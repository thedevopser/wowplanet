<?php

declare(strict_types=1);

use App\Domain\Services\ScoreCalculator;
use App\Domain\ValueObjects\CompletionScore;
use App\Domain\ValueObjects\ScoreDimension;
use App\Domain\ValueObjects\ScoreInput;
use App\Domain\ValueObjects\ScoreWeights;

/**
 * Construit une liste d'items de collection (montures, mascottes, décors).
 *
 * @return list<array<string, mixed>>
 */
function scoreItems(int $completed, int $total): array
{
    $items = [];
    for ($i = 0; $i < $total; $i++) {
        $items[] = ['id' => $i + 1, 'name' => 'Item '.($i + 1), 'is_completed' => $i < $completed];
    }

    return $items;
}

/**
 * Construit un groupe de collections d'extension pour un seul type.
 *
 * @return array<int, array<string, mixed>>
 */
function scoreCollections(string $type, int $completed, int $total): array
{
    return [10 => [$type => ['completed' => $completed, 'total' => $total]]];
}

/**
 * Récupère une dimension par sa clé dans un score calculé.
 */
function scoreDimension(CompletionScore $completionScore, string $key): ScoreDimension
{
    foreach ($completionScore->dimensions as $dimension) {
        if ($dimension->key === $key) {
            return $dimension;
        }
    }

    throw new RuntimeException('Dimension inconnue : '.$key);
}

beforeEach(function (): void {
    $this->calculator = new ScoreCalculator;
});

describe('pondération', function (): void {
    it('a des poids dont la somme fait exactement 1', function (): void {
        expect(array_sum(ScoreWeights::WEIGHTS))->toBeGreaterThan(0.999999)
            ->and(array_sum(ScoreWeights::WEIGHTS))->toBeLessThan(1.000001);
    });

    it('libelle chacune des dimensions pondérées', function (): void {
        expect(array_keys(ScoreWeights::LABELS))->toBe(array_keys(ScoreWeights::WEIGHTS));
    });

    it('expose les neuf dimensions dans l\'ordre de la pondération', function (): void {
        $score = $this->calculator->compute(new ScoreInput);

        expect(array_map(fn ($dimension): string => $dimension->key, $score->dimensions))
            ->toBe(array_keys(ScoreWeights::WEIGHTS));
    });

    it('porte la version de la formule', function (): void {
        expect($this->calculator->compute(new ScoreInput)->version)->toBe(ScoreWeights::VERSION);
    });
});

describe('dimensions de collection', function (): void {
    it('note les montures sur les items complétés', function (): void {
        $score = $this->calculator->compute(new ScoreInput(mounts: scoreItems(25, 100)));

        expect(scoreDimension($score, 'mounts')->score)->toBe(25.0)
            ->and(scoreDimension($score, 'mounts')->completed)->toBe(25.0)
            ->and(scoreDimension($score, 'mounts')->total)->toBe(100);
    });

    it('note les mascottes et les décors de la même façon', function (): void {
        $score = $this->calculator->compute(new ScoreInput(
            pets: scoreItems(5, 10),
            decor: scoreItems(1, 4),
        ));

        expect(scoreDimension($score, 'pets')->score)->toBe(50.0)
            ->and(scoreDimension($score, 'decor')->score)->toBe(25.0);
    });

    it('cumule quêtes, hauts-faits et réputations sur toutes les extensions', function (): void {
        $score = $this->calculator->compute(new ScoreInput(collections: [
            9 => ['quests' => ['completed' => 10, 'total' => 100]],
            10 => ['quests' => ['completed' => 30, 'total' => 100]],
        ]));

        expect(scoreDimension($score, 'quests')->completed)->toBe(40.0)
            ->and(scoreDimension($score, 'quests')->total)->toBe(200)
            ->and(scoreDimension($score, 'quests')->score)->toBe(20.0);
    });

    it('compte pour zéro un décompte absent d\'une extension', function (): void {
        $score = $this->calculator->compute(new ScoreInput(collections: [
            9 => ['quests' => ['total' => 10]],
            10 => ['quests' => ['completed' => 5]],
            11 => ['achievements' => ['completed' => 1, 'total' => 2]],
        ]));

        expect(scoreDimension($score, 'quests')->completed)->toBe(5.0)
            ->and(scoreDimension($score, 'quests')->total)->toBe(10);
    });

    it('rend une dimension applicable dès un seul élément', function (): void {
        $score = $this->calculator->compute(new ScoreInput(mounts: scoreItems(1, 1)));

        expect(scoreDimension($score, 'mounts')->applicable)->toBeTrue()
            ->and(scoreDimension($score, 'mounts')->score)->toBe(100.0);
    });

    it('ne compte pas un item dont l\'état de complétion est inconnu', function (): void {
        $score = $this->calculator->compute(new ScoreInput(mounts: [['is_completed' => true], []]));

        expect(scoreDimension($score, 'mounts')->completed)->toBe(1.0)
            ->and(scoreDimension($score, 'mounts')->total)->toBe(2);
    });
});

