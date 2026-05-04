<?php

namespace App\Service;

class ServiceValidator
{
    private const MIN_TITLE_LENGTH       = 5;
    private const MIN_DESCRIPTION_LENGTH = 20;
    private const MAX_PRICE              = 500.0;

    // ── Règle 1 & (implicite) : titre ────────────────────────────────────

    public function validateTitle(string $title): bool
    {
        if (empty(trim($title)) || strlen(trim($title)) < self::MIN_TITLE_LENGTH) {
            throw new \InvalidArgumentException(
                'Le titre est obligatoire et doit comporter au moins 5 caractères.'
            );
        }
        return true;
    }

    // ── Règles 2 & 3 : prix ───────────────────────────────────────────────

    public function validatePrice(float $price): bool
    {
        if ($price <= 0) {
            throw new \InvalidArgumentException(
                'Le prix doit être strictement positif.'
            );
        }

        if ($price > self::MAX_PRICE) {
            throw new \InvalidArgumentException(
                sprintf('Le prix ne peut pas dépasser %d €.', self::MAX_PRICE)
            );
        }

        return true;
    }

    // ── Règle 4 : description ─────────────────────────────────────────────

    public function validateDescription(string $description): bool
    {
        if (empty(trim($description)) || strlen(trim($description)) < self::MIN_DESCRIPTION_LENGTH) {
            throw new \InvalidArgumentException(
                'La description est obligatoire et doit comporter au moins 20 caractères.'
            );
        }
        return true;
    }

    // ── Règle 5 : type utilisateur ────────────────────────────────────────

    public function validateUserType(string $userType): bool
    {
        if ($userType !== 'PRESTATAIRE') {
            throw new \InvalidArgumentException(
                'Seul un prestataire peut créer un service.'
            );
        }
        return true;
    }
}