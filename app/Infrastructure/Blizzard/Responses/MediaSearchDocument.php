<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses;

/**
 * Un document de `data/wow/search/media`, réduit à son icône.
 *
 * Un media d'item n'expose pas systématiquement d'asset `icon` : l'absence d'icône est
 * un cas normal, que l'appelant traite par un repli, pas une réponse invalide.
 */
final readonly class MediaSearchDocument
{
    private const ICON_ASSET = 'icon';

    public function __construct(
        public int $id,
        public ?string $iconUrl,
        public ?int $fileDataId,
    ) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        $id = $responsePayload->requiredInt('id');

        foreach ($responsePayload->objectList('assets') as $asset) {
            if ($asset->optionalString('key') !== self::ICON_ASSET) {
                continue;
            }

            $iconUrl = trim($asset->optionalString('value') ?? '');
            if ($iconUrl === '') {
                continue;
            }

            return new self($id, $iconUrl, $asset->optionalInt('file_data_id'));
        }

        return new self($id, null, null);
    }
}