describe('garde-robe', function (): void {
    it('cumule les apparences débloquées sur tous les slots', function (): void {
        $score = $this->calculator->compute(new ScoreInput(appearances: [
            ['slot' => 'HEAD', 'category' => 'armor', 'total' => 100, 'completed' => 40],
            ['slot' => 'CHEST', 'category' => 'armor', 'total' => 100, 'completed' => 10],
        ]));

        expect(scoreDimension($score, 'transmog')->completed)->toBe(50.0)
            ->and(scoreDimension($score, 'transmog')->total)->toBe(200)
            ->and(scoreDimension($score, 'transmog')->score)->toBe(25.0);
    });

    it('compte pour zéro un décompte absent d\'un slot', function (): void {
        $score = $this->calculator->compute(new ScoreInput(appearances: [
            ['slot' => 'HEAD', 'total' => 10],
            ['slot' => 'CHEST', 'completed' => 4],
        ]));

        expect(scoreDimension($score, 'transmog')->completed)->toBe(4.0)
            ->and(scoreDimension($score, 'transmog')->total)->toBe(10);
    });
});

describe('raids', function (): void {
    /**
     * @param  array<string, list<int>>  $killsByDifficulty
     * @return list<array<string, mixed>>
     */
    $raid = function (int $totalBosses, array $killsByDifficulty): array {
        $modes = [];
        foreach ($killsByDifficulty as $difficulty => $bossIds) {
            $modes[] = [
                'difficulty_type' => $difficulty,
                'difficulty_label' => $difficulty,
                'completed_count' => count($bossIds),
                'total_count' => $totalBosses,
                'encounters' => array_map(fn (int $id): array => ['id' => $id, 'name' => 'Boss '.$id], $bossIds),
            ];
        }

        return [['instance_id' => 1, 'instance_name' => 'Raid', 'modes' => $modes]];
    };

    it('note un raid complété en normal à la moitié du maximum', function () use ($raid): void {
        $score = $this->calculator->compute(new ScoreInput(raids: $raid(8, ['NORMAL' => [1, 2, 3, 4, 5, 6, 7, 8]])));

        expect(scoreDimension($score, 'raids')->score)->toBe(50.0)
            ->and(scoreDimension($score, 'raids')->completed)->toBe(4.0)
            ->and(scoreDimension($score, 'raids')->total)->toBe(8);
    });

    it('note un raid complété en mythique au maximum', function () use ($raid): void {
        $score = $this->calculator->compute(new ScoreInput(raids: $raid(4, ['MYTHIC' => [1, 2, 3, 4]])));

        expect(scoreDimension($score, 'raids')->score)->toBe(100.0);
    });

    it('ne retient que le meilleur palier atteint pour un même boss', function () use ($raid): void {
        // Les quatre boss tués en LFR et en normal ne valent pas plus que le normal seul.
        $score = $this->calculator->compute(new ScoreInput(raids: $raid(4, [
            'LFR' => [1, 2, 3, 4],
            'NORMAL' => [1, 2, 3, 4],
        ])));

        expect(scoreDimension($score, 'raids')->score)->toBe(50.0);
    });

    it('mélange les paliers boss par boss', function () use ($raid): void {
        // 2 boss en mythique (1.0), 1 en héroïque (0.75), 1 jamais tué (0) sur 4.
        $score = $this->calculator->compute(new ScoreInput(raids: $raid(4, [
            'NORMAL' => [1, 2, 3],
            'HEROIC' => [1, 2, 3],
            'MYTHIC' => [1, 2],
        ])));

        expect(scoreDimension($score, 'raids')->completed)->toBe(2.75)
            ->and(scoreDimension($score, 'raids')->score)->toBe(68.75);
    });

    it('compte un boss jamais tué pour zéro', function () use ($raid): void {
        $score = $this->calculator->compute(new ScoreInput(raids: $raid(8, ['MYTHIC' => [1, 2]])));

        expect(scoreDimension($score, 'raids')->score)->toBe(25.0);
    });

    it('ignore un palier de difficulté inconnu', function () use ($raid): void {
        $score = $this->calculator->compute(new ScoreInput(raids: $raid(2, ['EVENT' => [1, 2]])));

        expect(scoreDimension($score, 'raids')->score)->toBe(0.0)
            ->and(scoreDimension($score, 'raids')->applicable)->toBeTrue();
    });

    it('additionne le total de boss de plusieurs raids', function (): void {
        $raids = [
            ['instance_id' => 1, 'instance_name' => 'A', 'modes' => [
                ['difficulty_type' => 'MYTHIC', 'completed_count' => 2, 'total_count' => 2, 'encounters' => [['id' => 1], ['id' => 2]]],
            ]],
            ['instance_id' => 2, 'instance_name' => 'B', 'modes' => [
                ['difficulty_type' => 'NORMAL', 'completed_count' => 0, 'total_count' => 6, 'encounters' => []],
            ]],
        ];

        $score = $this->calculator->compute(new ScoreInput(raids: $raids));

        expect(scoreDimension($score, 'raids')->total)->toBe(8)
            ->and(scoreDimension($score, 'raids')->completed)->toBe(2.0);
    });

    it('retient le plus grand nombre de boss annoncé par les paliers', function (): void {
        $raids = [['instance_id' => 1, 'instance_name' => 'A', 'modes' => [
            ['difficulty_type' => 'LFR', 'completed_count' => 0, 'total_count' => 0, 'encounters' => []],
            ['difficulty_type' => 'NORMAL', 'completed_count' => 0, 'total_count' => 8, 'encounters' => []],
        ]]];

        expect(scoreDimension($this->calculator->compute(new ScoreInput(raids: $raids)), 'raids')->total)->toBe(8);
    });

    it('ne compte aucun boss pour un raid sans palier ni pour un palier sans total', function (): void {
        $raids = [
            ['instance_id' => 1, 'instance_name' => 'A'],
            ['instance_id' => 2, 'instance_name' => 'B', 'modes' => [['difficulty_type' => 'NORMAL', 'encounters' => []]]],
            ['instance_id' => 3, 'instance_name' => 'C', 'modes' => [
                ['difficulty_type' => 'MYTHIC', 'total_count' => 2, 'encounters' => [['id' => 1]]],
            ]],
        ];

        $scoreDimension = scoreDimension($this->calculator->compute(new ScoreInput(raids: $raids)), 'raids');

        expect($scoreDimension->total)->toBe(2)
            ->and($scoreDimension->completed)->toBe(1.0);
    });

    it("garde le meilleur palier d'un boss quel que soit l'ordre des paliers", function (): void {
        $raids = [['instance_id' => 1, 'instance_name' => 'A', 'modes' => [
            ['difficulty_type' => 'MYTHIC', 'total_count' => 1, 'encounters' => [['id' => 1]]],
            ['difficulty_type' => 'NORMAL', 'total_count' => 1, 'encounters' => [['id' => 1]]],
        ]]];

        expect(scoreDimension($this->calculator->compute(new ScoreInput(raids: $raids)), 'raids')->completed)->toBe(1.0);
    });

    it('lit les paliers suivant un palier inconnu ou sans difficulté', function (): void {
        $raids = [['instance_id' => 1, 'instance_name' => 'A', 'modes' => [
            ['difficulty_type' => 'EVENT', 'total_count' => 2, 'encounters' => [['id' => 1]]],
            ['total_count' => 2, 'encounters' => [['id' => 1]]],
            ['difficulty_type' => 'NORMAL', 'total_count' => 2, 'encounters' => [['id' => 1], ['id' => 2]]],
        ]]];

        expect(scoreDimension($this->calculator->compute(new ScoreInput(raids: $raids)), 'raids')->completed)->toBe(1.0);
    });

    it('passe un boss sans identifiant sans ignorer les suivants', function (): void {
        $raids = [['instance_id' => 1, 'instance_name' => 'A', 'modes' => [
            ['difficulty_type' => 'MYTHIC', 'total_count' => 2, 'encounters' => [['name' => 'Inconnu'], ['id' => 2]]],
        ]]];

        expect(scoreDimension($this->calculator->compute(new ScoreInput(raids: $raids)), 'raids')->completed)->toBe(1.0);
    });

    it('sort les raids du calcul quand le tier courant est inconnu', function (): void {
        $scoreDimension = scoreDimension($this->calculator->compute(new ScoreInput(raids: null)), 'raids');

        expect($scoreDimension->completed)->toBe(0.0)
            ->and($scoreDimension->total)->toBe(0)
            ->and($scoreDimension->score)->toBe(0.0)
            ->and($scoreDimension->applicable)->toBeFalse();
    });
});

