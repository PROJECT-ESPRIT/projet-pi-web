<?php

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
class Reservation
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_CONFIRMED = 'CONFIRMED';
    public const STATUS_CANCELLED = 'CANCELLED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateReservation = null;

    #[ORM\Column(length: 50)]
    private ?string $status = null;

    #[ORM\ManyToOne(inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $participant = null;

    #[ORM\ManyToOne(inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Evenement $evenement = null;

<<<<<<< HEAD
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $seatLabel = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeCheckoutSessionId = null;

    /** Amount paid in smallest currency unit (e.g. centimes for TND). */
    #[ORM\Column(nullable: true)]
    private ?int $amountPaid = null;

    /** When the ticket was scanned at entrance (null = not scanned). */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $scannedAt = null;
=======
    #[ORM\Column]
    private int $quantite = 1;

    #[ORM\Column]
    private float $prixUnitaire = 0.0;

    #[ORM\Column]
    private float $remiseRate = 0.0;

    #[ORM\Column]
    private float $montantTotal = 0.0;
>>>>>>> c4d1c44b0746a7387dc28bd3111400a167bda2d9

    public function __construct()
    {
        $this->dateReservation = new \DateTimeImmutable();
        $this->status = self::STATUS_CONFIRMED;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateReservation(): ?\DateTimeImmutable
    {
        return $this->dateReservation;
    }

    public function setDateReservation(\DateTimeImmutable $dateReservation): static
    {
        $this->dateReservation = $dateReservation;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getParticipant(): ?User
    {
        return $this->participant;
    }

    public function setParticipant(?User $participant): static
    {
        $this->participant = $participant;

        return $this;
    }

    public function getEvenement(): ?Evenement
    {
        return $this->evenement;
    }

    public function setEvenement(?Evenement $evenement): static
    {
        $this->evenement = $evenement;

        return $this;
    }

<<<<<<< HEAD
    public function getSeatLabel(): ?string
    {
        return $this->seatLabel;
    }

    public function setSeatLabel(?string $seatLabel): static
    {
        $this->seatLabel = $seatLabel;
        return $this;
    }

    public function getStripeCheckoutSessionId(): ?string
    {
        return $this->stripeCheckoutSessionId;
    }

    public function setStripeCheckoutSessionId(?string $stripeCheckoutSessionId): static
    {
        $this->stripeCheckoutSessionId = $stripeCheckoutSessionId;
        return $this;
    }

    public function getAmountPaid(): ?int
    {
        return $this->amountPaid;
    }

    public function setAmountPaid(?int $amountPaid): static
    {
        $this->amountPaid = $amountPaid;
        return $this;
    }

    public function getScannedAt(): ?\DateTimeImmutable
    {
        return $this->scannedAt;
    }

    public function setScannedAt(?\DateTimeImmutable $scannedAt): static
    {
        $this->scannedAt = $scannedAt;
=======
    public function getQuantite(): int
    {
        return $this->quantite;
    }

    public function setQuantite(int $quantite): static
    {
        $this->quantite = max(1, $quantite);

        return $this;
    }

    public function getPrixUnitaire(): float
    {
        return $this->prixUnitaire;
    }

    public function setPrixUnitaire(float $prixUnitaire): static
    {
        $this->prixUnitaire = max(0.0, $prixUnitaire);

        return $this;
    }

    public function getRemiseRate(): float
    {
        return $this->remiseRate;
    }

    public function setRemiseRate(float $remiseRate): static
    {
        $this->remiseRate = max(0.0, min(1.0, $remiseRate));

        return $this;
    }

    public function getMontantTotal(): float
    {
        return $this->montantTotal;
    }

    public function setMontantTotal(float $montantTotal): static
    {
        $this->montantTotal = max(0.0, $montantTotal);

>>>>>>> c4d1c44b0746a7387dc28bd3111400a167bda2d9
        return $this;
    }
}
