<?php

declare(strict_types=1);

namespace App\Security;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;

class BattleNetResourceOwner implements ResourceOwnerInterface
{
    /**
     * @param array<string, mixed> $response
     */
    public function __construct(private readonly array $response)
    {
    }

    public function getId(): string
    {
        $id = $this->response['sub'] ?? $this->response['id'] ?? '';

        return is_string($id) ? $id : '';
    }

    public function getBattletag(): ?string
    {
        $battletag = $this->response['battletag'] ?? null;

        return is_string($battletag) ? $battletag : null;
    }

    /**
     * Retourne le battletag sans le discriminant (#1234).
     */
    public function getBattletagName(): ?string
    {
        $battletag = $this->getBattletag();
        if (!$battletag) {
            return null;
        }

        return explode('#', $battletag)[0] ?? null;
    }

    /**
     * Retourne uniquement le discriminant du battletag.
     */
    public function getBattletagDiscriminator(): ?string
    {
        $battletag = $this->getBattletag();
        if (!$battletag) {
            return null;
        }

        return explode('#', $battletag)[1] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->response;
    }
}
