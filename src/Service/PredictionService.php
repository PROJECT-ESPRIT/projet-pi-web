<?php
// src/Service/PredictionService.php

namespace App\Service;

use App\Repository\LigneCommandeRepository;
use App\Entity\Produit;

class PredictionService
{
    private LigneCommandeRepository $ligneCommandeRepository;

    public function __construct(LigneCommandeRepository $ligneCommandeRepository)
    {
        $this->ligneCommandeRepository = $ligneCommandeRepository;
    }

    /**
     * Retourne l'historique mensuel (Jan → Déc)
     */
    public function getMonthlyHistory(Produit $produit): array
    {
        $ventes = $this->ligneCommandeRepository
            ->findQuantitesByProduit($produit->getId());

        // Tableau 12 mois index 0 → 11
        $history = array_fill(0, 12, 0);

        foreach ($ventes as $v) {

            // Si SQL natif → date_commande est une string
            $month = (int) date('n', strtotime($v['date_commande'])) - 1;

            $history[$month] += (int) $v['quantite'];
        }

        return $history;
    }

    /**
     * Prédiction intelligente pour un mois donné
     */
    public function predictForMonth(Produit $produit, int $mois): int
    {
        $history = $this->getMonthlyHistory($produit);

        $total = array_sum($history);

        if ($total === 0) {
            return 0;
        }

        // Moyenne annuelle
        $moyenne = $total / 12;

        // Facteur saisonnier basé sur historique du mois choisi
        $moisIndex = $mois - 1;

        $seasonFactor = $history[$moisIndex] > 0
            ? $history[$moisIndex] / max($moyenne, 1)
            : 1;

        $prediction = $moyenne * $seasonFactor;

        return (int) round($prediction);
    }
}