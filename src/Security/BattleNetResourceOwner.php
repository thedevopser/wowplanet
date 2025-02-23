<?php

namespace App\Security;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;

class BattleNetResourceOwner implements ResourceOwnerInterface
{

    public function __construct(private readonly array $response)
    {
    }

    public function getId(): string
    {
        return $this->response['sub'] ?? $this->response['id'] ?? '';
    }

    public function getBattletag(): ?string
    {
        return $this->response['battletag'] ?? null;
    }

    /**
     * Retourne le battletag sans le discriminant (#1234)
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
     * Retourne uniquement le discriminant du battletag
     */
    public function getBattletagDiscriminator(): ?string
    {
        $battletag = $this->getBattletag();
        if (!$battletag) {
            return null;
        }

        return explode('#', $battletag)[1] ?? null;
    }

    public function toArray(): array
    {
        return $this->response;
    }
}