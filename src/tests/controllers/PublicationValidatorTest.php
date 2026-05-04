<?php

namespace App\Tests\Service;

use App\Service\PublicationValidator;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires du service PublicationValidator.
 *
 * Chaque méthode de test vérifie une règle métier précise.
 * On utilise de vraies instances (pas de mocks) conformément
 * au workshop « Les tests unitaires dans un projet Symfony ».
 */
class PublicationValidatorTest extends TestCase
{
    private PublicationValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new PublicationValidator();
    }

    // ══════════════════════════════════════════════════════════════════════
    // Règle 1 : Le titre est obligatoire et doit avoir au moins 5 caractères
    // ══════════════════════════════════════════════════════════════════════

    public function testValidTitreReturnsTrue(): void
    {
        $this->assertTrue($this->validator->validateTitre('Cours de maths'));
    }

    public function testEmptyTitreThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/titre/i');
        $this->validator->validateTitre('');
    }

    public function testShortTitreThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/titre/i');
        $this->validator->validateTitre('abc');
    }

    public function testWhitespaceTitreThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/titre/i');
        $this->validator->validateTitre('    ');
    }

    // ══════════════════════════════════════════════════════════════════════
    // Règle 2 : Le message doit comporter au moins 20 caractères
    // ══════════════════════════════════════════════════════════════════════

    public function testValidMessageReturnsTrue(): void
    {
        $this->assertTrue($this->validator->validateMessage('Je cherche un tuteur en physique.'));
    }

    public function testEmptyMessageThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/message/i');
        $this->validator->validateMessage('');
    }

    public function testShortMessageThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/message/i');
        $this->validator->validateMessage('Trop court.');
    }

    // ══════════════════════════════════════════════════════════════════════
    // Règle 3 : Le type de publication doit être valide
    // ══════════════════════════════════════════════════════════════════════

    public function testValidTypeDemandeServiceReturnsTrue(): void
    {
        $this->assertTrue($this->validator->validateType('DEMANDE_SERVICE'));
    }

    public function testValidTypeVenteObjetReturnsTrue(): void
    {
        $this->assertTrue($this->validator->validateType('VENTE_OBJET'));
    }

    public function testValidTypeDemandeReturnsTrue(): void
    {
        $this->assertTrue($this->validator->validateType('DEMANDE'));
    }

    public function testInvalidTypeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/type/i');
        $this->validator->validateType('TYPE_INEXISTANT');
    }

    public function testEmptyTypeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/type/i');
        $this->validator->validateType('');
    }

    // ══════════════════════════════════════════════════════════════════════
    // Règle 4 : La localisation est obligatoire
    // ══════════════════════════════════════════════════════════════════════

    public function testValidLocalisationReturnsTrue(): void
    {
        $this->assertTrue($this->validator->validateLocalisation('Tunis'));
    }

    public function testEmptyLocalisationThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/localisation/i');
        $this->validator->validateLocalisation('');
    }

    public function testBlankLocalisationThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/localisation/i');
        $this->validator->validateLocalisation('   ');
    }

    // ══════════════════════════════════════════════════════════════════════
    // Règle 5 : Le prix de vente est obligatoire et positif pour VENTE_OBJET
    // ══════════════════════════════════════════════════════════════════════

    public function testValidPrixVenteReturnsTrue(): void
    {
        $this->assertTrue($this->validator->validatePrixVente(25.0, 'VENTE_OBJET'));
    }

    public function testNullPrixVenteForVenteObjetThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/prix/i');
        $this->validator->validatePrixVente(null, 'VENTE_OBJET');
    }

    public function testZeroPrixVenteForVenteObjetThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/prix/i');
        $this->validator->validatePrixVente(0.0, 'VENTE_OBJET');
    }

    public function testNegativePrixVenteForVenteObjetThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/prix/i');
        $this->validator->validatePrixVente(-10.0, 'VENTE_OBJET');
    }

    public function testPrixVenteOver1000ForVenteObjetThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/prix/i');
        $this->validator->validatePrixVente(1500.0, 'VENTE_OBJET');
    }

    public function testNullPrixVenteIgnoredForNonVenteObjet(): void
    {
        // Pour DEMANDE_SERVICE, le prix n'est pas requis
        $this->assertTrue($this->validator->validatePrixVente(null, 'DEMANDE_SERVICE'));
    }
}