<?php

namespace App\Tests\Service;

use App\Service\UserManager;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for UserManager::validate()
 *
 * Run with:  php bin/phpunit tests/Service/UserManagerTest.php
 */
class UserManagerTest extends TestCase
{
    private UserManager $manager;

    protected function setUp(): void
    {
        $this->manager = new UserManager();
    }

    // =========================================================
    // ✅  HAPPY PATH — valid data must return true
    // =========================================================

    public function testValidCreateData(): void
    {
        $data = [
            'name'     => 'Alice Dupont',
            'email'    => 'alice@example.com',
            'password' => 'Secret1234',
            'userType' => 'ETUDIANT',
        ];

        $this->assertTrue($this->manager->validate($data, isUpdate: false));
    }

    public function testValidCreateDataWithAllOptionalFields(): void
    {
        $data = [
            'name'           => 'Bob Martin',
            'email'          => 'bob@example.com',
            'password'       => 'Secure99!',
            'userType'       => 'PRESTATAIRE',
            'phone'          => '+216 20 123 456',
            'gender'         => 'male',
            'status'         => 'ACTIVE',
            'dateNaissance'  => '1995-06-15',
            'trustPoints'    => 10,
        ];

        $this->assertTrue($this->manager->validate($data, isUpdate: false));
    }

    public function testValidUpdateWithPartialData(): void
    {
        // On update, only present fields are validated — no required-field check
        $this->assertTrue($this->manager->validate(['name' => 'New Name'], isUpdate: true));
    }

    // =========================================================
    // ❌  REQUIRED FIELDS (create mode)
    // =========================================================

    public function testMissingNameOnCreate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Field 'name' is required.");

        $this->manager->validate([
            'email'    => 'test@example.com',
            'password' => 'Secret1234',
            'userType' => 'ETUDIANT',
        ], isUpdate: false);
    }

    public function testMissingEmailOnCreate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Field 'email' is required.");

        $this->manager->validate([
            'name'     => 'Alice',
            'password' => 'Secret1234',
            'userType' => 'ETUDIANT',
        ], isUpdate: false);
    }

    public function testMissingPasswordOnCreate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Field 'password' is required.");

        $this->manager->validate([
            'name'     => 'Alice',
            'email'    => 'alice@example.com',
            'userType' => 'ETUDIANT',
        ], isUpdate: false);
    }

    public function testMissingUserTypeOnCreate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Field 'userType' is required.");

        $this->manager->validate([
            'name'     => 'Alice',
            'email'    => 'alice@example.com',
            'password' => 'Secret1234',
        ], isUpdate: false);
    }

    // =========================================================
    // ❌  NAME VALIDATION
    // =========================================================

    public function testNameTooShort(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Name must be at least 2 characters.');

        $this->manager->validate([
            'name'     => 'A',
            'email'    => 'test@example.com',
            'password' => 'Secret1234',
            'userType' => 'ETUDIANT',
        ], isUpdate: false);
    }

    

    // =========================================================
    // ❌  EMAIL VALIDATION
    // =========================================================

    public function testInvalidEmailMissingAt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email address.');

        $this->manager->validate([
            'name'     => 'Alice',
            'email'    => 'invalidemail.com',
            'password' => 'Secret1234',
            'userType' => 'ETUDIANT',
        ], isUpdate: false);
    }

    public function testInvalidEmailMissingDomain(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email address.');

        $this->manager->validate([
            'name'     => 'Alice',
            'email'    => 'alice@',
            'password' => 'Secret1234',
            'userType' => 'ETUDIANT',
        ], isUpdate: false);
    }

    // =========================================================
    // ❌  PASSWORD VALIDATION
    // =========================================================

    public function testPasswordTooShort(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Password must be at least 8 characters.');

        $this->manager->validate([
            'name'     => 'Alice',
            'email'    => 'alice@example.com',
            'password' => 'Ab1',
            'userType' => 'ETUDIANT',
        ], isUpdate: false);
    }

    public function testPasswordMissingUppercase(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Password must contain at least one uppercase letter.');

        $this->manager->validate([
            'name'     => 'Alice',
            'email'    => 'alice@example.com',
            'password' => 'secret1234',
            'userType' => 'ETUDIANT',
        ], isUpdate: false);
    }

    public function testPasswordMissingNumber(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Password must contain at least one number.');

        $this->manager->validate([
            'name'     => 'Alice',
            'email'    => 'alice@example.com',
            'password' => 'SecretPass',
            'userType' => 'ETUDIANT',
        ], isUpdate: false);
    }

    // =========================================================
    // ❌  USER TYPE VALIDATION
    // =========================================================

    public function testInvalidUserType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid userType.');

        $this->manager->validate([
            'name'     => 'Alice',
            'email'    => 'alice@example.com',
            'password' => 'Secret1234',
            'userType' => 'STUDENT', // wrong — must be ETUDIANT
        ], isUpdate: false);
    }

    // =========================================================
    // ❌  STATUS VALIDATION
    // =========================================================

    public function testInvalidStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid status.');

        $this->manager->validate([
            'name'     => 'Alice',
            'email'    => 'alice@example.com',
            'password' => 'Secret1234',
            'userType' => 'ETUDIANT',
            'status'   => 'SUSPENDED', // not in allowed list
        ], isUpdate: false);
    }

    // =========================================================
    // ❌  GENDER VALIDATION
    // =========================================================

    public function testInvalidGender(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid gender.');

        $this->manager->validate([
            'name'     => 'Alice',
            'email'    => 'alice@example.com',
            'password' => 'Secret1234',
            'userType' => 'ETUDIANT',
            'gender'   => 'unknown',
        ], isUpdate: false);
    }

    // =========================================================
    // ❌  PHONE VALIDATION
    // =========================================================

    public function testInvalidPhone(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid phone number format.');

        $this->manager->validate([
            'name'     => 'Alice',
            'email'    => 'alice@example.com',
            'password' => 'Secret1234',
            'userType' => 'ETUDIANT',
            'phone'    => 'not-a-phone',
        ], isUpdate: false);
    }

    public function testValidTunisianPhone(): void
    {
        $this->assertTrue($this->manager->validate([
            'name'     => 'Alice',
            'email'    => 'alice@example.com',
            'password' => 'Secret1234',
            'userType' => 'ETUDIANT',
            'phone'    => '+21620123456',
        ], isUpdate: false));
    }

    // =========================================================
    // ❌  DATE OF BIRTH VALIDATION
    // =========================================================

    public function testInvalidDateFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid date format.');

        $this->manager->validate([
            'name'          => 'Alice',
            'email'         => 'alice@example.com',
            'password'      => 'Secret1234',
            'userType'      => 'ETUDIANT',
            'dateNaissance' => '21-06-1995', // wrong format
        ], isUpdate: false);
    }

    public function testDateOfBirthInTheFuture(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Date of birth cannot be in the future.');

        $this->manager->validate([
            'name'          => 'Alice',
            'email'         => 'alice@example.com',
            'password'      => 'Secret1234',
            'userType'      => 'ETUDIANT',
            'dateNaissance' => '2099-01-01',
        ], isUpdate: false);
    }

   


}