<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Pvp;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * Le résumé PvP d'un personnage, `…/pvp-summary` : honneur, champs de bataille non cotés, et
 * brackets joués. Les brackets varient par personnage, la liste est donc lue dans la réponse.
 */
final readonly class PvpSummaryResponse
{
    private const string BRACKET_LINK = '#/pvp-bracket/([^?/]+)#';

    /**
     * @param  list<PvpMatchStatistics>  $battlegroundStatistics
     * @param  list<string>  $bracketSlugs
     */
    public function __construct(
        public bool $isEmpty,
        public int $honorLevel,
        public int $honorableKills,
        public array $battlegroundStatistics,
        public array $bracketSlugs,
    ) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        return new self(
            isEmpty: $responsePayload->isEmpty(),
            honorLevel: $responsePayload->lenientInt('honor_level') ?? 0,
            honorableKills: $responsePayload->lenientInt('honorable_kills') ?? 0,
            battlegroundStatistics: array_map(
                static fn (ResponsePayload $responsePayload): PvpMatchStatistics => PvpMatchStatistics::fromPayload($responsePayload->optionalObject('match_statistics')),
                $responsePayload->objectList('pvp_map_statistics'),
            ),
            bracketSlugs: self::bracketSlugs($responsePayload),
        );
    }

    /**
     * @return list<string>
     */
    private static function bracketSlugs(ResponsePayload $responsePayload): array
    {
        $slugs = [];
        foreach ($responsePayload->objectList('brackets') as $bracket) {
            $href = $bracket->optionalString('href') ?? '';
            if (preg_match(self::BRACKET_LINK, $href, $matches) === 1) {
                $slugs[] = $matches[1];
            }
        }

        return array_values(array_unique($slugs));
    }
}
