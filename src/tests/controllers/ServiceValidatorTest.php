<?php

namespace App\Tests\Service;

use App\Service\ServiceValidator;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires du service ServiceValidator.
 *
 * Chaque méthode vérifie une règle métier précise.
 * On utilise une instanciation directe (pas de mocks) conformément
 * au workshop « Les tests unitaires dans un projet Symfony ».
 */
class ServiceValidatorTest extends TestCase
{
    private ServiceValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ServiceValidator();
    }

    // ══════════════════════════════════════════════════════════════════════
    // Règle 1 : Le titre est obligatoire et doit avoir au moins 5 caractères
    // ══════════════════════════════════════════════════════════════════════

    public function testValidTitleReturnsTrue(): void
    {
        $this->assertTrue($this->validator->validateTitle('Cours de maths'));
    }

    public function testEmptyTitleThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/titre/i');
        $this->validator->validateTitle('');
    }

    public function testShortTitleThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/titre/i');
        $this->validator->validateTitle('abc');
    }

    public function testWhitespaceTitleThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/titre/i');
        $this->validator->validateTitle('    ');
    }

    // ══════════════════════════════════════════════════════════════════════
    // Règle 2 : Le prix doit être strictement positif
    // ══════════════════════════════════════════════════════════════════════

    public function testValidPriceReturnsTrue(): void
    {
        $this->assertTrue($this->validator->validatePrice(30.0));
    }

    public function testZeroPriceThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/prix/i');
        $this->validator->validatePrice(0.0);
    }

    public function testNegativePriceThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/prix/i');
        $this->validator->validatePrice(-10.0);
    }

    // ══════════════════════════════════════════════════════════════════════
    // Règle 3 : Le prix ne peut pas dépasser 500 €
    // ══════════════════════════════════════════════════════════════════════

    public function testPriceAtMaximumReturnsTrue(): void
    {
        $this->assertTrue($this->validator->validatePrice(500.0));
    }

    public function testPriceOverMaximumThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/prix/i');
        $this->validator->validatePrice(600.0);
    }

    // ══════════════════════════════════════════════════════════════════════
    // Règle 4 : La description doit comporter au moins 20 caractères
    // ══════════════════════════════════════════════════════════════════════

    public function testValidDescriptionReturnsTrue(): void
    {
        $this->assertTrue($this->validator->validateDescription('Description complète du service.'));
    }

    public function testEmptyDescriptionThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/description/i');
        $this->validator->validateDescription('');
    }

    public function testShortDescriptionThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/description/i');
        $this->validator->validateDescription('Trop court.');
    }

    // ══════════════════════════════════════════════════════════════════════
    // Règle 5 : Le type d'utilisateur doit être PRESTATAIRE pour créer
    // ══════════════════════════════════════════════════════════════════════

    public function testPrestataireIsAllowedToCreate(): void
    {
        $this->assertTrue($this->validator->validateUserType('PRESTATAIRE'));
    }

    public function testEtudiantIsNotAllowedToCreate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/prestataire/i');
        $this->validator->validateUserType('ETUDIANT');
    }

    public function testAdminIsNotAllowedToCreate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/prestataire/i');
        $this->validator->validateUserType('ADMIN');
    }

    public function testUnknownTypeIsNotAllowed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/prestataire/i');
        $this->validator->validateUserType('UNKNOWN');
    }

    // ══════════════════════════════════════════════════════════════════════
    // Règle 6 : Un service complet valide doit être accepté
    // ══════════════════════════════════════════════════════════════════════

    public function testValidServicePassesAllRules(): void
    {
        $this->assertTrue($this->validator->validateTitle('Cours de mathématiques'));
        $this->assertTrue($this->validator->validatePrice(45.0));
        $this->assertTrue($this->validator->validateDescription('Description complète et détaillée du service.'));
        $this->assertTrue($this->validator->validateUserType('PRESTATAIRE'));
    }
}