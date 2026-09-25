<?php

declare(strict_types=1);

namespace App\Http\Character;

/**
 * Vue de la fiche désignée par les segments d'URL qui suivent le nom du personnage.
 */
final readonly class CharacterSheetView
{
    private function __construct(
        public ?CharacterSheetSection $section,
        public ?string $sub,
    ) {}

    /**
     * @throws UnknownCharacterSheetSegment
     */
    public static function fromSegments(?string $section, ?string $sub): self
    {
        if ($section === null) {
            return $sub === null ? new self(null, null) : throw UnknownCharacterSheetSegment::for($section, $sub);
        }

        $known = CharacterSheetSection::tryFrom($section) ?? throw UnknownCharacterSheetSegment::for($section, $sub);

        if ($sub === null) {
            return new self($known, $known->subTabs()[0]);
        }

        if (! in_array($sub, $known->subTabs(), true)) {
            throw UnknownCharacterSheetSegment::for($section, $sub);
        }

        return new self($known, $sub);
    }

    public function isOverview(): bool
    {
        return ! $this->section instanceof CharacterSheetSection;
    }
}
