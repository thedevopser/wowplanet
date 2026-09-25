<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Profile;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * Une instance du journal, `data/wow/journal-instance/{id}` : la seule source des noms français
 * d'un raid et de ses boss, que l'endpoint de progression du profil ne localise pas.
 */
final readonly class JournalInstanceResponse
{
    /**
     * @param  array<int, string>  $encounterNames
     */
    public function __construct(
        public ?string $name,
        public array $encounterNames,
    ) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        $encounterNames = [];
        foreach ($responsePayload->objectList('encounters') as $encounter) {
            $encounterId = $encounter->optionalInt('id') ?? 0;
            if ($encounterId > 0) {
                $encounterNames[$encounterId] = $encounter->optionalString('name') ?? '';
            }
        }

        return new self($responsePayload->optionalString('name'), $encounterNames);
    }
}
