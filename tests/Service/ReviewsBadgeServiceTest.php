<?php

namespace App\Tests\Service;

use App\Service\ReviewsBadgeService;
use PHPUnit\Framework\TestCase;

class ReviewsBadgeServiceTest extends TestCase
{
    private ReviewsBadgeService $service;

    protected function setUp(): void
    {
        $this->service = new ReviewsBadgeService();
    }

    // ==================== TESTS POUR getBadge() ====================

    /**
     * Règle métier : Un tuteur avec 200+ trust points et 4.5+ de note obtient le badge "Élite"
     */
    public function testGetBadgeElite(): void
    {
        $badge = $this->service->getBadge(200, 4.5);
        
        $this->assertNotNull($badge);
        $this->assertEquals('elite', $badge['key']);
        $this->assertEquals('Élite', $badge['name']);
    }

    /**
     * Règle métier : Un tuteur avec moins de trust points que requis ne doit pas obtenir de badge
     */
    public function testGetBadgeInsufficientTrustPoints(): void
    {
        $badge = $this->service->getBadge(10, 5.0);
        
        $this->assertNull($badge);
    }

    // ==================== TESTS POUR getAllBadges() ====================

    /**
     * Règle métier : Un tuteur avec 20+ avis obtient le badge "Plus populaire"
     */
    public function testGetAllBadgesMostReviews(): void
    {
        $badges = $this->service->getAllBadges(200, 4.5, 20, 2);
        
        $badgeKeys = array_column($badges, 'key');
        $this->assertContains('most_reviews', $badgeKeys);
    }

    /**
     * Règle métier : Un tuteur avec moins de 20 avis ne doit pas obtenir le badge "Plus populaire"
     */
    public function testGetAllBadgesWithoutMostReviews(): void
    {
        $badges = $this->service->getAllBadges(200, 4.5, 19, 2);
        
        $badgeKeys = array_column($badges, 'key');
        $this->assertNotContains('most_reviews', $badgeKeys);
    }
}
