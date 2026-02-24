<?php

namespace App\Entity;

use App\Repository\ForumReponseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    /**
     * @var Collection<int, ForumReponseLike>
     */
    #[ORM\OneToMany(targetEntity: ForumReponseLike::class, mappedBy: 'reponse', orphanRemoval: true)]
    private Collection $likes;

    /**
     * @var Collection<int, ForumReponseSignalement>
     */
    #[ORM\OneToMany(targetEntity: ForumReponseSignalement::class, mappedBy: 'reponse', orphanRemoval: true)]
    private Collection $signalements;

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

    public function __construct()
    {
        $this->likes = new ArrayCollection();
        $this->signalements = new ArrayCollection();
    }

    /**
     * @return Collection<int, ForumReponseLike>
     */
    public function getLikes(): Collection
    {
        return $this->likes;
    }

    public function addLike(ForumReponseLike $like): static
    {
        if (!$this->likes->contains($like)) {
            $this->likes->add($like);
            $like->setReponse($this);
        }

        return $this;
    }

    public function removeLike(ForumReponseLike $like): static
    {
        if ($this->likes->removeElement($like)) {
            // set the owning side to null (unless already changed)
            if ($like->getReponse() === $this) {
                $like->setReponse(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ForumReponseSignalement>
     */
    public function getSignalements(): Collection
    {
        return $this->signalements;
    }

    public function addSignalement(ForumReponseSignalement $signalement): static
    {
        if (!$this->signalements->contains($signalement)) {
            $this->signalements->add($signalement);
            $signalement->setReponse($this);
        }

        return $this;
    }

    public function removeSignalement(ForumReponseSignalement $signalement): static
    {
        if ($this->signalements->removeElement($signalement)) {
            // set the owning side to null (unless already changed)
            if ($signalement->getReponse() === $this) {
                $signalement->setReponse(null);
            }
        }

        return $this;
    }

    public function getLikesCount(): int
    {
        return $this->likes->count();
    }

    public function getSignalementsCount(): int
    {
        return $this->signalements->count();
    }
}
