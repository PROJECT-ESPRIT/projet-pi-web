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

<<<<<<< HEAD
        match ($user->getStatus()) {
            User::STATUS_EMAIL_PENDING => throw new CustomUserMessageAccountStatusException(
                'Veuillez vérifier votre adresse email avant de vous connecter.'
            ),
            User::STATUS_EMAIL_VERIFIED => throw new CustomUserMessageAccountStatusException(
                'Votre email est vérifié. Votre compte est en attente de validation par un administrateur.'
            ),
            User::STATUS_REJECTED => throw new CustomUserMessageAccountStatusException(
                'Votre compte a été refusé. Contactez l\'administration pour plus d\'informations.'
            ),
            User::STATUS_SUSPENDED => throw new CustomUserMessageAccountStatusException(
                'Votre compte a été suspendu. Contactez l\'administration pour plus d\'informations.'
            ),
            User::STATUS_APPROVED => null,
            default => null,
        };
=======
        if (!in_array('ROLE_ARTISTE', $user->getRoles(), true)) {
            return;
        }

        if ($user->getStatus() === User::STATUS_PENDING) {
            throw new CustomUserMessageAccountStatusException('Votre compte artiste est en attente de validation.');
        }

        if ($user->getStatus() === User::STATUS_REJECTED) {
            throw new CustomUserMessageAccountStatusException('Votre compte artiste a ete refuse.');
        }
>>>>>>> c4d1c44b0746a7387dc28bd3111400a167bda2d9
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
