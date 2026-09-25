<?php

declare(strict_types=1);

namespace App\Domain\Services;

/**
 * Traduit un slug de bracket PvP en mode de jeu et en libellé FR.
 *
 * Aucune liste de brackets n'est figée : le mode se déduit du slug renvoyé par
 * l'API, de sorte qu'un mode ajouté par Blizzard reste affichable sans livraison.
 * Partagé par l'onglet du profil et la page de classements, qui doivent nommer
 * les mêmes modes de la même façon.
 */
class PvpBracketClassifier
{
    /**
     * Ordre d'affichage des modes, et libellés FR.
     *
     * @pest-mutate-ignore
     */
    public const GROUPS = [
        'arena' => 'Arène',
        'rbg' => 'Champs de bataille cotés',
        'shuffle' => 'Mêlée solo',
        'blitz' => 'Blitz',
        'other' => 'Autres modes',
    ];

    // @pest-mutate-ignore
    private const BRACKET_LABELS = [
        '2v2' => 'Arène 2c2',
        '3v3' => 'Arène 3c3',
        'rbg' => 'Champs de bataille cotés',
    ];

    public function groupFor(string $slug): string
    {
        if ($slug === '2v2' || $slug === '3v3') {
            return 'arena';
        }

        if ($slug === 'rbg') {
            return 'rbg';
        }

        if (str_starts_with($slug, 'shuffle-')) {
            return 'shuffle';
        }

        if (str_starts_with($slug, 'blitz-')) {
            return 'blitz';
        }

        return 'other';
    }

    public function groupLabel(string $group): string
    {
        return self::GROUPS[$group] ?? self::GROUPS['other'];
    }

    /**
     * Libellé complet, mode compris : « Mêlée solo — Ombre ».
     *
     * @param  string|null  $spec  Spécialisation renvoyée par l'API, quand elle l'est
     */
    public function labelFor(string $slug, ?string $spec = null): string
    {
        $group = $this->groupFor($slug);
        $short = $this->shortLabelFor($slug, $spec);

        if ($group === 'other' || isset(self::BRACKET_LABELS[$slug])) {
            return $short;
        }

        return $this->groupLabel($group).' — '.$short;
    }

    /**
     * Décompose « shuffle-deathknight-blood » en [classe, spécialisation], tels que
     * Blizzard les slugifie. Renvoie null pour tout bracket qui n'est pas par
     * spécialisation (2v2, rbg, « overall »…).
     *
     * @return array{0: string, 1: string}|null
     */
    public function specSlugsFor(string $slug): ?array
    {
        $group = $this->groupFor($slug);

        if ($group !== 'shuffle' && $group !== 'blitz') {
            return null;
        }

        $parts = explode('-', (string) preg_replace('/^(shuffle|blitz)-/', '', $slug));

        return count($parts) === 2 ? [$parts[0], $parts[1]] : null;
    }

    /**
     * Libellé sans le mode : ce qui distingue un bracket des autres du même mode.
     * C'est ce qu'affiche le second niveau du sélecteur de la page de classements.
     */
    public function shortLabelFor(string $slug, ?string $spec = null): string
    {
        if (isset(self::BRACKET_LABELS[$slug])) {
            return self::BRACKET_LABELS[$slug];
        }

        if ($spec !== null) {
            return $spec;
        }

        $tail = (string) preg_replace('/^(shuffle|blitz)-/', '', $slug);

        // Classement toutes spécialisations confondues, publié par Blizzard sous « overall ».
        if ($tail === 'overall') {
            return 'Toutes spés';
        }

        // Repli quand la spécialisation n'est pas fournie : « shuffle-priest-shadow » → « Priest Shadow ».
        return ucwords(str_replace('-', ' ', $tail));
    }
}
