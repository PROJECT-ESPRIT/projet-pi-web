<?php

namespace App\Service;

use App\Entity\User;

class LoyaltyService
{
    public const POINTS_DONATION = 10;
    public const POINTS_RESERVATION = 15;
    public const POINTS_COMMANDE = 20;
    public const POINTS_CANCELLATION_PENALTY = 10;

    public function awardPoints(User $user, int $points): void
    {
        if ($points <= 0) {
            return;
        }

        $user->setPoints($user->getPoints() + $points);
        $user->setLoyaltyLevel($this->resolveLevel($user->getPoints()));
    }

    public function removePoints(User $user, int $points): void
    {
        if ($points <= 0) {
            return;
        }

        $user->setPoints(max(0, $user->getPoints() - $points));
        $user->setLoyaltyLevel($this->resolveLevel($user->getPoints()));
    }

    private function resolveLevel(int $points): string
    {
        if ($points >= 200) {
            return User::LEVEL_GOLD;
        }

        if ($points >= 51) {
            return User::LEVEL_SILVER;
        }

        return User::LEVEL_BRONZE;
    }
}