describe('métiers', function (): void {
    it('privilégie les meilleures statistiques de métier fournies', function (): void {
        $score = $this->calculator->compute(new ScoreInput(
            professions: [['profession_id' => 164, 'expansions' => [10 => ['completed' => 1, 'total' => 100]]]],
            bestProfessionStats: ['completed' => 30, 'total' => 60],
        ));

        expect(scoreDimension($score, 'professions')->score)->toBe(50.0);
    });

    it('cumule les recettes des extensions à défaut', function (): void {
        $score = $this->calculator->compute(new ScoreInput(professions: [
            ['profession_id' => 164, 'expansions' => [
                9 => ['completed' => 10, 'total' => 40, 'skill_points' => 100, 'max_skill_points' => 100],
                10 => ['completed' => 10, 'total' => 40, 'skill_points' => 50, 'max_skill_points' => 100],
            ]],
        ]));

        expect(scoreDimension($score, 'professions')->score)->toBe(25.0);
    });

    it('retombe sur les points de compétence quand aucune recette n\'est connue', function (): void {
        $score = $this->calculator->compute(new ScoreInput(professions: [
            ['profession_id' => 164, 'expansions' => [
                10 => ['completed' => 0, 'total' => 0, 'skill_points' => 75, 'max_skill_points' => 100],
            ]],
        ]));

        expect(scoreDimension($score, 'professions')->score)->toBe(75.0)
            ->and(scoreDimension($score, 'professions')->total)->toBe(100);
    });

    it('ignore des meilleures statistiques sans recette au profit du personnage', function (int $bestTotal): void {
        $score = $this->calculator->compute(new ScoreInput(
            professions: [['profession_id' => 164, 'expansions' => [10 => ['completed' => 1, 'total' => 4]]]],
            bestProfessionStats: ['completed' => 0, 'total' => $bestTotal],
        ));

        expect(scoreDimension($score, 'professions')->total)->toBe(4);
    })->with([0, -1]);

    it('retient des meilleures statistiques portant une seule recette', function (): void {
        $score = $this->calculator->compute(new ScoreInput(bestProfessionStats: ['completed' => 1, 'total' => 1]));

        expect(scoreDimension($score, 'professions')->score)->toBe(100.0);
    });

    it("retient les recettes d'une extension qui n'en compte qu'une", function (): void {
        $score = $this->calculator->compute(new ScoreInput(professions: [
            ['profession_id' => 164, 'expansions' => [10 => ['completed' => 1, 'total' => 1, 'skill_points' => 10, 'max_skill_points' => 100]]],
        ]));

        expect(scoreDimension($score, 'professions')->total)->toBe(1);
    });

    it('compte pour zéro un décompte absent d\'une extension de métier', function (): void {
        $recipes = $this->calculator->compute(new ScoreInput(professions: [
            ['profession_id' => 164, 'expansions' => [9 => ['total' => 10], 10 => ['completed' => 3]]],
        ]));
        $skills = $this->calculator->compute(new ScoreInput(professions: [
            ['profession_id' => 164, 'expansions' => [9 => ['max_skill_points' => 100], 10 => ['skill_points' => 30]]],
            ['profession_id' => 171],
        ]));

        expect(scoreDimension($recipes, 'professions')->completed)->toBe(3.0)
            ->and(scoreDimension($recipes, 'professions')->total)->toBe(10)
            ->and(scoreDimension($skills, 'professions')->completed)->toBe(30.0)
            ->and(scoreDimension($skills, 'professions')->total)->toBe(100);
    });
});

