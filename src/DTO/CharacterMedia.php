<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class CharacterMedia
{
    public function __construct(
        public ?string $avatarUrl,
        public ?string $renderUrl,
        public ?string $mainRawUrl
    ) {
    }

    /**
     * @param array<string, mixed> $mediaData
     */
    public static function fromApiData(array $mediaData): self
    {
        $assets = is_array($mediaData['assets'] ?? null) ? $mediaData['assets'] : [];

        $avatarUrl = null;
        $renderUrl = null;
        $mainRawUrl = null;

        foreach ($assets as $asset) {
            if (!is_array($asset)) {
                continue;
            }

            $key = $asset['key'] ?? null;
            $value = $asset['value'] ?? null;

            if (!is_string($key) || !is_string($value)) {
                continue;
            }

            match ($key) {
                'avatar' => $avatarUrl = $value,
                'inset' => $renderUrl = $value,
                'main-raw' => $mainRawUrl = $value,
                default => null,
            };
        }

        return new self($avatarUrl, $renderUrl, $mainRawUrl);
    }
}
