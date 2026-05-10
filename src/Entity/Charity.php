<?php

namespace App\Entity;

use App\Repository\CharityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CharityRepository::class)]
class Charity
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_HIDDEN = 'HIDDEN';
    public const STATUS_REJECTED = 'REJECTED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'title', length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'target_amount', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private ?string $goalAmount = '0.00';

    #[ORM\Column(name: 'collected_amount', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private ?string $collectedAmount = '0.00';

    #[ORM\Column(name: 'image_url', length: 512, nullable: true)]
    private ?string $imagePath = null;

    #[ORM\Column(length: 32, options: ['default' => self::STATUS_PENDING])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(name: 'rejection_reason', length: 512, nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'charities')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $owner = null;

    #[ORM\OneToMany(mappedBy: 'charity', targetEntity: Donation::class)]
    private Collection $donations;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->donations = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
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

    public function getGoalAmount(): ?int
    {
        return $this->goalAmount !== null ? (int) $this->goalAmount : null;
    }

    public function setGoalAmount(int|float|null $goalAmount): static
    {
        $this->goalAmount = $goalAmount !== null
            ? number_format(max(1, (float) $goalAmount), 2, '.', '')
            : null;
        return $this;
    }

    public function getTargetAmount(): ?string
    {
        return $this->goalAmount;
    }

    public function getCollectedAmount(): ?string
    {
        return $this->collectedAmount;
    }

    public function setCollectedAmount(int|float|string|null $collectedAmount): static
    {
        $this->collectedAmount = $collectedAmount === null
            ? null
            : number_format((float) $collectedAmount, 2, '.', '');
        return $this;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(?string $imagePath): static
    {
        $this->imagePath = $imagePath;

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

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $reason): static
    {
        $this->rejectionReason = $reason;
        return $this;
    }

    public function isHidden(): bool
    {
        return $this->status === self::STATUS_HIDDEN;
    }

    public function setIsHidden(bool $isHidden): static
    {
        if ($isHidden) {
            $this->status = self::STATUS_HIDDEN;
        } elseif ($this->status === self::STATUS_HIDDEN) {
            $this->status = self::STATUS_APPROVED;
        }
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

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

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
            $donation->setCharity($this);
        }

        return $this;
    }

    public function removeDonation(Donation $donation): static
    {
        if ($this->donations->removeElement($donation)) {
            if ($donation->getCharity() === $this) {
                $donation->setCharity(null);
            }
        }

        return $this;
    }
}
