<?php
// src/Controller/PredictionController.php

namespace App\Controller;

use App\Entity\Produit;
use App\Service\PredictionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PredictionController extends AbstractController
{
    #[Route('/produit/{id}/prediction', name: 'app_prediction_produit')]
    public function produitPrediction(
        Produit $produit,
        Request $request,
        PredictionService $predictionService
    ): Response {

        // Mois sélectionné (par défaut mois actuel)
        $mois = (int) $request->query->get('mois', date('n'));

        // Historique mensuel complet (Jan → Déc)
        $historyData = $predictionService->getMonthlyHistory($produit);

        // Prédiction pour le mois sélectionné
        $prediction = $predictionService->predictForMonth($produit, $mois);

        return $this->render('prediction/produit_prediction.html.twig', [
            'produit' => $produit,
            'mois' => $mois,
            'prediction' => $prediction,
            'totalVentes' => array_sum($historyData),
            'historyData' => $historyData,
        ]);
    }
}