<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\UserManager;
use PHPUnit\Framework\TestCase;

class UserManagerTest extends TestCase
{
    public function testValidUser(): void
    {
        $user = new User();
        $user->setNom('Dupont');
        $user->setPrenom('Jean');
        $user->setEmail('jean.dupont@gmail.com');
        $user->setPassword('hashed');

        $manager = new UserManager();
        $this->assertTrue($manager->validate($user));
    }

    public function testUserWithoutName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom est obligatoire');

        $user = new User();
        $user->setNom('');
        $user->setEmail('test@gmail.com');
        $user->setPassword('hashed');

        $manager = new UserManager();
        $manager->validate($user);
    }

    public function testUserWithWhitespaceOnlyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom est obligatoire');

        $user = new User();
        $user->setNom('   ');
        $user->setEmail('test@gmail.com');

        $manager = new UserManager();
        $manager->validate($user);
    }

    public function testUserWithInvalidEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email invalide');

        $user = new User();
        $user->setNom('Martin');
        $user->setPrenom('Marie');
        $user->setEmail('email_invalide');
        $user->setPassword('hashed');

        $manager = new UserManager();
        $manager->validate($user);
    }

    public function testUserWithNullEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email invalide');

        $user = new User();
        $user->setNom('Martin');
        $user->setEmail('');
        $user->setPassword('hashed');

        $manager = new UserManager();
        $manager->validate($user);
    }
}
