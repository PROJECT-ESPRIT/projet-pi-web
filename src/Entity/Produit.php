<?php

namespace App\Entity;

use App\Repository\ProduitRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProduitRepository::class)]
class Produit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // ================= NOM =================

    #[Assert\NotBlank(message: "Le nom du produit est obligatoire.")]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: "Le nom doit contenir au moins {{ limit }} caractères.",
        maxMessage: "Le nom ne doit pas dépasser {{ limit }} caractères."
    )]
    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    // ================= DESCRIPTION =================

    #[Assert\Length(
        max: 2000,
        maxMessage: "La description ne doit pas dépasser {{ limit }} caractères."
    )]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    // ================= PRIX =================

    #[Assert\NotBlank(message: "Le prix est obligatoire.")]
    #[Assert\Type(
        type: 'numeric',
        message: "Le prix doit être un nombre valide."
    )]
    #[Assert\Positive(message: "Le prix doit être supérieur à 0.")]
    #[ORM\Column]
    private ?float $prix = null;

    // ================= STOCK =================

    #[Assert\NotBlank(message: "Le stock est obligatoire.")]
    #[Assert\Type(
        type: 'integer',
        message: "Le stock doit être un nombre entier."
    )]
    #[Assert\PositiveOrZero(message: "Le stock doit être positif ou égal à 0.")]
    #[ORM\Column]
    private ?int $stock = null;

    // ================= IMAGE =================

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    // ====================================================
    // ================= GETTERS & SETTERS =================
    // ====================================================

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
        $this->nom = trim($nom);
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description ? trim($description) : null;
        return $this;
    }

    public function getPrix(): ?float
    {
        return $this->prix;
    }

    public function setPrix(float $prix): static
    {
        $this->prix = $prix;
        return $this;
    }

    public function getStock(): ?int
    {
        return $this->stock;
    }

    public function setStock(int $stock): static
    {
        $this->stock = $stock;
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;
        return $this;
    }

    // ====================================================
    // 🔹 MÉTHODE BONUS (facultative)
    // Retourne chemin complet image
    // ====================================================

    public function getImagePath(): ?string
    {
        return $this->image
            ? '/uploads/images/' . $this->image
            : null;
    }
}
