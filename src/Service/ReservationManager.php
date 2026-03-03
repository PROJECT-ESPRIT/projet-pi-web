<?php

namespace App\Service;

use App\Entity\Reservation;

class ReservationManager
{
    private const VALID_STATUSES = [
        Reservation::STATUS_PENDING,
        Reservation::STATUS_CONFIRMED,
        Reservation::STATUS_CANCELLED,
    ];

    /**
     * Valide les règles métier de l'entité Reservation.
     * Règles : statut valide (PENDING, CONFIRMED, CANCELLED) ; montant payé non négatif.
     */
    public function validate(Reservation $reservation): bool
    {
        $status = $reservation->getStatus();
        if ($status === null || !\in_array($status, self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException('Le statut doit être PENDING, CONFIRMED ou CANCELLED');
        }
        $amountPaid = $reservation->getAmountPaid();
        if ($amountPaid !== null && $amountPaid < 0) {
            throw new \InvalidArgumentException('Le montant payé ne peut pas être négatif');
        }
        return true;
    }
}
