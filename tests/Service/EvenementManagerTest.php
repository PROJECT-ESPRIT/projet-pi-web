<?php

namespace App\Tests\Service;

use App\Entity\Evenement;
use App\Service\EvenementManager;
use PHPUnit\Framework\TestCase;

class EvenementManagerTest extends TestCase
{
    public function testValidEvenement(): void
    {
        $event = new Evenement();
        $event->setDateDebut(new \DateTime('2026-06-01 10:00:00'));
        $event->setDateFin(new \DateTime('2026-06-01 18:00:00'));
        $event->setNbPlaces(100);

        $manager = new EvenementManager();
        $this->assertTrue($manager->validate($event));
    }

    public function testDateFinMustBeAfterDateDebut(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La date de fin doit être postérieure à la date de début');

        $event = new Evenement();
        $event->setDateDebut(new \DateTime('2026-06-01 18:00:00'));
        $event->setDateFin(new \DateTime('2026-06-01 10:00:00'));
        $event->setNbPlaces(50);

        $manager = new EvenementManager();
        $manager->validate($event);
    }

    public function testNbPlacesMustBePositive(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nombre de places doit être supérieur à zéro');

        $event = new Evenement();
        $event->setDateDebut(new \DateTime('2026-06-01 10:00:00'));
        $event->setDateFin(new \DateTime('2026-06-01 18:00:00'));
        $event->setNbPlaces(0);

        $manager = new EvenementManager();
        $manager->validate($event);
    }

    public function testNbPlacesNegativeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $event = new Evenement();
        $event->setDateDebut(new \DateTime('2026-06-01 10:00:00'));
        $event->setDateFin(new \DateTime('2026-06-01 18:00:00'));
        $event->setNbPlaces(-5);

        $manager = new EvenementManager();
        $manager->validate($event);
    }
}
