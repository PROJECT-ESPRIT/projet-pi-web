<?php

namespace App\Tests\Entity;

use App\Entity\Charity;
use App\Entity\Donation;
use PHPUnit\Framework\TestCase;

class CharityTest extends TestCase
{
    public function testGoalAmountIsClampedAndNullable(): void
    {
        $charity = new Charity();

        $charity->setGoalAmount(100);
        $this->assertSame(100, $charity->getGoalAmount());

        $charity->setGoalAmount(0);
        $this->assertSame(1, $charity->getGoalAmount());

        $charity->setGoalAmount(null);
        $this->assertNull($charity->getGoalAmount());
    }

    public function testAddAndRemoveDonationMaintainsAssociation(): void
    {
        $charity = new Charity();
        $donation = new Donation();

        $charity->addDonation($donation);
        $this->assertSame($charity, $donation->getCharity());
        $this->assertCount(1, $charity->getDonations());

        $charity->removeDonation($donation);
        $this->assertNull($donation->getCharity());
        $this->assertCount(0, $charity->getDonations());
    }
}
