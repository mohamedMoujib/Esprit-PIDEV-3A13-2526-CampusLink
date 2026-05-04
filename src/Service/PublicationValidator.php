<?php

namespace App\Service;

class PublicationValidator
{
    private const MIN_TITRE_LENGTH   = 5;
    private const MIN_MESSAGE_LENGTH = 20;
    private const MAX_PRIX_VENTE     = 1000.0;

    private const ALLOWED_TYPES = [
        'DEMANDE_SERVICE',
        'VENTE_OBJET',
        'DEMANDE',
    ];

    // ── Règle 1 : titre ───────────────────────────────────────────────────

    public function validateTitre(string $titre): bool
    {
        if (empty(trim($titre)) || strlen(trim($titre)) < self::MIN_TITRE_LENGTH) {
            throw new \InvalidArgumentException(
                'Le titre est obligatoire et doit comporter au moins 5 caractères.'
            );
        }
        return true;
    }

    // ── Règle 2 : message ─────────────────────────────────────────────────

    public function validateMessage(string $message): bool
    {
        if (empty(trim($message)) || strlen(trim($message)) < self::MIN_MESSAGE_LENGTH) {
            throw new \InvalidArgumentException(
                'Le message est obligatoire et doit comporter au moins 20 caractères.'
            );
        }
        return true;
    }

    // ── Règle 3 : type de publication ─────────────────────────────────────

    public function validateType(string $type): bool
    {
        if (empty($type) || !in_array($type, self::ALLOWED_TYPES, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Le type de publication "%s" est invalide. Valeurs autorisées : %s.',
                    $type,
                    implode(', ', self::ALLOWED_TYPES)
                )
            );
        }
        return true;
    }

    // ── Règle 4 : localisation ────────────────────────────────────────────

    public function validateLocalisation(?string $localisation): bool
    {
        if ($localisation === null || empty(trim($localisation))) {
            throw new \InvalidArgumentException(
                'La localisation est obligatoire.'
            );
        }
        return true;
    }

    // ── Règle 5 : prix de vente ───────────────────────────────────────────

    public function validatePrixVente(?float $prix, string $type): bool
    {
        if ($type !== 'VENTE_OBJET') {
            return true; // prix non requis pour les autres types
        }

        if ($prix === null || $prix <= 0) {
            throw new \InvalidArgumentException(
                'Le prix de vente est obligatoire et doit être strictement positif.'
            );
        }

        if ($prix > self::MAX_PRIX_VENTE) {
            throw new \InvalidArgumentException(
                sprintf('Le prix de vente ne peut pas dépasser %d €.', self::MAX_PRIX_VENTE)
            );
        }

        return true;
    }
}