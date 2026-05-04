<?php

namespace App\Tests\Service;

use App\Service\ActivationManager;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ActivationManager
 *
 * Run with:  php bin/phpunit tests/Service/ActivationManagerTest.php
 */
class ActivationManagerTest extends TestCase
{
    private ActivationManager $manager;

    protected function setUp(): void
    {
        $this->manager = new ActivationManager();
    }

    // =========================================================
    // RULE 1 — Passwords must match
    // =========================================================

    public function testPasswordsMatchReturnsTrue(): void
    {
        $result = $this->manager->checkPasswordsMatch('Secret1234', 'Secret1234');
        $this->assertTrue($result);
    }

    public function testPasswordsDifferentThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Les mots de passe ne correspondent pas.');

        $this->manager->checkPasswordsMatch('Secret1234', 'Different99');
    }

    public function testPasswordsCaseSensitive(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->manager->checkPasswordsMatch('secret1234', 'Secret1234');
    }

    public function testBothPasswordsEmpty(): void
    {
        // Two empty strings are equal → should pass
        $result = $this->manager->checkPasswordsMatch('', '');
        $this->assertTrue($result);
    }

    // =========================================================
    // RULE 2 — Activation code must not be expired
    // =========================================================

    public function testCodeNotExpiredReturnsTrue(): void
    {
        $now       = time();
        $expiresAt = $now + 900; // expires in 15 min

        $result = $this->manager->checkCodeNotExpired($expiresAt, $now);
        $this->assertTrue($result);
    }

    public function testCodeExpiredThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Code expiré. Veuillez vous réinscrire.');

        $now       = time();
        $expiresAt = $now - 1; // expired 1 second ago

        $this->manager->checkCodeNotExpired($expiresAt, $now);
    }

    public function testCodeExpiresExactlyNow(): void
    {
        // now === expiresAt → NOT expired (boundary: now > expiresAt fails)
        $now = time();

        $result = $this->manager->checkCodeNotExpired($now, $now);
        $this->assertTrue($result);
    }

    public function testCodeExpiredLongAgo(): void
    {
        $this->expectException(\RuntimeException::class);

        $now       = time();
        $expiresAt = $now - 3600; // expired 1 hour ago

        $this->manager->checkCodeNotExpired($expiresAt, $now);
    }

    // =========================================================
    // RULE 3 — Submitted code must match stored code
    // =========================================================

    public function testCodesMatchReturnsTrue(): void
    {
        $result = $this->manager->checkCodeMatches('482910', '482910');
        $this->assertTrue($result);
    }

    public function testCodesMismatchThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Code invalide. Réessayez.');

        $this->manager->checkCodeMatches('111111', '999999');
    }

    public function testCodesAreStringsNotIntegers(): void
    {
        // '007777' !== '7777' — leading zeros matter
        $this->expectException(\InvalidArgumentException::class);

        $this->manager->checkCodeMatches('7777', '007777');
    }

    public function testCodeWithLeadingZerosMatchesExactly(): void
    {
        // Both sides have the same leading zeros → must pass
        $result = $this->manager->checkCodeMatches('007777', '007777');
        $this->assertTrue($result);
    }
}