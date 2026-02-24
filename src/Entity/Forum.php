<?php

namespace App\Entity;

use App\Repository\ForumRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\ForumPostScore;
use App\Entity\ForumDislike;

#[ORM\Entity(repositoryClass: ForumRepository::class)]
class Forum
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $prenom = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private ?string $email = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $sujet = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 10)]
    private ?string $message = null;

    #[ORM\Column]
    #[Assert\NotNull]
    #[Assert\Type(type: \DateTimeImmutable::class)]
    private ?\DateTimeImmutable $dateCreation = null;

    /**
     * @var Collection<int, ForumReponse>
     */
    #[ORM\OneToMany(targetEntity: ForumReponse::class, mappedBy: 'forum', orphanRemoval: true)]
    private Collection $reponses;

    /**
     * @var Collection<int, ForumLike>
     */
    #[ORM\OneToMany(targetEntity: ForumLike::class, mappedBy: 'forum', orphanRemoval: true)]
    private Collection $likes;

    /**
     * @var Collection<int, ForumDislike>
     */
    #[ORM\OneToMany(targetEntity: ForumDislike::class, mappedBy: 'forum', orphanRemoval: true)]
    private Collection $dislikes;

    /**
     * @var Collection<int, ForumSignalement>
     */
    #[ORM\OneToMany(targetEntity: ForumSignalement::class, mappedBy: 'forum', orphanRemoval: true)]
    private Collection $signalements;

    /**
     * @var ForumPostScore|null
     */
    #[ORM\OneToOne(targetEntity: ForumPostScore::class, mappedBy: 'forum', cascade: ['persist', 'remove'])]
    private ?ForumPostScore $score = null;

    public function __construct()
    {
        $this->reponses = new ArrayCollection();
        $this->likes = new ArrayCollection();
        $this->dislikes = new ArrayCollection();
        $this->signalements = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getSujet(): ?string
    {
        return $this->sujet;
    }

    public function setSujet(string $sujet): static
    {
        $this->sujet = $sujet;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
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
            // set the owning side to null (unless already changed)
            if ($reponse->getForum() === $this) {
                $reponse->setForum(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ForumLike>
     */
    public function getLikes(): Collection
    {
        return $this->likes;
    }

    public function addLike(ForumLike $like): static
    {
        if (!$this->likes->contains($like)) {
            $this->likes->add($like);
            $like->setForum($this);
        }

        return $this;
    }

    public function removeLike(ForumLike $like): static
    {
        if ($this->likes->removeElement($like)) {
            // set the owning side to null (unless already changed)
            if ($like->getForum() === $this) {
                $like->setForum(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ForumDislike>
     */
    public function getDislikes(): Collection
    {
        return $this->dislikes;
    }

    public function addDislike(ForumDislike $dislike): static
    {
        if (!$this->dislikes->contains($dislike)) {
            $this->dislikes->add($dislike);
            $dislike->setForum($this);
        }

        return $this;
    }

    public function removeDislike(ForumDislike $dislike): static
    {
        if ($this->dislikes->removeElement($dislike)) {
            // set the owning side to null (unless already changed)
            if ($dislike->getForum() === $this) {
                $dislike->setForum(null);
            }
        }

        return $this;
    }

    public function getDislikesCount(): int
    {
        return $this->dislikes->count();
    }

    /**
     * @return Collection<int, ForumSignalement>
     */
    public function getSignalements(): Collection
    {
        return $this->signalements;
    }

    public function addSignalement(ForumSignalement $signalement): static
    {
        if (!$this->signalements->contains($signalement)) {
            $this->signalements->add($signalement);
            $signalement->setForum($this);
        }

        return $this;
    }

    public function removeSignalement(ForumSignalement $signalement): static
    {
        if ($this->signalements->removeElement($signalement)) {
            // set the owning side to null (unless already changed)
            if ($signalement->getForum() === $this) {
                $signalement->setForum(null);
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

    public function getScore(): ?ForumPostScore
    {
        return $this->score;
    }

    public function setScore(?ForumPostScore $score): static
    {
        // unset the owning side of the relation if necessary
        if ($score === null && $this->score !== null) {
            $this->score->setForum(null);
        }

        // set the owning side of the relation if necessary
        if ($score !== null && $score->getForum() !== $this) {
            $score->setForum($this);
        }

        $this->score = $score;

        return $this;
    }
}
