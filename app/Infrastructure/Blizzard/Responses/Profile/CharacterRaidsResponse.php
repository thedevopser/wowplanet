<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Profile;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * La progression en raid, `…/encounters/raids`, rangée par extension.
 */
final readonly class CharacterRaidsResponse
{
    /**
     * @param  array<int, list<RaidInstance>>  $instancesByExpansion
     */
    public function __construct(private array $instancesByExpansion) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        $instancesByExpansion = [];
        foreach ($responsePayload->objectList('expansions') as $expansion) {
            $expansionId = $expansion->optionalObject('expansion')?->optionalInt('id') ?? 0;
            if (isset($instancesByExpansion[$expansionId])) {
                continue;
            }

            $instancesByExpansion[$expansionId] = array_map(RaidInstance::fromPayload(...), $expansion->objectList('instances'));
        }

        return new self($instancesByExpansion);
    }

    /**
     * Nul quand le personnage n'a rien fait dans l'extension, qui est alors absente de la réponse.
     *
     * @return list<RaidInstance>|null
     */
    public function instancesOf(int $expansionId): ?array
    {
        return $this->instancesByExpansion[$expansionId] ?? null;
    }

    /**
     * @return list<int>
     */
    public function instanceIdsOf(int $expansionId): array
    {
        $instanceIds = [];
        foreach ($this->instancesOf($expansionId) ?? [] as $raidInstance) {
            if (($raidInstance->id ?? 0) > 0) {
                $instanceIds[] = $raidInstance->id;
            }
        }

        return $instanceIds;
    }
}