describe('score global', function (): void {
    it('vaut zéro sur un profil vide', function (): void {
        $score = $this->calculator->compute(new ScoreInput);

        expect($score->global)->toBe(0.0)
            ->and($score->rank)->toBe('Débutant');
    });

    it('marque comme non applicable une dimension sans données', function (): void {
        $score = $this->calculator->compute(new ScoreInput(mounts: scoreItems(1, 2)));

        expect(scoreDimension($score, 'mounts')->applicable)->toBeTrue()
            ->and(scoreDimension($score, 'raids')->applicable)->toBeFalse()
            ->and(scoreDimension($score, 'raids')->total)->toBe(0)
            ->and(scoreDimension($score, 'pets')->score)->toBe(0.0);
    });

    it('redistribue le poids des dimensions non applicables', function (): void {
        // Seules les montures sont renseignées : le global vaut leur score, pas leur score × 0,13.
        $score = $this->calculator->compute(new ScoreInput(mounts: scoreItems(40, 100)));

        expect($score->global)->toBe(40.0);
    });

    it('pondère deux dimensions applicables l\'une par rapport à l\'autre', function (): void {
        $score = $this->calculator->compute(new ScoreInput(
            collections: scoreCollections('achievements', 100, 100),
            mounts: scoreItems(0, 100),
        ));

        $expected = round(100 * ScoreWeights::WEIGHTS['achievements']
            / (ScoreWeights::WEIGHTS['achievements'] + ScoreWeights::WEIGHTS['mounts']), 1);

        expect($score->global)->toBe($expected);
    });

    it('atteint cent quand toutes les dimensions renseignées sont complètes', function (): void {
        $score = $this->calculator->compute(new ScoreInput(
            collections: scoreCollections('quests', 50, 50),
            mounts: scoreItems(10, 10),
            appearances: [['slot' => 'HEAD', 'category' => 'armor', 'total' => 10, 'completed' => 10]],
        ));

        expect($score->global)->toBe(100.0)
            ->and($score->rank)->toBe('Légendaire');
    });

    it('nomme les rangs par palier, bornes incluses', function (int $perThousand, string $expected): void {
        $score = $this->calculator->compute(new ScoreInput(mounts: scoreItems($perThousand, 1000)));

        expect($score->rank)->toBe($expected);
    })->with([
        [900, 'Légendaire'],
        [899, 'Épique'],
        [750, 'Épique'],
        [749, 'Rare'],
        [500, 'Rare'],
        [499, 'Commun'],
        [250, 'Commun'],
        [249, 'Débutant'],
    ]);

    it('arrondit le global au dixième', function (): void {
        $score = $this->calculator->compute(new ScoreInput(mounts: scoreItems(1, 3)));

        expect($score->global)->toBe(33.3);
    });
});

