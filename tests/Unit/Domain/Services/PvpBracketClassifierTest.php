<?php

declare(strict_types=1);

use App\Domain\Services\PvpBracketClassifier;

beforeEach(function (): void {
    $this->classifier = new PvpBracketClassifier;
});

describe('mode de jeu', function (): void {
    it('déduit le mode du slug renvoyé par l\'API', function (string $slug, string $group): void {
        expect($this->classifier->groupFor($slug))->toBe($group);
    })->with([
        ['2v2', 'arena'],
        ['3v3', 'arena'],
        ['rbg', 'rbg'],
        ['shuffle-priest-shadow', 'shuffle'],
        ['shuffle-overall', 'shuffle'],
        ['blitz-mage-frost', 'blitz'],
        ['5v5', 'other'],
        ['shuffle', 'other'],
        ['blitz', 'other'],
        ['brawl-shuffle-priest', 'other'],
    ]);

    it('nomme chaque mode connu', function (string $group, string $label): void {
        expect($this->classifier->groupLabel($group))->toBe($label);
    })->with([
        ['arena', 'Arène'],
        ['rbg', 'Champs de bataille cotés'],
        ['shuffle', 'Mêlée solo'],
        ['blitz', 'Blitz'],
        ['other', 'Autres modes'],
    ]);

    it('range un mode inconnu dans les autres modes', function (): void {
        expect($this->classifier->groupLabel('brawl'))->toBe('Autres modes');
    });
});

describe('libellé court', function (): void {
    it('nomme les brackets fixes par leur libellé, même avec une spécialisation', function (string $slug, string $label): void {
        expect($this->classifier->shortLabelFor($slug, 'Ombre'))->toBe($label);
    })->with([
        ['2v2', 'Arène 2c2'],
        ['3v3', 'Arène 3c3'],
        ['rbg', 'Champs de bataille cotés'],
    ]);

    it('reprend la spécialisation fournie par l\'API', function (): void {
        expect($this->classifier->shortLabelFor('shuffle-priest-shadow', 'Ombre'))->toBe('Ombre');
    });

    it('nomme le classement toutes spécialisations confondues', function (string $slug): void {
        expect($this->classifier->shortLabelFor($slug))->toBe('Toutes spés');
    })->with(['shuffle-overall', 'blitz-overall']);

    it('déduit un libellé du slug quand la spécialisation manque', function (string $slug, string $label): void {
        expect($this->classifier->shortLabelFor($slug))->toBe($label);
    })->with([
        ['shuffle-priest-shadow', 'Priest Shadow'],
        ['blitz-mage-frost', 'Mage Frost'],
        ['brawl-shuffle-priest', 'Brawl Shuffle Priest'],
    ]);
});

describe('libellé complet', function (): void {
    it('préfixe le libellé du mode pour les brackets par spécialisation', function (): void {
        expect($this->classifier->labelFor('shuffle-priest-shadow', 'Ombre'))->toBe('Mêlée solo — Ombre')
            ->and($this->classifier->labelFor('blitz-overall'))->toBe('Blitz — Toutes spés');
    });

    it('ne répète pas le mode dans le libellé d\'un bracket fixe', function (string $slug, string $label): void {
        expect($this->classifier->labelFor($slug))->toBe($label);
    })->with([
        ['2v2', 'Arène 2c2'],
        ['rbg', 'Champs de bataille cotés'],
    ]);

    it('ne préfixe pas un mode inconnu', function (): void {
        expect($this->classifier->labelFor('brawl-solo'))->toBe('Brawl Solo');
    });
});

describe('spécialisation', function (): void {
    it('décompose un bracket par spécialisation en classe et spécialisation', function (string $slug): void {
        expect($this->classifier->specSlugsFor($slug))->toBe(['deathknight', 'blood']);
    })->with(['shuffle-deathknight-blood', 'blitz-deathknight-blood']);

    it('ne décompose pas un bracket qui n\'est pas par spécialisation', function (string $slug): void {
        expect($this->classifier->specSlugsFor($slug))->toBeNull();
    })->with([
        '2v2',
        'rbg',
        'shuffle-overall',
        'shuffle-deathknight-blood-extra',
        'brawl-deathknight-blood',
        'brawl-solo',
    ]);
});
