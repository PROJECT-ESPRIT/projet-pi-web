<?php

namespace App\Tests\Service;

use App\Entity\Reservation;
use App\Entity\User;
use App\Entity\Evenement;
use App\Service\ReservationManager;
use PHPUnit\Framework\TestCase;

class ReservationManagerTest extends TestCase
{
    private function makeReservation(string $status, ?int $amountPaid = null): Reservation
    {
        $r = new Reservation();
        $r->setStatus($status);
        if ($amountPaid !== null) {
            $r->setAmountPaid($amountPaid);
        }
        return $r;
    }

    public function testValidReservationConfirmed(): void
    {
        $reservation = $this->makeReservation(Reservation::STATUS_CONFIRMED, 2500);
        $manager = new ReservationManager();
        $this->assertTrue($manager->validate($reservation));
    }

    public function testValidReservationPending(): void
    {
        $reservation = $this->makeReservation(Reservation::STATUS_PENDING);
        $manager = new ReservationManager();
        $this->assertTrue($manager->validate($reservation));
    }

    public function testValidReservationCancelled(): void
    {
        $reservation = $this->makeReservation(Reservation::STATUS_CANCELLED);
        $manager = new ReservationManager();
        $this->assertTrue($manager->validate($reservation));
    }

    public function testInvalidStatusThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le statut doit être PENDING, CONFIRMED ou CANCELLED');

        $reservation = $this->makeReservation('INVALID_STATUS');
        $manager = new ReservationManager();
        $manager->validate($reservation);
    }

    public function testAmountPaidCannotBeNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le montant payé ne peut pas être négatif');

        $reservation = $this->makeReservation(Reservation::STATUS_CONFIRMED, -100);
        $manager = new ReservationManager();
        $manager->validate($reservation);
    }

    public function testAmountPaidZeroIsValid(): void
    {
        $reservation = $this->makeReservation(Reservation::STATUS_CONFIRMED, 0);
        $manager = new ReservationManager();
        $this->assertTrue($manager->validate($reservation));
    }
}
