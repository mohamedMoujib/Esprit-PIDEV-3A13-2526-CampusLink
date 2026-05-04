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

    // ==================== TESTS : getBadge() ====================

    /**
     * Règle métier : 200+ trust points ET note ≥ 4.5 → badge "Élite".
     */
    public function testGetBadgeEliteWithExactThreshold(): void
    {
        $badge = $this->service->getBadge(200, 4.5);

        $this->assertNotNull($badge);
        $this->assertEquals('elite', $badge['key']);
        $this->assertEquals('Élite', $badge['name']);
    }

    /**
     * Règle métier : 200+ trust points mais note < 4.5 → pas de badge Élite.
     */
    public function testGetBadgeEliteNotGrantedWhenRatingTooLow(): void
    {
        $badge = $this->service->getBadge(200, 4.4);

        $this->assertNotNull($badge);
        $this->assertNotEquals('elite', $badge['key'], 'La note insuffisante empêche le badge Élite.');
    }

    /**
     * Règle métier : 150+ trust points ET note ≥ 4.0 → badge "Expert".
     */
    public function testGetBadgeExpert(): void
    {
        $badge = $this->service->getBadge(150, 4.0);

        $this->assertNotNull($badge);
        $this->assertEquals('expert', $badge['key']);
        $this->assertEquals('Expert', $badge['name']);
    }

    /**
     * Règle métier : 100+ trust points ET note ≥ 3.5 → badge "Professionnel".
     */
    public function testGetBadgePro(): void
    {
        $badge = $this->service->getBadge(100, 3.5);

        $this->assertNotNull($badge);
        $this->assertEquals('pro', $badge['key']);
        $this->assertEquals('Professionnel', $badge['name']);
    }

    /**
     * Règle métier : 50+ trust points ET note ≥ 3.0 → badge "Vérifié".
     */
    public function testGetBadgeVerified(): void
    {
        $badge = $this->service->getBadge(50, 3.0);

        $this->assertNotNull($badge);
        $this->assertEquals('verified', $badge['key']);
        $this->assertEquals('Vérifié', $badge['name']);
    }

    /**
     * Règle métier : 20+ trust points ET note ≥ 3.0 → badge "Étoile montante".
     */
    public function testGetBadgeRising(): void
    {
        $badge = $this->service->getBadge(20, 3.0);

        $this->assertNotNull($badge);
        $this->assertEquals('rising', $badge['key']);
        $this->assertEquals('Étoile montante', $badge['name']);
    }

    /**
     * Règle métier : Un tuteur avec très peu de points et une note basse n'obtient aucun badge.
     */
    public function testGetBadgeReturnsNullWhenInsufficientTrustPoints(): void
    {
        $badge = $this->service->getBadge(10, 5.0);
        $this->assertNull($badge, 'Un tuteur avec seulement 10 trust points ne doit pas avoir de badge.');
    }

    /**
     * Règle métier : Un tuteur avec 0 trust points et une note parfaite n'obtient aucun badge.
     */
    public function testGetBadgeReturnsNullForNewTutor(): void
    {
        $badge = $this->service->getBadge(0, 0.0);
        $this->assertNull($badge, 'Un tuteur sans points ni avis ne doit pas avoir de badge.');
    }

    /**
     * Règle métier : Un tuteur avec assez de points mais une note insuffisante est rétrogradé au badge inférieur.
     */
    public function testGetBadgeDemotesWhenRatingIsInsufficient(): void
    {
        // 150 points mais note 3.9 → pas Expert (4.0 requis), mais Pro (3.5 requis) si ≥ 100 pts
        $badge = $this->service->getBadge(150, 3.9);

        $this->assertNotNull($badge);
        $this->assertEquals('pro', $badge['key'], 'Avec 150 pts et une note de 3.9, le badge Pro doit être attribué.');
    }

    // ==================== TESTS : getAllBadges() ====================

    /**
     * Règle métier : Un tuteur avec 20+ avis obtient le badge "Plus populaire".
     */
    public function testGetAllBadgesGrantsMostReviewsBadgeWith20Reviews(): void
    {
        $badges    = $this->service->getAllBadges(200, 4.5, 20, 2);
        $badgeKeys = array_column($badges, 'key');

        $this->assertContains('most_reviews', $badgeKeys);
    }

    /**
     * Règle métier : Moins de 20 avis → pas de badge "Plus populaire".
     */
    public function testGetAllBadgesDoesNotGrantMostReviewsBadgeBelow20Reviews(): void
    {
        $badges    = $this->service->getAllBadges(200, 4.5, 19, 2);
        $badgeKeys = array_column($badges, 'key');

        $this->assertNotContains('most_reviews', $badgeKeys);
    }

    /**
     * Règle métier : Rang 1 ET note ≥ 4.0 → badge "Meilleure note".
     */
    public function testGetAllBadgesGrantsTopRatedBadgeForRank1(): void
    {
        $badges    = $this->service->getAllBadges(200, 4.5, 5, 1);
        $badgeKeys = array_column($badges, 'key');

        $this->assertContains('top_rated', $badgeKeys);
    }

    /**
     * Règle métier : Rang 2 → pas de badge "Meilleure note", même avec une excellente note.
     */
    public function testGetAllBadgesDoesNotGrantTopRatedBadgeForRank2(): void
    {
        $badges    = $this->service->getAllBadges(200, 5.0, 5, 2);
        $badgeKeys = array_column($badges, 'key');

        $this->assertNotContains('top_rated', $badgeKeys);
    }

    /**
     * Règle métier : Rang 1 mais note < 4.0 → pas de badge "Meilleure note".
     */
    public function testGetAllBadgesDoesNotGrantTopRatedWhenRatingTooLow(): void
    {
        $badges    = $this->service->getAllBadges(200, 3.9, 5, 1);
        $badgeKeys = array_column($badges, 'key');

        $this->assertNotContains('top_rated', $badgeKeys);
    }

    /**
     * Règle métier : 10+ avis ET note ≥ 4.0 → badge "Régulier".
     */
    public function testGetAllBadgesGrantsConsistentBadge(): void
    {
        $badges    = $this->service->getAllBadges(200, 4.5, 10, 2);
        $badgeKeys = array_column($badges, 'key');

        $this->assertContains('consistent', $badgeKeys);
    }

    /**
     * Règle métier : Moins de 10 avis → pas de badge "Régulier".
     */
    public function testGetAllBadgesDoesNotGrantConsistentBadgeBelow10Reviews(): void
    {
        $badges    = $this->service->getAllBadges(200, 4.5, 9, 2);
        $badgeKeys = array_column($badges, 'key');

        $this->assertNotContains('consistent', $badgeKeys);
    }

    /**
     * Règle métier : 10+ avis mais note < 4.0 → pas de badge "Régulier".
     */
    public function testGetAllBadgesDoesNotGrantConsistentBadgeWhenRatingTooLow(): void
    {
        $badges    = $this->service->getAllBadges(200, 3.9, 10, 2);
        $badgeKeys = array_column($badges, 'key');

        $this->assertNotContains('consistent', $badgeKeys);
    }

    /**
     * Règle métier : Un tuteur sans critères spéciaux obtient au moins son badge principal.
     */
    public function testGetAllBadgesAlwaysIncludesMainBadgeWhenEligible(): void
    {
        $badges    = $this->service->getAllBadges(200, 4.5, 5, 3);
        $badgeKeys = array_column($badges, 'key');

        $this->assertContains('elite', $badgeKeys);
    }

    /**
     * Règle métier : Un tuteur sans aucun critère ne reçoit aucun badge.
     */
    public function testGetAllBadgesReturnsEmptyArrayForNewTutor(): void
    {
        $badges = $this->service->getAllBadges(0, 0.0, 0, 10);
        $this->assertEmpty($badges, 'Un nouveau tuteur sans critères ne doit recevoir aucun badge.');
    }

    // ==================== TESTS : getProgressToNextBadge() ====================

    /**
     * Règle métier : Un tuteur au niveau maximum (Élite) n'a pas de progression à afficher.
     */
    public function testGetProgressToNextBadgeReturnsNullForEliteTutor(): void
    {
        $progress = $this->service->getProgressToNextBadge(200, 4.5);
        $this->assertNull($progress, 'Un tuteur Élite est au niveau maximum, pas de progression.');
    }

    /**
     * Règle métier : Un tuteur sans badge a une progression vers le premier badge.
     */
    public function testGetProgressToNextBadgeReturnsDataForTutorWithNoBadge(): void
    {
        $progress = $this->service->getProgressToNextBadge(5, 2.0);

        $this->assertNotNull($progress);
        $this->assertArrayHasKey('nextBadge', $progress);
        $this->assertArrayHasKey('trustPointsNeeded', $progress);
        $this->assertArrayHasKey('ratingNeeded', $progress);
        $this->assertArrayHasKey('trustPointsProgress', $progress);
        $this->assertArrayHasKey('ratingProgress', $progress);
    }

    /**
     * Règle métier : La progression en trust points est plafonnée à 100 %.
     */
    public function testGetProgressToNextBadgeTrustPointsProgressCappedAt100(): void
    {
        // Un tuteur "Rising" (20 pts, 3.0 avg) visant "Verified" (50 pts, 3.0 avg)
        // → il a assez de points pour "Rising" mais pas pour "Verified"
        $progress = $this->service->getProgressToNextBadge(20, 3.0);

        if ($progress !== null) {
            $this->assertLessThanOrEqual(
                100,
                $progress['trustPointsProgress'],
                'La progression en trust points ne doit jamais dépasser 100 %.'
            );
        } else {
            $this->markTestSkipped('Le tuteur est déjà au niveau maximum.');
        }
    }

    /**
     * Règle métier : Les trust points nécessaires sont un entier positif ou nul.
     */
    public function testGetProgressToNextBadgeTrustPointsNeededIsNonNegative(): void
    {
        $progress = $this->service->getProgressToNextBadge(30, 2.5);

        if ($progress !== null) {
            $this->assertGreaterThanOrEqual(
                0,
                $progress['trustPointsNeeded'],
                'Le nombre de trust points nécessaires ne peut pas être négatif.'
            );
        } else {
            $this->markTestSkipped('Le tuteur est déjà au niveau maximum.');
        }
    }

    /**
     * Règle métier : Un tuteur "Expert" a une progression vers "Élite".
     */
    public function testGetProgressToNextBadgeFromExpertToElite(): void
    {
        // Expert : 150 pts, 4.0 avg → prochain = Élite (200 pts, 4.5)
        $progress = $this->service->getProgressToNextBadge(150, 4.0);

        $this->assertNotNull($progress);
        $this->assertEquals('elite', $progress['nextBadge']['key']);
        $this->assertEquals(50, $progress['trustPointsNeeded']); // 200 - 150
    }

    // ==================== TESTS : getBadgeRank() ====================

    /**
     * Règle métier : Le badge "Élite" est de rang 1 (le meilleur).
     */
    public function testGetBadgeRankEliteIsRank1(): void
    {
        $rank = $this->service->getBadgeRank('elite');
        $this->assertEquals(1, $rank);
    }

    /**
     * Règle métier : Le badge "Expert" est de rang 2.
     */
    public function testGetBadgeRankExpertIsRank2(): void
    {
        $rank = $this->service->getBadgeRank('expert');
        $this->assertEquals(2, $rank);
    }

    /**
     * Règle métier : Le badge "Professionnel" est de rang 3.
     */
    public function testGetBadgeRankProIsRank3(): void
    {
        $rank = $this->service->getBadgeRank('pro');
        $this->assertEquals(3, $rank);
    }

    /**
     * Règle métier : Le badge "Vérifié" est de rang 4.
     */
    public function testGetBadgeRankVerifiedIsRank4(): void
    {
        $rank = $this->service->getBadgeRank('verified');
        $this->assertEquals(4, $rank);
    }

    /**
     * Règle métier : Le badge "Étoile montante" est de rang 5.
     */
    public function testGetBadgeRankRisingIsRank5(): void
    {
        $rank = $this->service->getBadgeRank('rising');
        $this->assertEquals(5, $rank);
    }

    /**
     * Règle métier : Un badge inconnu ou null est de rang 6 (hors classement).
     */
    public function testGetBadgeRankUnknownBadgeIsRank6(): void
    {
        $this->assertEquals(6, $this->service->getBadgeRank(null));
        $this->assertEquals(6, $this->service->getBadgeRank('inexistant'));
    }

    /**
     * Règle métier : L'ordre des rangs est cohérent (Élite < Expert < Pro < Verified < Rising).
     */
    public function testBadgeRanksAreInOrder(): void
    {
        $this->assertLessThan(
            $this->service->getBadgeRank('expert'),
            $this->service->getBadgeRank('elite'),
            'Élite doit avoir un rang inférieur (meilleur) à Expert.'
        );
        $this->assertLessThan(
            $this->service->getBadgeRank('pro'),
            $this->service->getBadgeRank('expert')
        );
        $this->assertLessThan(
            $this->service->getBadgeRank('verified'),
            $this->service->getBadgeRank('pro')
        );
        $this->assertLessThan(
            $this->service->getBadgeRank('rising'),
            $this->service->getBadgeRank('verified')
        );
    }
}