describe('non-régression', function (): void {
    it('note un profil de complétionniste avancé', function (): void {
        $score = $this->calculator->compute(new ScoreInput(
            collections: [
                10 => [
                    'quests' => ['completed' => 800, 'total' => 1000],
                    'achievements' => ['completed' => 600, 'total' => 1000],
                    'reputations' => ['completed' => 40, 'total' => 50],
                ],
            ],
            mounts: scoreItems(400, 800),
            pets: scoreItems(600, 1000),
            decor: scoreItems(100, 400),
            appearances: [['slot' => 'HEAD', 'category' => 'armor', 'total' => 1000, 'completed' => 300]],
            raids: [['instance_id' => 1, 'instance_name' => 'A', 'modes' => [
                ['difficulty_type' => 'HEROIC', 'completed_count' => 8, 'total_count' => 8,
                    'encounters' => array_map(fn (int $i): array => ['id' => $i], range(1, 8))],
            ]]],
            bestProfessionStats: ['completed' => 300, 'total' => 500],
        ));

        // 10,4 + 12,0 + 9,6 + 5,25 + 6,5 + 3,6 + 4,8 + 1,75 + 4,8 — toutes les dimensions sont applicables.
        expect($score->global)->toBe(58.7)
            ->and($score->rank)->toBe('Rare');
    });

    it('note un profil de joueur occasionnel sans raid ni métier', function (): void {
        $score = $this->calculator->compute(new ScoreInput(
            collections: [10 => ['quests' => ['completed' => 100, 'total' => 1000]]],
            mounts: scoreItems(40, 800),
            pets: scoreItems(50, 1000),
        ));

        // Seules quêtes (10), montures (5) et mascottes (5) sont applicables.
        $weights = ScoreWeights::WEIGHTS;
        $expected = round(
            (10 * $weights['quests'] + 5 * $weights['mounts'] + 5 * $weights['pets'])
            / ($weights['quests'] + $weights['mounts'] + $weights['pets']),
            1,
        );

        expect($score->global)->toBe($expected)
            ->and($score->rank)->toBe('Débutant');
    });
});
