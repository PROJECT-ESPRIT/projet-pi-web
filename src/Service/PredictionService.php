<?php
// src/Service/PredictionService.php

namespace App\Service;

use App\Repository\LigneCommandeRepository;
use App\Entity\Produit;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class PredictionService
{
    private LigneCommandeRepository $ligneCommandeRepository;

    public function __construct(LigneCommandeRepository $ligneCommandeRepository)
    {
        $this->ligneCommandeRepository = $ligneCommandeRepository;
    }

    /**
     * Retourne l'historique mensuel (Jan → Déc) pour un produit
     */
    public function getMonthlyHistory(Produit $produit): array
    {
        $ventes = $this->ligneCommandeRepository->findQuantitesByProduit($produit->getId());

        $history = array_fill(0, 12, 0);

        foreach ($ventes as $v) {
            $month = (int) date('n', strtotime($v['date_commande'])) - 1;
            $history[$month] += (int) $v['quantite'];
        }

        return $history;
    }

    /**
     * Prédiction pour un mois donné via Python
     */
    public function predictForMonth(Produit $produit, int $mois): int
    {
        $history = $this->getMonthlyHistory($produit);

        // Chemin absolu vers le script Python
        $scriptPath = realpath(__DIR__ . '/../scripts/predict_sales.py');
        if (!$scriptPath) {
            throw new \RuntimeException("Le script Python predict_sales.py n'a pas été trouvé !");
        }

        // Conversion en JSON pour passer en argument
        $historyJson = json_encode($history);

        // Exécution du script Python
        $process = new Process(['python', $scriptPath, $historyJson, (string)$mois]);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        // Récupère la sortie JSON du script Python
        $result = json_decode($process->getOutput(), true);

        // Retourne la prédiction ou 0 si problème
        return $result['prediction'] ?? 0;
    }
}