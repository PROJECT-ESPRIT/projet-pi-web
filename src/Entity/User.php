<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const STATUS_EMAIL_PENDING = 'EMAIL_PENDING';
    public const STATUS_EMAIL_VERIFIED = 'EMAIL_VERIFIED';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_SUSPENDED = 'SUSPENDED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    private ?string $prenom = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 20, options: ['default' => self::STATUS_EMAIL_PENDING])]
    private string $status = self::STATUS_EMAIL_PENDING;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $emailVerificationToken = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $emailVerificationSentAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateNaissance = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $profileImageUrl = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->evenements = new ArrayCollection();
        $this->reservations = new ArrayCollection();
        $this->donations = new ArrayCollection();
        $this->commandes = new ArrayCollection();
        $this->forumReponses = new ArrayCollection();
        $this->forumLikes = new ArrayCollection();
        $this->forumDislikes = new ArrayCollection();
        $this->forumSignalements = new ArrayCollection();
        $this->forumReponseLikes = new ArrayCollection();
        $this->forumReponseSignalements = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
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

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
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

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getDateNaissance(): ?\DateTimeImmutable
    {
        return $this->dateNaissance;
    }

    public function setDateNaissance(?\DateTimeImmutable $dateNaissance): static
    {
        $this->dateNaissance = $dateNaissance;

        return $this;
    }

    public function getProfileImageUrl(): ?string
    {
        return $this->profileImageUrl;
    }

    public function setProfileImageUrl(?string $profileImageUrl): static
    {
        $this->profileImageUrl = $profileImageUrl;

        return $this;
    }

    /**
     * Returns the user's age in years (today's date minus birth date), or null if date of birth is not set.
     */
    public function getAge(): ?int
    {
        if ($this->dateNaissance === null) {
            return null;
        }
        $today = new \DateTimeImmutable('today');
        return (int) $today->diff($this->dateNaissance)->y;
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getEmailVerificationToken(): ?string
    {
        return $this->emailVerificationToken;
    }

    public function setEmailVerificationToken(?string $token): static
    {
        $this->emailVerificationToken = $token;
        return $this;
    }

    public function getEmailVerificationSentAt(): ?\DateTimeImmutable
    {
        return $this->emailVerificationSentAt;
    }

    public function setEmailVerificationSentAt(?\DateTimeImmutable $sentAt): static
    {
        $this->emailVerificationSentAt = $sentAt;
        return $this;
    }

    public function generateEmailVerificationToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->emailVerificationToken = $token;
        $this->emailVerificationSentAt = new \DateTimeImmutable();
        return $token;
    }

    public function isEmailVerificationTokenExpired(int $ttlHours = 48): bool
    {
        if (!$this->emailVerificationSentAt) {
            return true;
        }
        return $this->emailVerificationSentAt->modify("+{$ttlHours} hours") < new \DateTimeImmutable();
    }

    public function getFullName(): string
    {
        return $this->prenom . ' ' . $this->nom;
    }

    #[ORM\OneToMany(mappedBy: 'organisateur', targetEntity: Evenement::class)]
    private Collection $evenements;

    #[ORM\OneToMany(mappedBy: 'participant', targetEntity: Reservation::class)]
    private Collection $reservations;

    #[ORM\OneToMany(mappedBy: 'donateur', targetEntity: Donation::class)]
    private Collection $donations;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Commande::class)]
    private Collection $commandes;

    /**
     * @var Collection<int, ForumReponse>
     */
    #[ORM\OneToMany(targetEntity: ForumReponse::class, mappedBy: 'auteur')]
    private Collection $forumReponses;

    /**
     * @var Collection<int, ForumLike>
     */
    #[ORM\OneToMany(targetEntity: ForumLike::class, mappedBy: 'user')]
    private Collection $forumLikes;

    /**
     * @var Collection<int, ForumDislike>
     */
    #[ORM\OneToMany(targetEntity: ForumDislike::class, mappedBy: 'user')]
    private Collection $forumDislikes;

    /**
     * @var Collection<int, ForumSignalement>
     */
    #[ORM\OneToMany(targetEntity: ForumSignalement::class, mappedBy: 'user')]
    private Collection $forumSignalements;

    /**
     * @var Collection<int, ForumReponseLike>
     */
    #[ORM\OneToMany(targetEntity: ForumReponseLike::class, mappedBy: 'user')]
    private Collection $forumReponseLikes;

    /**
     * @var Collection<int, ForumReponseSignalement>
     */
    #[ORM\OneToMany(targetEntity: ForumReponseSignalement::class, mappedBy: 'user')]
    private Collection $forumReponseSignalements;

    /**
     * @return Collection<int, Evenement>
     */
    public function getEvenements(): Collection
    {
        return $this->evenements;
    }

    public function addEvenement(Evenement $evenement): static
    {
        if (!$this->evenements->contains($evenement)) {
            $this->evenements->add($evenement);
            $evenement->setOrganisateur($this);
        }

        return $this;
    }

    public function removeEvenement(Evenement $evenement): static
    {
        if ($this->evenements->removeElement($evenement)) {
            // set the owning side to null (unless already changed)
            if ($evenement->getOrganisateur() === $this) {
                $evenement->setOrganisateur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Reservation>
     */
    public function getReservations(): Collection
    {
        return $this->reservations;
    }

    public function addReservation(Reservation $reservation): static
    {
        if (!$this->reservations->contains($reservation)) {
            $this->reservations->add($reservation);
            $reservation->setParticipant($this);
        }

        return $this;
    }

    public function removeReservation(Reservation $reservation): static
    {
        if ($this->reservations->removeElement($reservation)) {
            // set the owning side to null (unless already changed)
            if ($reservation->getParticipant() === $this) {
                $reservation->setParticipant(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Donation>
     */
    public function getDonations(): Collection
    {
        return $this->donations;
    }

    public function addDonation(Donation $donation): static
    {
        if (!$this->donations->contains($donation)) {
            $this->donations->add($donation);
            $donation->setDonateur($this);
        }

        return $this;
    }

    public function removeDonation(Donation $donation): static
    {
        if ($this->donations->removeElement($donation)) {
            // set the owning side to null (unless already changed)
            if ($donation->getDonateur() === $this) {
                $donation->setDonateur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Commande>
     */
    public function getCommandes(): Collection
    {
        return $this->commandes;
    }

    public function addCommande(Commande $commande): static
    {
        if (!$this->commandes->contains($commande)) {
            $this->commandes->add($commande);
            $commande->setUser($this);
        }

        return $this;
    }

    public function removeCommande(Commande $commande): static
    {
        if ($this->commandes->removeElement($commande)) {
            // set the owning side to null (unless already changed)
            if ($commande->getUser() === $this) {
                $commande->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ForumReponse>
     */
    public function getForumReponses(): Collection
    {
        return $this->forumReponses;
    }

    public function addForumReponse(ForumReponse $forumReponse): static
    {
        if (!$this->forumReponses->contains($forumReponse)) {
            $this->forumReponses->add($forumReponse);
            $forumReponse->setAuteur($this);
        }

        return $this;
    }

    public function removeForumReponse(ForumReponse $forumReponse): static
    {
        if ($this->forumReponses->removeElement($forumReponse)) {
            // set the owning side to null (unless already changed)
            if ($forumReponse->getAuteur() === $this) {
                $forumReponse->setAuteur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ForumLike>
     */
    public function getForumLikes(): Collection
    {
        return $this->forumLikes;
    }

    public function addForumLike(ForumLike $forumLike): static
    {
        if (!$this->forumLikes->contains($forumLike)) {
            $this->forumLikes->add($forumLike);
            $forumLike->setUser($this);
        }

        return $this;
    }

    public function removeForumLike(ForumLike $forumLike): static
    {
        if ($this->forumLikes->removeElement($forumLike)) {
            // set the owning side to null (unless already changed)
            if ($forumLike->getUser() === $this) {
                $forumLike->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ForumDislike>
     */
    public function getForumDislikes(): Collection
    {
        return $this->forumDislikes;
    }

    public function addForumDislike(ForumDislike $forumDislike): static
    {
        if (!$this->forumDislikes->contains($forumDislike)) {
            $this->forumDislikes->add($forumDislike);
            $forumDislike->setUser($this);
        }

        return $this;
    }

    public function removeForumDislike(ForumDislike $forumDislike): static
    {
        if ($this->forumDislikes->removeElement($forumDislike)) {
            // set the owning side to null (unless already changed)
            if ($forumDislike->getUser() === $this) {
                $forumDislike->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ForumSignalement>
     */
    public function getForumSignalements(): Collection
    {
        return $this->forumSignalements;
    }

    public function addForumSignalement(ForumSignalement $forumSignalement): static
    {
        if (!$this->forumSignalements->contains($forumSignalement)) {
            $this->forumSignalements->add($forumSignalement);
            $forumSignalement->setUser($this);
        }

        return $this;
    }

    public function removeForumSignalement(ForumSignalement $forumSignalement): static
    {
        if ($this->forumSignalements->removeElement($forumSignalement)) {
            // set the owning side to null (unless already changed)
            if ($forumSignalement->getUser() === $this) {
                $forumSignalement->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ForumReponseLike>
     */
    public function getForumReponseLikes(): Collection
    {
        return $this->forumReponseLikes;
    }

    public function addForumReponseLike(ForumReponseLike $forumReponseLike): static
    {
        if (!$this->forumReponseLikes->contains($forumReponseLike)) {
            $this->forumReponseLikes->add($forumReponseLike);
            $forumReponseLike->setUser($this);
        }

        return $this;
    }

    public function removeForumReponseLike(ForumReponseLike $forumReponseLike): static
    {
        if ($this->forumReponseLikes->removeElement($forumReponseLike)) {
            // set the owning side to null (unless already changed)
            if ($forumReponseLike->getUser() === $this) {
                $forumReponseLike->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ForumReponseSignalement>
     */
    public function getForumReponseSignalements(): Collection
    {
        return $this->forumReponseSignalements;
    }

    public function addForumReponseSignalement(ForumReponseSignalement $forumReponseSignalement): static
    {
        if (!$this->forumReponseSignalements->contains($forumReponseSignalement)) {
            $this->forumReponseSignalements->add($forumReponseSignalement);
            $forumReponseSignalement->setUser($this);
        }

        return $this;
    }

    public function removeForumReponseSignalement(ForumReponseSignalement $forumReponseSignalement): static
    {
        if ($this->forumReponseSignalements->removeElement($forumReponseSignalement)) {
            // set the owning side to null (unless already changed)
            if ($forumReponseSignalement->getUser() === $this) {
                $forumReponseSignalement->setUser(null);
            }
        }

        return $this;
    }
}
