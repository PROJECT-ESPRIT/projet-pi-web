<?php

namespace App\Entity;

use App\Repository\ForumReponseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ForumReponseRepository::class)]
class ForumReponse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2)]
    private ?string $contenu = null;

    #[ORM\Column]
    #[Assert\NotNull]
    #[Assert\Type(type: \DateTimeImmutable::class)]
    private ?\DateTimeImmutable $dateReponse = null;

    #[ORM\ManyToOne(inversedBy: 'reponses')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Forum $forum = null;

    #[ORM\ManyToOne(inversedBy: 'forumReponses')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?User $auteur = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContenu(): ?string
    {
        return $this->contenu;
    }

    public function setContenu(string $contenu): static
    {
        $this->contenu = $contenu;

        return $this;
    }

    public function getDateReponse(): ?\DateTimeImmutable
    {
        return $this->dateReponse;
    }

    public function setDateReponse(\DateTimeImmutable $dateReponse): static
    {
        $this->dateReponse = $dateReponse;

        return $this;
    }

    public function getForum(): ?Forum
    {
        return $this->forum;
    }

    public function setForum(?Forum $forum): static
    {
        $this->forum = $forum;

        return $this;
    }

    public function getAuteur(): ?User
    {
        return $this->auteur;
    }

    public function setAuteur(?User $auteur): static
    {
        $this->auteur = $auteur;

        return $this;
    }
}
