<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

use InvalidArgumentException;

readonly class ExpansionId
{
    // @pest-mutate-ignore
    public const CLASSIC = 0;

    // @pest-mutate-ignore
    public const BURNING_CRUSADE = 1;

    // @pest-mutate-ignore
    public const WRATH_OF_THE_LICH_KING = 2;

    // @pest-mutate-ignore
    public const CATACLYSM = 3;

    // @pest-mutate-ignore
    public const MISTS_OF_PANDARIA = 4;

    // @pest-mutate-ignore
    public const WARLORDS_OF_DRAENOR = 5;

    // @pest-mutate-ignore
    public const LEGION = 6;

    // @pest-mutate-ignore
    public const BATTLE_FOR_AZEROTH = 7;

    // @pest-mutate-ignore
    public const SHADOWLANDS = 8;

    // @pest-mutate-ignore
    public const DRAGONFLIGHT = 9;

    // @pest-mutate-ignore
    public const THE_WAR_WITHIN = 10;

    // @pest-mutate-ignore
    public const MIDNIGHT = 11;

    /**
     * Le seau de ce qui n'est daté par rien.
     *
     * Hors de la suite des extensions à dessein : une entrée non datée n'est pas du contenu
     * d'origine, et la verser dans Classic la déguiserait en contenu du jeu de base. La
     * valeur est prise loin devant pour que la prochaine extension reste 12.
     *
     * @pest-mutate-ignore
     */
    public const UNCLASSIFIED = 99;

    public function __construct(public int $value)
    {
        if (! array_key_exists($this->value, self::SLUG_MAP)) {
            throw new InvalidArgumentException('Invalid Expansion ID: '.$this->value);
        }
    }

    // @pest-mutate-ignore
    private const SLUG_MAP = [
        self::CLASSIC => 'classic',
        self::BURNING_CRUSADE => 'burning-crusade',
        self::WRATH_OF_THE_LICH_KING => 'wrath',
        self::CATACLYSM => 'cataclysm',
        self::MISTS_OF_PANDARIA => 'pandaria',
        self::WARLORDS_OF_DRAENOR => 'draenor',
        self::LEGION => 'legion',
        self::BATTLE_FOR_AZEROTH => 'battle-for-azeroth',
        self::SHADOWLANDS => 'shadowlands',
        self::DRAGONFLIGHT => 'dragonflight',
        self::THE_WAR_WITHIN => 'the-war-within',
        self::MIDNIGHT => 'midnight',
        self::UNCLASSIFIED => 'non-classe',
    ];

    public function toString(): string
    {
        return match ($this->value) {
            self::CLASSIC => 'Classic',
            self::BURNING_CRUSADE => 'The Burning Crusade',
            self::WRATH_OF_THE_LICH_KING => 'Wrath of the Lich King',
            self::CATACLYSM => 'Cataclysm',
            self::MISTS_OF_PANDARIA => 'Mists of Pandaria',
            self::WARLORDS_OF_DRAENOR => 'Warlords of Draenor',
            self::LEGION => 'Legion',
            self::BATTLE_FOR_AZEROTH => 'Battle for Azeroth',
            self::SHADOWLANDS => 'Shadowlands',
            self::DRAGONFLIGHT => 'Dragonflight',
            self::THE_WAR_WITHIN => 'The War Within',
            self::MIDNIGHT => 'Midnight',
            self::UNCLASSIFIED => 'Non classé',
        };
    }

    public function toOrdinal(): string
    {
        return match ($this->value) {
            self::CLASSIC => 'le jeu original',
            self::BURNING_CRUSADE => 'la 1re extension',
            self::WRATH_OF_THE_LICH_KING => 'la 2e extension',
            self::CATACLYSM => 'la 3e extension',
            self::MISTS_OF_PANDARIA => 'la 4e extension',
            self::WARLORDS_OF_DRAENOR => 'la 5e extension',
            self::LEGION => 'la 6e extension',
            self::BATTLE_FOR_AZEROTH => 'la 7e extension',
            self::SHADOWLANDS => 'la 8e extension',
            self::DRAGONFLIGHT => 'la 9e extension',
            self::THE_WAR_WITHIN => 'la 10e extension',
            self::MIDNIGHT => 'la 11e extension',
            self::UNCLASSIFIED => 'aucune extension',
        };
    }

    public function toSlug(): string
    {
        return self::SLUG_MAP[$this->value];
    }

    public static function fromSlug(string $slug): ?self
    {
        $id = array_search($slug, self::SLUG_MAP, true);

        return $id !== false ? new self($id) : null;
    }

    /**
     * @return array<int, string>
     */
    public static function allSlugs(): array
    {
        return self::SLUG_MAP;
    }
}
