<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\UserLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

/**
 * Unit tests for UserLoader — the business rules extracted from AppAuthenticator.
 *
 * Run with: php bin/phpunit tests/Service/UserLoaderTest.php
 */
class UserLoaderTest extends TestCase
{
    // ─────────────────────────────────────────────────────────
    // HELPER — builds a mock User with the given properties
    // ─────────────────────────────────────────────────────────
    private function makeUser(string $type, string $status): User
    {
        $user = new User();
        $user->setName('Test User');
        $user->setEmail('test@example.com');
        $user->setPassword(password_hash('Secret1234', PASSWORD_BCRYPT));
        $user->setUserType($type);
        $user->setStatus($status);
        return $user;
    }

    // ─────────────────────────────────────────────────────────
    // HELPER — builds a UserLoader with a mocked repository
    // ─────────────────────────────────────────────────────────
    private function makeLoader(?User $returnedUser): UserLoader
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->method('findByEmail')->willReturn($returnedUser);
        return new UserLoader($repo);
    }

    // =========================================================
    // RULE 1 — User must exist
    // =========================================================

    public function testUnknownEmailThrowsException(): void
    {
        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Identifiants invalides.');

        $loader = $this->makeLoader(null); // repository returns nothing
        $loader->loadUser('ghost@example.com', 'ETUDIANT');
    }

    // =========================================================
    // RULE 2 — Submitted role must match the account type
    // =========================================================

    public function testWrongRoleEtudiantThrowsException(): void
    {
        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Ce compte n\'est pas un compte Étudiant.');

        $user   = $this->makeUser('PRESTATAIRE', 'ACTIVE');
        $loader = $this->makeLoader($user);
        $loader->loadUser('test@example.com', 'ETUDIANT'); // submits ETUDIANT but is PRESTATAIRE
    }

    public function testWrongRolePrestataireThrowsException(): void
    {
        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Ce compte n\'est pas un compte Prestataire.');

        $user   = $this->makeUser('ETUDIANT', 'ACTIVE');
        $loader = $this->makeLoader($user);
        $loader->loadUser('test@example.com', 'PRESTATAIRE');
    }

    public function testWrongRoleAdminThrowsException(): void
    {
        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Ce compte n\'est pas un compte Admin.');

        $user   = $this->makeUser('ETUDIANT', 'ACTIVE');
        $loader = $this->makeLoader($user);
        $loader->loadUser('test@example.com', 'ADMIN');
    }

    // =========================================================
    // RULE 3 — Account must be activated
    // =========================================================

    public function testInactiveAccountThrowsException(): void
    {
        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Votre compte n\'est pas encore activé.');

        $user   = $this->makeUser('ETUDIANT', 'INACTIVE');
        $loader = $this->makeLoader($user);
        $loader->loadUser('test@example.com', 'ETUDIANT');
    }

    // =========================================================
    // RULE 4 — Account must not be banned
    // =========================================================

    public function testBannedAccountThrowsException(): void
    {
        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Votre compte est banni.');

        $user   = $this->makeUser('ETUDIANT', 'BANNED');
        $loader = $this->makeLoader($user);
        $loader->loadUser('test@example.com', 'ETUDIANT');
    }

    // =========================================================
    // HAPPY PATH — valid user must be returned
    // =========================================================

    public function testValidEtudiantReturnsUser(): void
    {
        $user   = $this->makeUser('ETUDIANT', 'ACTIVE');
        $loader = $this->makeLoader($user);

        $result = $loader->loadUser('test@example.com', 'ETUDIANT');
        $this->assertSame($user, $result);
    }

    public function testValidPrestataireReturnsUser(): void
    {
        $user   = $this->makeUser('PRESTATAIRE', 'ACTIVE');
        $loader = $this->makeLoader($user);

        $result = $loader->loadUser('test@example.com', 'PRESTATAIRE');
        $this->assertSame($user, $result);
    }

    public function testValidAdminReturnsUser(): void
    {
        $user   = $this->makeUser('ADMIN', 'ACTIVE');
        $loader = $this->makeLoader($user);

        $result = $loader->loadUser('test@example.com', 'ADMIN');
        $this->assertSame($user, $result);
    }
}