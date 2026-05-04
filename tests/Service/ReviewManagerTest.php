<?php

namespace App\Tests\Service;

use App\Entity\Review;
use App\Service\ReviewManager;
use PHPUnit\Framework\TestCase;

class ReviewManagerTest extends TestCase
{
    private ReviewManager $manager;

    protected function setUp(): void
    {
        $this->manager = new ReviewManager();
    }

    // ==================== TESTS POSITIFS ====================

    /**
     * Règle métier : Une review valide doit être acceptée
     */
    public function testValidReview(): void
    {
        $review = new Review();
        $review->setRating(4);
        $review->setComment('Excellent service, très professionnel et à l\'écoute des besoins.');

        $this->assertTrue($this->manager->validate($review));
    }

    /**
     * Règle métier : Une review avec note négative valide doit être acceptée
     */
    public function testValidReviewWithNegativeRating(): void
    {
        $review = new Review();
        $review->setRating(-3);
        $review->setComment('Service décevant, manque de professionnalisme et de ponctualité.');

        $this->assertTrue($this->manager->validate($review));
    }

    // ==================== TESTS NÉGATIFS - COMMENTAIRE ====================

    /**
     * Règle métier : Le commentaire est obligatoire
     */
    public function testReviewWithoutComment(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le commentaire est obligatoire');

        $review = new Review();
        $review->setRating(4);
        // Pas de commentaire

        $this->manager->validate($review);
    }

    /**
     * Règle métier : Le commentaire doit contenir au moins 10 caractères
     */
    public function testReviewWithTooShortComment(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le commentaire doit contenir au moins 10 caractères');

        $review = new Review();
        $review->setRating(4);
        $review->setComment('Court');

        $this->manager->validate($review);
    }

    /**
     * Règle métier : Le commentaire ne peut pas dépasser 1000 caractères
     */
    public function testReviewWithTooLongComment(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le commentaire ne peut pas dépasser 1000 caractères');

        $review = new Review();
        $review->setRating(4);
        $review->setComment(str_repeat('a', 1001));

        $this->manager->validate($review);
    }

    /**
     * Règle métier : Le commentaire ne peut pas contenir de caractères répétés excessivement
     */
    public function testReviewWithExcessiveRepeatedCharacters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le commentaire ne peut pas contenir de caractères répétés excessivement');

        $review = new Review();
        $review->setRating(4);
        $review->setComment('Aaaaaaaaaaaaaa service');

        $this->manager->validate($review);
    }

    /**
     * Règle métier : Le commentaire doit contenir au moins quelques lettres
     */
    public function testReviewWithOnlyNumbers(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le commentaire doit contenir au moins quelques lettres');

        $review = new Review();
        $review->setRating(4);
        $review->setComment('123456789012345');

        $this->manager->validate($review);
    }

    // ==================== TESTS NÉGATIFS - RATING ====================

    /**
     * Règle métier : La note est obligatoire
     */
    public function testReviewWithoutRating(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La note est obligatoire');

        $review = new Review();
        $review->setComment('Excellent service, très professionnel et à l\'écoute.');
        // Pas de rating

        $this->manager->validate($review);
    }

    /**
     * Règle métier : La note ne peut pas être 0
     */
    public function testReviewWithZeroRating(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La note ne peut pas être 0');

        $review = new Review();
        $review->setRating(0);
        $review->setComment('Excellent service, très professionnel et à l\'écoute.');

        $this->manager->validate($review);
    }

    /**
     * Règle métier : La note doit être comprise entre -5 et 5
     */
    public function testReviewWithRatingTooLow(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La note doit être comprise entre -5 et 5');

        $review = new Review();
        $review->setRating(-6);
        $review->setComment('Excellent service, très professionnel et à l\'écoute.');

        $this->manager->validate($review);
    }

    /**
     * Règle métier : La note doit être comprise entre -5 et 5
     */
    public function testReviewWithRatingTooHigh(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La note doit être comprise entre -5 et 5');

        $review = new Review();
        $review->setRating(6);
        $review->setComment('Excellent service, très professionnel et à l\'écoute.');

        $this->manager->validate($review);
    }
}
