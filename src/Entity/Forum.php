<?php

namespace App\Entity;

use App\Repository\ForumRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ForumRepository::class)]
#[ORM\Table(name: 'forum_topic')]
class Forum
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $titre = null;

    #[ORM\Column(length: 128, nullable: true)]
    #[Assert\Length(max: 128)]
    private ?string $auteur = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 5)]
    private ?string $message = null;

    #[ORM\Column(name: 'created_at')]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $dateCreation = null;

    /**
     * @var Collection<int, ForumReponse>
     */
    #[ORM\OneToMany(targetEntity: ForumReponse::class, mappedBy: 'forum', orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $reponses;

    public function __construct()
    {
        $this->reponses = new ArrayCollection();
        $this->dateCreation = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(?string $titre): static
    {
        $this->titre = $titre;
        return $this;
    }

    public function getAuteur(): ?string
    {
        return $this->auteur;
    }

    public function setAuteur(?string $auteur): static
    {
        $this->auteur = $auteur;
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function getDateCreation(): ?\DateTimeImmutable
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeImmutable $dateCreation): static
    {
        $this->dateCreation = $dateCreation;
        return $this;
    }

    /**
     * Backward-compat alias for templates that use 'sujet'.
     */
    public function getSujet(): ?string
    {
        return $this->titre;
    }

    public function setSujet(?string $sujet): static
    {
        $this->titre = $sujet;
        return $this;
    }

    /**
     * @return Collection<int, ForumReponse>
     */
    public function getReponses(): Collection
    {
        return $this->reponses;
    }

    public function addReponse(ForumReponse $reponse): static
    {
        if (!$this->reponses->contains($reponse)) {
            $this->reponses->add($reponse);
            $reponse->setForum($this);
        }
        return $this;
    }

    public function removeReponse(ForumReponse $reponse): static
    {
        if ($this->reponses->removeElement($reponse)) {
            if ($reponse->getForum() === $this) {
                $reponse->setForum(null);
            }
        }
        return $this;
    }
}
