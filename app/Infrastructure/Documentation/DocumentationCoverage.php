<?php

declare(strict_types=1);

namespace App\Infrastructure\Documentation;

/**
 * Mesure de la part du code nommée dans les pages de documentation.
 *
 * Fonction pure : la liste des classes et le texte des pages entrent, un rapport sort.
 * Le parcours du disque appartient à l'appelant.
 */
final class DocumentationCoverage
{
    /**
     * Une classe compte comme documentée quand son nom court ouvre une portion entre
     * backticks — la convention des pages. La correspondance porte sur le jeton entier :
     * `ImportStageRunner` ne satisfait pas `ImportStage`, sans quoi la mesure ne voudrait rien dire.
     *
     * @param  list<string>  $classNames  Noms complets, par exemple `App\Domain\Services\ScoreCalculator`
     * @param  list<string>  $excluded  Noms complets sortis du périmètre
     */
    public static function report(array $classNames, string $pages, array $excluded): CoverageReport
    {
        $mentioned = self::mentionedNames($pages);

        $missing = [];
        $documented = 0;
        $inPerimeter = 0;

        foreach ($classNames as $className) {
            if (in_array($className, $excluded, true)) {
                continue;
            }

            $inPerimeter++;

            if (isset($mentioned[self::shortNameOf($className)])) {
                $documented++;

                continue;
            }

            $missing[] = $className;
        }

        return new CoverageReport($missing, $documented, $inPerimeter, count($classNames) - $inPerimeter);
    }

    /**
     * Identifiants ouvrant une portion entre backticks, en ensemble indexé par le nom.
     *
     * @return array<string, true>
     */
    private static function mentionedNames(string $pages): array
    {
        preg_match_all('/`([^`\n]+)`/', $pages, $spans);

        $names = [];
        foreach ($spans[1] as $span) {
            if (preg_match('/^[A-Za-z_]\w*/', $span, $identifier) === 1) {
                $names[$identifier[0]] = true;
            }
        }

        return $names;
    }

    private static function shortNameOf(string $className): string
    {
        $segments = explode('\\', $className);

        return end($segments);
    }
}
