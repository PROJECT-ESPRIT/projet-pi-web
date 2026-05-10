<?php

namespace App\Service;

use App\Entity\User;

class UserManager
{
    /**
     * Valide les règles métier de l'entité User.
     * Règles : le nom est obligatoire ; l'email doit être valide.
     */
    public function validate(User $user): bool
    {
        if (empty(trim((string) $user->getNom()))) {
            throw new \InvalidArgumentException('Le nom est obligatoire');
        }
        $email = $user->getEmail();
        if ($email === null || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Email invalide');
        }
        return true;
    }
}
