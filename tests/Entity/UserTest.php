<?php

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the User entity (registration-related behavior).
 * Simple tests, no database or Symfony kernel.
 * Run: php bin/phpunit tests/Entity/UserTest.php
 */
class UserTest extends TestCase
{
    private function makeUser(string $email = 'student@test.com', string $nom = 'Dupont', string $prenom = 'Jean'): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setNom($nom);
        $user->setPrenom($prenom);
        $user->setPassword('hashed_password');
        return $user;
    }

    public function testNewUserHasCreatedAtSet(): void
    {
        $user = new User();

        $this->assertNotNull($user->getCreatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $user->getCreatedAt());
    }

    public function testNewUserHasEmailPendingStatus(): void
    {
        $user = new User();

        $this->assertSame(User::STATUS_EMAIL_PENDING, $user->getStatus());
    }

    public function testGetRolesAlwaysIncludesRoleUser(): void
    {
        $user = $this->makeUser();
        $user->setRoles([]);

        $roles = $user->getRoles();

        $this->assertContains('ROLE_USER', $roles);
    }

    public function testSetRolesAndGetRoles(): void
    {
        $user = $this->makeUser();
        $user->setRoles(['ROLE_PARTICIPANT']);

        $roles = $user->getRoles();

        $this->assertContains('ROLE_USER', $roles);
        $this->assertContains('ROLE_PARTICIPANT', $roles);
    }

    public function testUserIdentifierIsEmail(): void
    {
        $user = $this->makeUser('alice@example.com');

        $this->assertSame('alice@example.com', $user->getUserIdentifier());
    }

    public function testEmailNomPrenomSettersAndGetters(): void
    {
        $user = $this->makeUser('test@mail.com', 'Martin', 'Marie');

        $this->assertSame('test@mail.com', $user->getEmail());
        $this->assertSame('Martin', $user->getNom());
        $this->assertSame('Marie', $user->getPrenom());
    }

    public function testPasswordGetterAndSetter(): void
    {
        $user = new User();
        $user->setPassword('my_hashed_pass');

        $this->assertSame('my_hashed_pass', $user->getPassword());
    }

    public function testGenerateEmailVerificationTokenSetsTokenAndSentAt(): void
    {
        $user = $this->makeUser();

        $token = $user->generateEmailVerificationToken();

        $this->assertNotEmpty($token);
        $this->assertSame($token, $user->getEmailVerificationToken());
        $this->assertNotNull($user->getEmailVerificationSentAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $user->getEmailVerificationSentAt());
    }

    public function testTokenExpiredWhenNoSentAt(): void
    {
        $user = $this->makeUser();

        $this->assertTrue($user->isEmailVerificationTokenExpired(48));
    }

    public function testGetFullName(): void
    {
        $user = $this->makeUser('x@x.com', 'Dupont', 'Jean');

        $this->assertSame('Jean Dupont', $user->getFullName());
    }

    public function testSetStatus(): void
    {
        $user = $this->makeUser();
        $user->setStatus(User::STATUS_EMAIL_VERIFIED);

        $this->assertSame(User::STATUS_EMAIL_VERIFIED, $user->getStatus());
    }

    public function testTelephoneOptional(): void
    {
        $user = $this->makeUser();
        $this->assertNull($user->getTelephone());

        $user->setTelephone('0612345678');
        $this->assertSame('0612345678', $user->getTelephone());
    }

    public function testGetAgeWhenDateNaissanceNotSet(): void
    {
        $user = $this->makeUser();

        $this->assertNull($user->getAge());
    }

    public function testGetAgeWhenDateNaissanceSet(): void
    {
        $user = $this->makeUser();
        $user->setDateNaissance(new \DateTimeImmutable('-25 years'));

        $age = $user->getAge();

        $this->assertIsInt($age);
        $this->assertGreaterThanOrEqual(24, $age);
        $this->assertLessThanOrEqual(26, $age);
    }
}
