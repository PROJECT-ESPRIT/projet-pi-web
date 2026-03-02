<?php

namespace App\Tests\Entity;

use App\Entity\Donation;
use PHPUnit\Framework\TestCase;

class DonationTest extends TestCase
{
    public function testAmountIsClampedToZero(): void
    {
        $donation = new Donation();

        $donation->setAmount(25);
        $this->assertSame(25, $donation->getAmount());

        $donation->setAmount(-10);
        $this->assertSame(0, $donation->getAmount());
    }

    public function testAnonymousFlagDefaultsFalseAndCanToggle(): void
    {
        $donation = new Donation();

        $this->assertFalse($donation->isAnonymous());
        $donation->setIsAnonymous(true);
        $this->assertTrue($donation->isAnonymous());
    }
}
