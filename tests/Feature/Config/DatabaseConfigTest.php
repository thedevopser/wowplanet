<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * La bascule vers PostgreSQL de #22 a laissé derrière elle une connexion SQLite en
 * lecture seule, le temps que le volume de production soit retiré. Le jour où elle
 * réapparaît, ce n'est plus un reliquat de bascule : c'est une seconde base.
 *
 * L'assertion ne porte que sur ce que le projet déclare. Laravel fusionne son propre
 * fichier de configuration par défaut, qui apporte une connexion `sqlite` inutilisée
 * dans toute application du framework : elle est hors de notre portée, et inatteignable
 * tant que `DB_CONNECTION` vaut `pgsql`.
 */
test('the project declares no sqlite connection of its own', function (): void {
    /** @var array<string, mixed> $declared */
    $declared = require config_path('database.php');

    /** @var array<string, array{driver?: string}> $connections */
    $connections = $declared['connections'] ?? [];

    $sqliteConnections = array_keys(array_filter(
        $connections,
        static fn (array $connection): bool => ($connection['driver'] ?? null) === 'sqlite',
    ));

    expect($sqliteConnections)->toBe([]);
});

test('the default connection is postgresql', function (): void {
    expect(config('database.default'))->toBe('pgsql')
        ->and(config('database.connections.sqlite_legacy'))->toBeNull();
});

test('the one-shot legacy transfer command is gone', function (): void {
    expect(Artisan::all())->not->toHaveKey('app:migrate-legacy-sqlite');
});

test('the test database sorts accented names in French order, parallel processes included', function (): void {
    $names = array_column(DB::select("SELECT name FROM unnest(ARRAY['Zul''Gurub', 'Élune', 'Eau']) AS name ORDER BY name"), 'name');

    expect($names)->toBe(['Eau', 'Élune', "Zul'Gurub"]);
});
