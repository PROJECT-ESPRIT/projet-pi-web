<?php

namespace App\Entity;

use App\Repository\DonationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DonationRepository::class)]
class Donation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateDon = null;

    #[ORM\ManyToOne(inversedBy: 'donations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TypeDon $type = null;

    #[ORM\ManyToOne(inversedBy: 'donations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $donateur = null;

    public function __construct()
    {
        $this->dateDon = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDateDon(): ?\DateTimeImmutable
    {
        return $this->dateDon;
    }

    public function setDateDon(\DateTimeImmutable $dateDon): static
    {
        $this->dateDon = $dateDon;

        return $this;
    }

    public function getType(): ?TypeDon
    {
        return $this->type;
    }

    public function setType(?TypeDon $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getDonateur(): ?User
    {
        return $this->donateur;
    }

    public function setDonateur(?User $donateur): static
    {
        $this->donateur = $donateur;

        return $this;
    }
}
