<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses;

/**
 * Connected realms of a region, `connected-realm/index`. The index only carries links: the
 * identifier is read from the link.
 */
final readonly class ConnectedRealmIndexResponse
{
    private const CONNECTED_REALM_LINK = '~/connected-realm/(\d+)~';

    public function __construct(public ?int $firstConnectedRealmId) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        foreach ($responsePayload->objectList('connected_realms') as $connectedRealm) {
            if (preg_match(self::CONNECTED_REALM_LINK, $connectedRealm->optionalString('href') ?? '', $matches) === 1) {
                return new self((int) $matches[1]);
            }
        }

        return new self(null);
    }
}
