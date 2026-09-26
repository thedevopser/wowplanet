<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\BlizzardInternalName;

test('a name opened by a bracketed or angled tag is internal', function (string $name): void {
    expect(BlizzardInternalName::isInternal($name))->toBeTrue();
})->with([
    '[PH] Rainbow Axe - 1h - Purple',
    '[PÉRIMÉ]Semez votre graine',
    "[En attente d'un nouveau monstre]Finlay manque de tripes",
    '<NYI> <TXT> Pirate Hats',
    '  [PH] Acheter une bride',
]);

test('a name led by a patch number is internal', function (string $name): void {
    expect(BlizzardInternalName::isInternal($name))->toBeTrue();
})->with([
    '9.0 PvP - PvP Reward - Tabard - 4',
    '10.0 Rare Reward TBD - Mace2H - Str - 2 Hand',
    '11.2.5 Placeholder Cloak',
]);

test('an upper-case development marker makes a name internal', function (string $name): void {
    expect(BlizzardInternalName::isInternal($name))->toBeTrue();
})->with([
    'Le héraut <NYI>',
    'Livraison de stèle du vice TBD',
    "DNT Ula'tek Pole Dummy F",
    'NYI',
    'TBD',
    'TEST 130 Epic Paladin DPS Chest',
]);

test('a bare test name is internal', function (string $name): void {
    expect(BlizzardInternalName::isInternal($name))->toBeTrue();
})->with(['Test', 'Test Quest']);

test('a released name is kept, even when it holds a marker in lower case or inside a word', function (string $name): void {
    expect(BlizzardInternalName::isInternal($name))->toBeFalse();
})->with([
    'Test de courage',
    'Test effectué',
    "Testament d'espoir",
    '« Brassards intelligents »',
    'Cape du grizzly',
    "Glaive de guerre d'Azzinoth (droit)",
    "Les Lames jumelles d'Azzinoth",
    'Garde-robe de TBDX',
    'Épée de la 7e légion',
    'Arme 9.0',
]);
