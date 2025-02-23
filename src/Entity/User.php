<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_USERNAME', fields: ['username'])]
class User implements UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $username = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $battlenetId = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $googleId = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $discordId = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $avatar = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getBattleNetId(): ?string
    {
        return $this->battlenetId;
    }

    public function setBattleNetId(?string $battlenetId): void
    {
        $this->battlenetId = $battlenetId;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return !empty($this->username) ? $this->username : 'default_identifier';
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getGoogleId(): ?string
    {
        return $this->googleId;
    }

    public function setGoogleId(?string $googleId): void
    {
        $this->googleId = $googleId;
    }

    public function getDiscordId(): ?string
    {
        return $this->discordId;
    }

    public function setDiscordId(?string $discordId): void
    {
        $this->discordId = $discordId;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): void
    {
        $this->avatar = $avatar;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }
}
