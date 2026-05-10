<?php

namespace App\Entity;

use App\Repository\FavoriteCharityRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FavoriteCharityRepository::class)]
#[ORM\Table(name: 'favorite_charity')]
class FavoriteCharity
{
    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'charity_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Charity $charity = null;

    #[ORM\Column(name: 'created_at')]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct(?User $user = null, ?Charity $charity = null)
    {
        $this->user = $user;
        $this->charity = $charity;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getCharity(): ?Charity
    {
        return $this->charity;
    }

    public function setCharity(?Charity $charity): static
    {
        $this->charity = $charity;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
