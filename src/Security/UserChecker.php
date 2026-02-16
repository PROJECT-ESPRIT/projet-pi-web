<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!in_array('ROLE_ARTISTE', $user->getRoles(), true)) {
            return;
        }

        if ($user->getStatus() === User::STATUS_PENDING) {
            throw new CustomUserMessageAccountStatusException('Votre compte artiste est en attente de validation.');
        }

        if ($user->getStatus() === User::STATUS_REJECTED) {
            throw new CustomUserMessageAccountStatusException('Votre compte artiste a ete refuse.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
