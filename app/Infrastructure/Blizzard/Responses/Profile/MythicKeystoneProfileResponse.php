<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Profile;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * Le profil Mythique+ d'un personnage, `…/mythic-keystone-profile` : la semaine en cours, avec sa
 * meilleure clé par donjon. Sans clé dans la semaine, Blizzard omet la liste.
 */
final readonly class MythicKeystoneProfileResponse
{
    /**
     * @param  list<MythicRun>  $currentPeriodRuns
     */
    public function __construct(
        public ?int $periodId,
        public array $currentPeriodRuns,
    ) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        $currentPeriod = $responsePayload->optionalObject('current_period');

        return new self(
            periodId: $currentPeriod?->optionalObject('period')?->optionalInt('id'),
            currentPeriodRuns: array_map(MythicRun::fromPayload(...), $currentPeriod?->objectList('best_runs') ?? []),
        );
    }
}
