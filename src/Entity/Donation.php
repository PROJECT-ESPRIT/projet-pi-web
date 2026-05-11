<?php

namespace App\Entity;

use App\Repository\DonationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DonationRepository::class)]
class Donation
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_HIDDEN = 'HIDDEN';

    public const TYPE_MONEY = 'MONEY';
    public const TYPE_ITEM = 'ITEM';

    public const AI_NOT_VERIFIED = 'NOT_VERIFIED';
    public const AI_VERIFIED = 'VERIFIED';
    public const AI_REJECTED = 'REJECTED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'created_at')]
    private ?\DateTimeImmutable $dateDon = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $amount = null;

    #[ORM\Column(name: 'item_picture_path', length: 512, nullable: true)]
    private ?string $imagePath = null;

    #[ORM\Column(length: 32, options: ['default' => self::STATUS_PENDING])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(name: 'donation_type', length: 20, options: ['default' => self::TYPE_MONEY])]
    private string $donationType = self::TYPE_MONEY;

    #[ORM\Column(name: 'ai_verification_status', length: 32, nullable: true, options: ['default' => self::AI_NOT_VERIFIED])]
    private ?string $aiVerificationStatus = self::AI_NOT_VERIFIED;

    #[ORM\Column(name: 'ai_verification_message', type: Types::TEXT, nullable: true)]
    private ?string $aiVerificationMessage = null;

    #[ORM\Column(name: 'donor_name', length: 255, nullable: true)]
    private ?string $donorName = null;

    #[ORM\Column(name: 'donor_email', length: 255, nullable: true)]
    private ?string $donorEmail = null;

    #[ORM\ManyToOne(inversedBy: 'donations')]
    #[ORM\JoinColumn(name: 'item_type_id', nullable: true)]
    private ?TypeDon $type = null;

    #[ORM\ManyToOne(inversedBy: 'donations')]
    #[ORM\JoinColumn(name: 'donor_user_id', nullable: true)]
    private ?User $donateur = null;

    #[ORM\ManyToOne(inversedBy: 'donations')]
    #[ORM\JoinColumn(name: 'charity_id', nullable: false)]
    private ?Charity $charity = null;

    #[ORM\Column(name: 'anonymous')]
    private bool $isAnonymous = false;

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

    public function getAmount(): int
    {
        return $this->amount !== null ? (int) $this->amount : 0;
    }

    public function setAmount(int|float|null $amount): static
    {
        $this->amount = $amount === null
            ? null
            : number_format(max(0, (float) $amount), 2, '.', '');
        return $this;
    }

    public function getAmountDecimal(): ?string
    {
        return $this->amount;
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

    public function getDonationType(): string
    {
        return $this->donationType;
    }

    public function setDonationType(string $donationType): static
    {
        $this->donationType = $donationType;
        return $this;
    }

    public function getAiVerificationStatus(): ?string
    {
        return $this->aiVerificationStatus;
    }

    public function setAiVerificationStatus(?string $status): static
    {
        $this->aiVerificationStatus = $status;
        return $this;
    }

    public function getAiVerificationMessage(): ?string
    {
        return $this->aiVerificationMessage;
    }

    public function setAiVerificationMessage(?string $message): static
    {
        $this->aiVerificationMessage = $message;
        return $this;
    }

    public function getDonorName(): ?string
    {
        return $this->donorName;
    }

    public function setDonorName(?string $donorName): static
    {
        $this->donorName = $donorName;
        return $this;
    }

    public function getDonorEmail(): ?string
    {
        return $this->donorEmail;
    }

    public function setDonorEmail(?string $donorEmail): static
    {
        $this->donorEmail = $donorEmail;
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

    public function getCharity(): ?Charity
    {
        return $this->charity;
    }

    public function setCharity(?Charity $charity): static
    {
        $this->charity = $charity;
        return $this;
    }

    public function isAnonymous(): bool
    {
        return $this->isAnonymous;
    }

    public function setIsAnonymous(bool $isAnonymous): static
    {
        $this->isAnonymous = $isAnonymous;
        return $this;
    }
}
