<?php

namespace App\Tests\Service;

use App\Entity\Categorie;
use App\Entity\Publication;
use App\Entity\Service as ServiceEntity;
use App\Entity\User;
use App\Service\ModerationValidator;
use PHPUnit\Framework\TestCase;

class ModerationValidatorTest extends TestCase  // ← "Test" ajouté ici
{
    private ModerationValidator $manager;

    protected function setUp(): void
    {
        $this->manager = new ModerationValidator();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    private function makeUser(int $trustPoints = 50): User
    {
        $user = new User();
        $user->setTrustPoints($trustPoints);
        return $user;
    }

    private function makeValidService(): ServiceEntity
    {
        $service = new ServiceEntity();
        $service->setTitle('Cours de mathématiques');
        $service->setDescription('Description suffisamment longue pour passer la validation sans aucun problème.');
        $service->setCategory(new Categorie());
        $service->setPrice(30.0);
        $service->setUser($this->makeUser());
        return $service;
    }

    private function makeValidPublication(): Publication
    {
        $pub = new Publication();
        $pub->setTitre('Vente livre de maths');
        $pub->setMessage('Message suffisamment long pour valider la règle de longueur minimale sans souci.');
        $pub->setCategory(new Categorie());
        $pub->setLocalisation('Tunis');
        $pub->setTypePublication('DEMANDE');
        $pub->setUser($this->makeUser());
        return $pub;
    }

    // ── validateService ───────────────────────────────────────────────────

    public function testValidServiceReturnsTrue(): void
    {
        $this->assertTrue($this->manager->validateService($this->makeValidService()));
    }

    public function testServiceWithShortTitleThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/titre/i');
        $service = $this->makeValidService();
        $service->setTitle('abc');
        $this->manager->validateService($service);
    }

    public function testServiceWithEmptyDescriptionThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/description/i');
        $service = $this->makeValidService();
        $service->setDescription('');
        $this->manager->validateService($service);
    }

    public function testServiceWithShortDescriptionThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/description/i');
        $service = $this->makeValidService();
        $service->setDescription('Trop court.');
        $this->manager->validateService($service);
    }

    public function testServiceWithoutCategoryThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/catégorie/i');
        $service = $this->makeValidService();
        $service->setCategory(null);
        $this->manager->validateService($service);
    }

    public function testServiceWithZeroPriceThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/prix/i');
        $service = $this->makeValidService();
        $service->setPrice(0.0);
        $this->manager->validateService($service);
    }

    public function testServiceWithNegativePriceThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/prix/i');
        $service = $this->makeValidService();
        $service->setPrice(-10.0);
        $this->manager->validateService($service);
    }

    public function testServiceWithPriceOver500ThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/prix/i');
        $service = $this->makeValidService();
        $service->setPrice(600.0);
        $this->manager->validateService($service);
    }

    public function testServiceWithLowTrustPointsThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/confiance/i');
        $service = $this->makeValidService();
        $service->setUser($this->makeUser(trustPoints: 5));
        $this->manager->validateService($service);
    }

    public function testServiceWithNullUserThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/confiance/i');
        $service = $this->makeValidService();
        $service->setUser(null);
        $this->manager->validateService($service);
    }

    // ── validatePublication ───────────────────────────────────────────────

    public function testValidPublicationReturnsTrue(): void
    {
        $this->assertTrue($this->manager->validatePublication($this->makeValidPublication()));
    }

    public function testPublicationWithShortTitreThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/titre/i');
        $pub = $this->makeValidPublication();
        $pub->setTitre('abc');
        $this->manager->validatePublication($pub);
    }

    public function testPublicationWithEmptyMessageThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/message/i');
        $pub = $this->makeValidPublication();
        $pub->setMessage('');
        $this->manager->validatePublication($pub);
    }

    public function testPublicationWithShortMessageThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/message/i');
        $pub = $this->makeValidPublication();
        $pub->setMessage('Trop court.');
        $this->manager->validatePublication($pub);
    }

    public function testPublicationWithoutCategoryThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/catégorie/i');
        $pub = $this->makeValidPublication();
        $pub->setCategory(null);
        $this->manager->validatePublication($pub);
    }

    public function testPublicationWithNullLocalisationThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/localisation/i');
        $pub = $this->makeValidPublication();
        $pub->setLocalisation(null);
        $this->manager->validatePublication($pub);
    }

    public function testPublicationWithBlankLocalisationThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/localisation/i');
        $pub = $this->makeValidPublication();
        $pub->setLocalisation('   ');
        $this->manager->validatePublication($pub);
    }

    public function testVenteObjetWithNullPrixVenteThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/prix/i');
        $pub = $this->makeValidPublication();
        $pub->setTypePublication('VENTE_OBJET');
        $pub->setPrixVente(null);
        $this->manager->validatePublication($pub);
    }

    public function testVenteObjetWithZeroPrixVenteThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/prix/i');
        $pub = $this->makeValidPublication();
        $pub->setTypePublication('VENTE_OBJET');
        $pub->setPrixVente(0.0);
        $this->manager->validatePublication($pub);
    }

    public function testVenteObjetWithPrixVenteOver1000ThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/prix/i');
        $pub = $this->makeValidPublication();
        $pub->setTypePublication('VENTE_OBJET');
        $pub->setPrixVente(1500.0);
        $this->manager->validatePublication($pub);
    }

    public function testValidVenteObjetReturnsTrue(): void
    {
        $pub = $this->makeValidPublication();
        $pub->setTypePublication('VENTE_OBJET');
        $pub->setPrixVente(150.0);
        $this->assertTrue($this->manager->validatePublication($pub));
    }
}