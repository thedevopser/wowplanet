<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Talent;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * Les spécialisations d'un personnage, `…/specializations`, réduites à la spécialisation active
 * et à son loadout actif.
 */
final readonly class CharacterSpecializationsResponse
{
    public function __construct(
        public ?int $activeSpecializationId,
        public ?TalentLoadout $activeLoadout,
    ) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        $activeSpecializationId = $responsePayload->optionalObject('active_specialization')?->optionalInt('id');

        return new self($activeSpecializationId, self::findActiveLoadout($responsePayload, $activeSpecializationId ?? 0));
    }

    /**
     * Une spécialisation peut apparaître plusieurs fois : la recherche continue tant qu'aucun
     * loadout actif n'est trouvé.
     */
    private static function findActiveLoadout(ResponsePayload $responsePayload, int $activeSpecializationId): ?TalentLoadout
    {
        foreach ($responsePayload->objectList('specializations') as $specialization) {
            $specializationId = $specialization->optionalObject('specialization')?->optionalInt('id') ?? 0;
            if ($specializationId !== $activeSpecializationId) {
                continue;
            }

            foreach ($specialization->objectList('loadouts') as $loadout) {
                if ($loadout->optionalBool('is_active') === true) {
                    return TalentLoadout::fromPayload($loadout);
                }
            }
        }

        return null;
    }
}
