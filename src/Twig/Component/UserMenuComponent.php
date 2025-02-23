<?php

namespace App\Twig\Component;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('user_menu')]
final class UserMenuComponent
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public bool $isOpen = false;

    private ?object $user = null;

    public function __construct(
        private Security $security
    ) {
        $this->user = $this->security->getUser();
    }

    public function getUser(): ?object
    {
        return $this->user;
    }

    #[LiveAction]
    public function toggleMenu(): void
    {
        $this->isOpen = !$this->isOpen;
    }
}
