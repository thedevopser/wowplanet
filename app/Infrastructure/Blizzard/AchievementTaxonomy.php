<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard;

use App\Domain\ValueObjects\ExpansionId;
use App\Infrastructure\Blizzard\Responses\AchievementCategoryDocument;

/**
 * Le rangement des hauts faits, déduit de la hiérarchie des catégories.
 *
 * La catégorie racine nomme, la sous-catégorie date. C'est vérifié : les sous-catégories
 * de l'API suivent la sortie du contenu et non la géographie — « Reprise des Chants
 * éternels », situé en Quel'Thalas, est rangé sous Midnight et non sous Royaumes de l'Est.
 * Une extension ne se déduit donc jamais d'un nom de lieu, mais la catégorie reste un
 * signal de sortie fiable.
 *
 * Ce qu'aucun ancêtre ne date tombe dans un seau nommé plutôt que dans l'extension 0, et
 * la construction rend compte de ce qui y atterrit : un rangement qu'on ignore doit se
 * lire dans le rapport d'import, jamais se découvrir par un tri devenu faux.
 */
final readonly class AchievementTaxonomy
{
    /**
     * Les sous-catégories à nom de continent sont les seaux Classic de l'onglet Quêtes.
     * Elles n'ont jamais reçu de contenu récent, et le jour où elles en reçoivent,
     * l'hypothèse de datation par la catégorie se périme.
     *
     * @var list<string>
     */
    private const PLACE_NAMED_CATEGORIES = ['Kalimdor', "Royaumes de l'Est", 'Outreterre', 'Norfendre'];

    private const RECENT_ACHIEVEMENT_ID = 40000;

    /**
     * @param  list<AchievementPlacement>  $placements
     * @param  array<string, int>  $unrankedCategories
     * @param  list<string>  $staleDatingSignals
     */
    private function __construct(
        private array $placements,
        private array $unrankedCategories,
        private array $staleDatingSignals,
    ) {}

    /**
     * @param  list<AchievementCategoryDocument>  $categories
     */
    public static function fromCategories(array $categories): self
    {
        $byId = [];
        foreach ($categories as $category) {
            $byId[$category->id] = $category;
        }

        /** @var array<int, array{placement: AchievementPlacement, label: string, categoryId: int}> $kept */
        $kept = [];
        $staleDatingSignals = [];

        foreach ($categories as $category) {
            $chain = self::chainToRoot($byId, $category);
            $root = $chain[count($chain) - 1];
            $expansionId = self::expansionOf($chain);
            $label = $category->id === $root->id ? $root->name : $root->name.' > '.$category->name;

            foreach ($category->achievements as $achievementId => $name) {
                $candidate = [
                    'placement' => new AchievementPlacement($achievementId, $name, $root->name, $expansionId),
                    'label' => $label,
                    'categoryId' => $category->id,
                ];

                if (! isset($kept[$achievementId]) || self::wins($candidate, $kept[$achievementId])) {
                    $kept[$achievementId] = $candidate;
                }

                if (self::isStaleDatingSignal($category, $achievementId)) {
                    $staleDatingSignals[] = sprintf('%s: %d %s', $label, $achievementId, $name);
                }
            }
        }

        ksort($kept);

        $placements = [];
        $unrankedCategories = [];
        foreach ($kept as $entry) {
            $placements[] = $entry['placement'];

            if ($entry['placement']->expansionId === ExpansionId::UNCLASSIFIED) {
                $unrankedCategories[$entry['label']] = ($unrankedCategories[$entry['label']] ?? 0) + 1;
            }
        }

        return new self($placements, $unrankedCategories, $staleDatingSignals);
    }

    /**
     * @return list<AchievementPlacement>
     */
    public function placements(): array
    {
        return $this->placements;
    }

    /**
     * Catégories dont les hauts faits n'ont pu être datés, et combien chacune en porte.
     *
     * @return array<string, int>
     */
    public function unrankedCategories(): array
    {
        return $this->unrankedCategories;
    }

    /**
     * @return list<string>
     */
    public function staleDatingSignals(): array
    {
        return $this->staleDatingSignals;
    }

    /**
     * La catégorie, ses ancêtres, puis la racine. Le parcours s'arrête sur un parent
     * inconnu comme sur un cycle : une hiérarchie incohérente ne doit pas suspendre
     * un import.
     *
     * @param  array<int, AchievementCategoryDocument>  $byId
     * @return non-empty-list<AchievementCategoryDocument>
     */
    private static function chainToRoot(array $byId, AchievementCategoryDocument $achievementCategoryDocument): array
    {
        $chain = [$achievementCategoryDocument];
        $seen = [$achievementCategoryDocument->id => true];
        $current = $achievementCategoryDocument;

        while ($current->parentId !== null && isset($byId[$current->parentId]) && ! isset($seen[$current->parentId])) {
            $current = $byId[$current->parentId];
            $seen[$current->id] = true;
            $chain[] = $current;
        }

        return $chain;
    }

    /**
     * L'extension du premier ancêtre daté, la racine exclue : aucune racine ne porte de
     * nom d'extension, et les faire concourir n'ajouterait que des faux positifs.
     *
     * @param  non-empty-list<AchievementCategoryDocument>  $chain
     */
    private static function expansionOf(array $chain): int
    {
        for ($level = 0; $level < count($chain) - 1; $level++) {
            $expansionId = ExpansionTierMatcher::match($chain[$level]->name);
            if ($expansionId !== null) {
                return $expansionId;
            }
        }

        return ExpansionId::UNCLASSIFIED;
    }

    /**
     * Ordre total, indépendant de l'ordre de parcours : un haut fait listé sous deux
     * catégories doit être rangé au même endroit d'un import à l'autre.
     *
     * @param  array{placement: AchievementPlacement, label: string, categoryId: int}  $candidate
     * @param  array{placement: AchievementPlacement, label: string, categoryId: int}  $incumbent
     */
    private static function wins(array $candidate, array $incumbent): bool
    {
        $candidateIsDated = $candidate['placement']->expansionId !== ExpansionId::UNCLASSIFIED;
        $incumbentIsDated = $incumbent['placement']->expansionId !== ExpansionId::UNCLASSIFIED;

        if ($candidateIsDated !== $incumbentIsDated) {
            return $candidateIsDated;
        }

        return $candidate['categoryId'] < $incumbent['categoryId'];
    }

    private static function isStaleDatingSignal(AchievementCategoryDocument $achievementCategoryDocument, int $achievementId): bool
    {
        return in_array($achievementCategoryDocument->name, self::PLACE_NAMED_CATEGORIES, true)
            && $achievementId >= self::RECENT_ACHIEVEMENT_ID;
    }
}
