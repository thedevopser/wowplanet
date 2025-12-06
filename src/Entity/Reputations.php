<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ReputationsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReputationsRepository::class)]
class Reputations
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $nameEN;

    #[ORM\Column(length: 255)]
    private string $nameFR;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct(string $nameEN, string $nameFR)
    {
        $this->nameEN = $nameEN;
        $this->nameFR = $nameFR;
        $this->createdAt = $this->getDateNow();
    }

    public function update(?string $nameEN, ?string $nameFR): void
    {
        if ($nameEN !== null) {
            $this->nameEN = $nameEN;
        }

        if ($nameFR !== null) {
            $this->nameFR = $nameFR;
        }

        $this->updatedAt = $this->getDateNow();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNameEN(): ?string
    {
        return $this->nameEN;
    }


    public function getNameFR(): ?string
    {
        return $this->nameFR;
    }


    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }


    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function getDateNow(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
