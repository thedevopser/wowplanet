<?php

declare(strict_types=1);

namespace App\Infrastructure\Mutation;

/**
 * Les fichiers du périmètre de mutation qu'une branche concerne.
 *
 * Fonction pure : les fichiers du périmètre et les chemins modifiés entrent, la sélection sort.
 * Lire le périmètre et interroger Git appartient à l'appelant.
 */
final class MutationScope
{
    private const string TESTS_DIRECTORY = 'tests/';

    private const string TEST_SUFFIX = 'Test.php';

    /**
     * Un fichier est retenu quand la branche le modifie, quand elle modifie ou supprime un test
     * qui porte son nom — `ScoreCalculatorTest.php`, `ScoreCalculatorEdgeCasesTest.php` —, ou
     * quand elle vient de le déclarer dans le périmètre. Retirer un test protège autant de
     * règles que modifier la classe en met en jeu.
     *
     * @param  list<string>  $perimeterFiles  Fichiers PHP du périmètre, relatifs à la racine du projet
     * @param  list<string>  $newlyDeclaredFiles  Fichiers entrés dans le périmètre sur la branche
     * @param  list<string>  $changedPaths  Chemins modifiés, ajoutés ou supprimés sur la branche
     * @return list<string>
     */
    public static function select(array $perimeterFiles, array $newlyDeclaredFiles, array $changedPaths): array
    {
        $selected = array_filter(
            $perimeterFiles,
            fn (string $file): bool => in_array($file, $changedPaths, true)
                || in_array($file, $newlyDeclaredFiles, true)
                || self::dedicatedTests($file, $changedPaths) !== [],
        );

        $selected = array_values(array_unique($selected));
        sort($selected);

        return $selected;
    }

    /**
     * Les tests d'une classe sont ceux qui portent son nom : `ImportRunTest.php` et
     * `ImportRunStateTest.php` pour `ImportRun`. Une classe est mutée contre eux seuls.
     *
     * @param  list<string>  $testFiles  Chemins relatifs à la racine du projet
     * @return list<string>
     */
    public static function dedicatedTests(string $file, array $testFiles): array
    {
        $className = basename($file, '.php');

        $dedicated = array_values(array_filter(
            $testFiles,
            fn (string $testFile): bool => self::isTest($testFile) && str_starts_with(basename($testFile), $className),
        ));
        sort($dedicated);

        return $dedicated;
    }

    private static function isTest(string $path): bool
    {
        return str_starts_with($path, self::TESTS_DIRECTORY) && str_ends_with($path, self::TEST_SUFFIX);
    }
}
