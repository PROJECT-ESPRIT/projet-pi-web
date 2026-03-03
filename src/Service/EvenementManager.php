<?php

namespace App\Service;

use App\Entity\Evenement;

class EvenementManager
{
    /**
     * Valide les règles métier de l'entité Evenement.
     * Règles : date fin > date début ; nombre de places > 0.
     */
    public function validate(Evenement $evenement): bool
    {
        $dateDebut = $evenement->getDateDebut();
        $dateFin = $evenement->getDateFin();
        if ($dateDebut !== null && $dateFin !== null && $dateFin <= $dateDebut) {
            throw new \InvalidArgumentException('La date de fin doit être postérieure à la date de début');
        }
        $nbPlaces = $evenement->getNbPlaces();
        if ($nbPlaces !== null && $nbPlaces <= 0) {
            throw new \InvalidArgumentException('Le nombre de places doit être supérieur à zéro');
        }
        return true;
    }
}
