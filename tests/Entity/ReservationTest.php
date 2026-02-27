<?php

namespace App\Tests\Entity;

use App\Entity\Evenement;
use App\Entity\Reservation;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Reservation entity.
 *
 * Pure PHP tests — no database needed.
 * Run with:  php bin/phpunit tests/Entity/ReservationTest.php
 */
class ReservationTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Helper: make a basic user
    // -----------------------------------------------------------------------
    private function makeUser(string $prenom = 'Alice', string $nom = 'Dupont'): User
    {
        $user = new User();
        $user->setPrenom($prenom);
        $user->setNom($nom);
        $user->setEmail($prenom . '@test.com');
        $user->setPassword('secret');
        return $user;
    }

    // -----------------------------------------------------------------------
    // Helper: make a basic event
    // -----------------------------------------------------------------------
    private function makeEvent(): Evenement
    {
        $event = new Evenement();
        $event->setTitre('Concert Test');
        $event->setDescription('Une description de test.');
        $event->setLieu('Tunis');
        $event->setNbPlaces(50);
        return $event;
    }

    // -----------------------------------------------------------------------
    // Test 1 — A new Reservation has dateReservation set automatically
    // -----------------------------------------------------------------------
    public function testDateReservationIsSetAutomaticallyOnCreate(): void
    {
        $r = new Reservation();

        $this->assertNotNull($r->getDateReservation());
        $this->assertInstanceOf(\DateTimeImmutable::class, $r->getDateReservation());
    }

    // -----------------------------------------------------------------------
    // Test 2 — A new Reservation starts with status CONFIRMED
    // -----------------------------------------------------------------------
    public function testNewReservationStatusIsConfirmedByDefault(): void
    {
        $r = new Reservation();

        $this->assertSame(Reservation::STATUS_CONFIRMED, $r->getStatus());
    }

    // -----------------------------------------------------------------------
    // Test 3 — We can change the status to PENDING
    // -----------------------------------------------------------------------
    public function testWeCanChangeStatusToPending(): void
    {
        $r = new Reservation();
        $r->setStatus(Reservation::STATUS_PENDING);

        $this->assertSame(Reservation::STATUS_PENDING, $r->getStatus());
    }

    // -----------------------------------------------------------------------
    // Test 4 — We can change the status to CANCELLED
    // -----------------------------------------------------------------------
    public function testWeCanChangeStatusToCancelled(): void
    {
        $r = new Reservation();
        $r->setStatus(Reservation::STATUS_CANCELLED);

        $this->assertSame(Reservation::STATUS_CANCELLED, $r->getStatus());
    }

    // -----------------------------------------------------------------------
    // Test 5 — The three status constants have the expected string values
    // -----------------------------------------------------------------------
    public function testStatusConstantsHaveCorrectValues(): void
    {
        $this->assertSame('PENDING',   Reservation::STATUS_PENDING);
        $this->assertSame('CONFIRMED', Reservation::STATUS_CONFIRMED);
        $this->assertSame('CANCELLED', Reservation::STATUS_CANCELLED);
    }

    // -----------------------------------------------------------------------
    // Test 6 — seatLabel is null by default
    // -----------------------------------------------------------------------
    public function testSeatLabelIsNullByDefault(): void
    {
        $r = new Reservation();

        $this->assertNull($r->getSeatLabel());
    }

    // -----------------------------------------------------------------------
    // Test 7 — We can assign a seat label
    // -----------------------------------------------------------------------
    public function testWeCanAssignASeatLabel(): void
    {
        $r = new Reservation();
        $r->setSeatLabel('B7');

        $this->assertSame('B7', $r->getSeatLabel());
    }

    // -----------------------------------------------------------------------
    // Test 8 — amountPaid is null by default (free event)
    // -----------------------------------------------------------------------
    public function testAmountPaidIsNullByDefault(): void
    {
        $r = new Reservation();

        $this->assertNull($r->getAmountPaid());
    }

    // -----------------------------------------------------------------------
    // Test 9 — We can store the amount paid (in centimes)
    // -----------------------------------------------------------------------
    public function testWeCanStoreAmountPaid(): void
    {
        $r = new Reservation();
        $r->setAmountPaid(2500); // 25.00 TND in centimes

        $this->assertSame(2500, $r->getAmountPaid());
    }

    // -----------------------------------------------------------------------
    // Test 10 — scannedAt is null by default (ticket not scanned yet)
    // -----------------------------------------------------------------------
    public function testScannedAtIsNullByDefault(): void
    {
        $r = new Reservation();

        $this->assertNull($r->getScannedAt());
    }

    // -----------------------------------------------------------------------
    // Test 11 — We can mark the ticket as scanned
    // -----------------------------------------------------------------------
    public function testWeCanMarkTicketAsScanned(): void
    {
        $r       = new Reservation();
        $scanTime = new \DateTimeImmutable('2026-03-01 20:00:00');
        $r->setScannedAt($scanTime);

        $this->assertSame($scanTime, $r->getScannedAt());
    }

    // -----------------------------------------------------------------------
    // Test 12 — We can link a participant (User) to the reservation
    // -----------------------------------------------------------------------
    public function testWeCanLinkAParticipant(): void
    {
        $r    = new Reservation();
        $user = $this->makeUser();
        $r->setParticipant($user);

        $this->assertSame($user, $r->getParticipant());
    }

    // -----------------------------------------------------------------------
    // Test 13 — We can link an event (Evenement) to the reservation
    // -----------------------------------------------------------------------
    public function testWeCanLinkAnEvent(): void
    {
        $r     = new Reservation();
        $event = $this->makeEvent();
        $r->setEvenement($event);

        $this->assertSame($event, $r->getEvenement());
    }

    // -----------------------------------------------------------------------
    // Test 14 — stripeCheckoutSessionId is null by default
    // -----------------------------------------------------------------------
    public function testStripeSessionIdIsNullByDefault(): void
    {
        $r = new Reservation();

        $this->assertNull($r->getStripeCheckoutSessionId());
    }

    // -----------------------------------------------------------------------
    // Test 15 — We can store a Stripe checkout session ID
    // -----------------------------------------------------------------------
    public function testWeCanStoreStripeSessionId(): void
    {
        $r = new Reservation();
        $r->setStripeCheckoutSessionId('cs_test_abc123');

        $this->assertSame('cs_test_abc123', $r->getStripeCheckoutSessionId());
    }
}
