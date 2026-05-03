<?php

namespace App\Service;

use App\Entity\Review;

class ReviewManager
{
    public const MAX_COMMENT_LENGTH = 1000;
    public const MIN_COMMENT_LENGTH = 10;
    public const MIN_RATING = -5;
    public const MAX_RATING = 5;

    /**
     * Valide une review selon les règles métier
     * 
     * @param Review $review
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function validate(Review $review): bool
    {
        // Règle métier 1 : Le commentaire est obligatoire
        if (empty($review->getComment())) {
            throw new \InvalidArgumentException('Le commentaire est obligatoire');
        }

        // Règle métier 2 : Le commentaire doit avoir une longueur minimale
        if (mb_strlen($review->getComment()) < self::MIN_COMMENT_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('Le commentaire doit contenir au moins %d caractères', self::MIN_COMMENT_LENGTH)
            );
        }

        // Règle métier 3 : Le commentaire ne peut pas dépasser la longueur maximale
        if (mb_strlen($review->getComment()) > self::MAX_COMMENT_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('Le commentaire ne peut pas dépasser %d caractères', self::MAX_COMMENT_LENGTH)
            );
        }

        // Règle métier 4 : Le commentaire ne peut pas contenir de caractères répétés excessivement
        if (preg_match('/(.)\1{9,}/', $review->getComment())) {
            throw new \InvalidArgumentException('Le commentaire ne peut pas contenir de caractères répétés excessivement');
        }

        // Règle métier 5 : Le commentaire doit contenir au moins quelques lettres
        if (!preg_match('/[a-zA-ZÀ-ÿ]/', $review->getComment())) {
            throw new \InvalidArgumentException('Le commentaire doit contenir au moins quelques lettres');
        }

        // Règle métier 6 : La note est obligatoire
        if ($review->getRating() === null) {
            throw new \InvalidArgumentException('La note est obligatoire');
        }

        // Règle métier 7 : La note doit être dans la plage autorisée
        if ($review->getRating() < self::MIN_RATING || $review->getRating() > self::MAX_RATING) {
            throw new \InvalidArgumentException(
                sprintf('La note doit être comprise entre %d et %d', self::MIN_RATING, self::MAX_RATING)
            );
        }

        // Règle métier 8 : La note ne peut pas être 0
        if ($review->getRating() === 0) {
            throw new \InvalidArgumentException('La note ne peut pas être 0. Choisissez une note positive ou négative');
        }

        return true;
    }
}
