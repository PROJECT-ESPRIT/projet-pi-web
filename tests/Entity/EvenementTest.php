<?php

namespace App\Tests\Entity;

use App\Entity\Evenement;
use App\Entity\Reservation;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Evenement entity.
 *
 * We only test pure PHP logic here — no database, no Symfony kernel needed.
 * Run with:  php bin/phpunit tests/Entity/EvenementTest.php
 */
class EvenementTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Helper: create a simple User with a first name and last name
    // -----------------------------------------------------------------------
    private function makeUser(string $prenom, string $nom): User
    {
        $user = new User();
        $user->setPrenom($prenom);
        $user->setNom($nom);
        $user->setEmail($prenom . '@test.com');
        $user->setPassword('secret');
        return $user;
    }

    // -----------------------------------------------------------------------
    // Helper: create a Reservation with a given status and optional seat
    // -----------------------------------------------------------------------
    private function makeReservation(User $participant, string $status, ?string $seat = null): Reservation
    {
        $r = new Reservation();
        $r->setParticipant($participant);
        $r->setStatus($status);
        $r->setSeatLabel($seat);
        return $r;
    }

    // -----------------------------------------------------------------------
    // Test 1 — When we create a new Evenement, createdAt is set automatically
    // -----------------------------------------------------------------------
    public function testCreatedAtIsSetAutomaticallyWhenCreated(): void
    {
        $event = new Evenement();

        // createdAt should NOT be null right after construction
        $this->assertNotNull($event->getCreatedAt());

        // It should be a DateTimeImmutable
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->getCreatedAt());
    }

    // -----------------------------------------------------------------------
    // Test 2 — A new event is NOT cancelled by default
    // -----------------------------------------------------------------------
    public function testNewEventIsNotCancelledByDefault(): void
    {
        $event = new Evenement();

        $this->assertFalse($event->isAnnule());
    }

    // -----------------------------------------------------------------------
    // Test 3 — We can cancel an event and store the reason
    // -----------------------------------------------------------------------
    public function testWeCanCancelAnEventWithAReason(): void
    {
        $event = new Evenement();
        $event->setAnnule(true);
        $event->setMotifAnnulation('Problème technique');
        $event->setDateAnnulation(new \DateTimeImmutable());

        $this->assertTrue($event->isAnnule());
        $this->assertSame('Problème technique', $event->getMotifAnnulation());
        $this->assertNotNull($event->getDateAnnulation());
    }

    // -----------------------------------------------------------------------
    // Test 4 — getTakenSeats() returns an empty array when there are no reservations
    // -----------------------------------------------------------------------
    public function testGetTakenSeatsIsEmptyWhenNoReservations(): void
    {
        $event = new Evenement();

        $seats = $event->getTakenSeats();

        $this->assertIsArray($seats);
        $this->assertEmpty($seats);
    }

    // -----------------------------------------------------------------------
    // Test 5 — getTakenSeats() only counts CONFIRMED reservations
    // -----------------------------------------------------------------------
    public function testGetTakenSeatsOnlyCountsConfirmedReservations(): void
    {
        $event = new Evenement();

        $alice = $this->makeUser('Alice', 'Dupont');
        $bob   = $this->makeUser('Bob',   'Martin');
        $carol = $this->makeUser('Carol', 'Leroy');

        // Alice has a CONFIRMED reservation on seat A1
        $r1 = $this->makeReservation($alice, Reservation::STATUS_CONFIRMED, 'A1');
        // Bob has a PENDING reservation on seat A2 — should NOT appear
        $r2 = $this->makeReservation($bob, Reservation::STATUS_PENDING, 'A2');
        // Carol has a CANCELLED reservation on seat A3 — should NOT appear
        $r3 = $this->makeReservation($carol, Reservation::STATUS_CANCELLED, 'A3');

        $event->addReservation($r1);
        $event->addReservation($r2);
        $event->addReservation($r3);

        $seats = $event->getTakenSeats();

        // Only Alice's seat should be in the result
        $this->assertCount(1, $seats);
        $this->assertArrayHasKey('A1', $seats);
        $this->assertArrayNotHasKey('A2', $seats);
        $this->assertArrayNotHasKey('A3', $seats);
    }

    // -----------------------------------------------------------------------
    // Test 6 — getTakenSeats() formats the name as "FirstName L."
    // -----------------------------------------------------------------------
    public function testGetTakenSeatsFormatsNameCorrectly(): void
    {
        $event = new Evenement();

        $user = $this->makeUser('Alice', 'Dupont');
        $r    = $this->makeReservation($user, Reservation::STATUS_CONFIRMED, 'B5');
        $event->addReservation($r);

        $seats = $event->getTakenSeats();

        // Expected format: "Alice D."
        $this->assertSame('Alice D.', $seats['B5']);
    }

    // -----------------------------------------------------------------------
    // Test 7 — getTakenSeats() ignores reservations that have no seat label
    // -----------------------------------------------------------------------
    public function testGetTakenSeatsIgnoresReservationsWithNoSeat(): void
    {
        $event = new Evenement();

        $user = $this->makeUser('Alice', 'Dupont');
        // Seat label is null (free-standing event, no assigned seat)
        $r = $this->makeReservation($user, Reservation::STATUS_CONFIRMED, null);
        $event->addReservation($r);

        $seats = $event->getTakenSeats();

        $this->assertEmpty($seats);
    }

    // -----------------------------------------------------------------------
    // Test 8 — Multiple confirmed reservations all appear in getTakenSeats()
    // -----------------------------------------------------------------------
    public function testGetTakenSeatsReturnsAllConfirmedSeats(): void
    {
        $event = new Evenement();

        $alice = $this->makeUser('Alice', 'Dupont');
        $bob   = $this->makeUser('Bob',   'Martin');

        $event->addReservation($this->makeReservation($alice, Reservation::STATUS_CONFIRMED, 'A1'));
        $event->addReservation($this->makeReservation($bob,   Reservation::STATUS_CONFIRMED, 'A2'));

        $seats = $event->getTakenSeats();

        $this->assertCount(2, $seats);
        $this->assertArrayHasKey('A1', $seats);
        $this->assertArrayHasKey('A2', $seats);
    }

    // -----------------------------------------------------------------------
    // Test 9 — Setters and getters work correctly for basic fields
    // -----------------------------------------------------------------------
    public function testSettersAndGettersWorkForBasicFields(): void
    {
        $event = new Evenement();

        $event->setTitre('Concert de Jazz');
        $event->setDescription('Un super concert de jazz en plein air.');
        $event->setLieu('Tunis');
        $event->setNbPlaces(100);
        $event->setPrix(25.0);

        $this->assertSame('Concert de Jazz', $event->getTitre());
        $this->assertSame('Un super concert de jazz en plein air.', $event->getDescription());
        $this->assertSame('Tunis', $event->getLieu());
        $this->assertSame(100, $event->getNbPlaces());
        $this->assertSame(25.0, $event->getPrix());
    }

    // -----------------------------------------------------------------------
    // Test 10 — Age restriction fields are null by default (optional)
    // -----------------------------------------------------------------------
    public function testAgeRestrictionIsNullByDefault(): void
    {
        $event = new Evenement();

        $this->assertNull($event->getAgeMin());
        $this->assertNull($event->getAgeMax());
    }

    // -----------------------------------------------------------------------
    // Test 11 — We can set age restrictions
    // -----------------------------------------------------------------------
    public function testWeCanSetAgeRestrictions(): void
    {
        $event = new Evenement();
        $event->setAgeMin(18);
        $event->setAgeMax(60);

        $this->assertSame(18, $event->getAgeMin());
        $this->assertSame(60, $event->getAgeMax());
    }

    // -----------------------------------------------------------------------
    // Test 12 — addReservation links the reservation back to this event
    // -----------------------------------------------------------------------
    public function testAddReservationLinksBackToEvent(): void
    {
        $event = new Evenement();
        $user  = $this->makeUser('Alice', 'Dupont');
        $r     = $this->makeReservation($user, Reservation::STATUS_CONFIRMED, 'C3');

        $event->addReservation($r);

        // The reservation should know which event it belongs to
        $this->assertSame($event, $r->getEvenement());
        // The event's collection should contain the reservation
        $this->assertTrue($event->getReservations()->contains($r));
    }

    // -----------------------------------------------------------------------
    // Test 13 — Adding the same reservation twice does not duplicate it
    // -----------------------------------------------------------------------
    public function testAddingTheSameReservationTwiceDoesNotDuplicate(): void
    {
        $event = new Evenement();
        $user  = $this->makeUser('Alice', 'Dupont');
        $r     = $this->makeReservation($user, Reservation::STATUS_CONFIRMED, 'D1');

        $event->addReservation($r);
        $event->addReservation($r); // second time — should be ignored

        $this->assertCount(1, $event->getReservations());
    }

    // -----------------------------------------------------------------------
    // Test 14 — Prix is null by default (free event)
    // -----------------------------------------------------------------------
    public function testPrixIsNullByDefault(): void
    {
        $event = new Evenement();

        $this->assertNull($event->getPrix());
    }

    // -----------------------------------------------------------------------
    // Test 15 — Layout fields are null by default
    // -----------------------------------------------------------------------
    public function testLayoutFieldsAreNullByDefault(): void
    {
        $event = new Evenement();

        $this->assertNull($event->getLayoutType());
        $this->assertNull($event->getLayoutRows());
        $this->assertNull($event->getLayoutCols());
    }
}
