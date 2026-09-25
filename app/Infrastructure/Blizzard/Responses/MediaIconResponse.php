<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses;

/**
 * Le media d'un objet ou d'un sort, `data/wow/media/{item|spell}/{id}`, réduit à son icône.
 */
final readonly class MediaIconResponse
{
    private const string ICON_ASSET_KEY = 'icon';

    public function __construct(public ?string $iconUrl) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        foreach ($responsePayload->objectList('assets') as $asset) {
            $value = $asset->optionalString('value');
            if ($value !== null && $asset->optionalString('key') === self::ICON_ASSET_KEY) {
                return new self($value);
            }
        }

        return new self(null);
    }
}
