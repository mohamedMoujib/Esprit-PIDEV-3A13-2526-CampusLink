<?php

namespace App\Service;

use App\Entity\Publication;
use App\Entity\Service as ServiceEntity;

class ModerationValidator
{
    private const MIN_TRUST_POINTS      = 20;
    private const MIN_TITLE_LENGTH      = 6;
    private const MIN_CONTENT_LENGTH    = 40;
    private const MAX_SERVICE_PRICE     = 500.0;
    private const MAX_PUBLICATION_PRICE = 1000.0;

    public function validateService(ServiceEntity $service): bool
    {
        if (strlen($service->getTitle()) < self::MIN_TITLE_LENGTH) {
            throw new \InvalidArgumentException(
                'Le titre du service doit comporter au moins 6 caractères.'
            );
        }

        if (empty($service->getDescription())) {
            throw new \InvalidArgumentException(
                'La description du service ne peut pas être vide.'
            );
        }

        if (strlen($service->getDescription()) < self::MIN_CONTENT_LENGTH) {
            throw new \InvalidArgumentException(
                'La description du service doit comporter au moins 40 caractères.'
            );
        }

        if ($service->getCategory() === null) {
            throw new \InvalidArgumentException(
                'La catégorie du service est obligatoire.'
            );
        }

        if ($service->getPrice() <= 0) {
            throw new \InvalidArgumentException(
                'Le prix du service doit être strictement positif.'
            );
        }

        if ($service->getPrice() > self::MAX_SERVICE_PRICE) {
            throw new \InvalidArgumentException(
                'Le prix du service ne peut pas dépasser 500 €.'
            );
        }

        $user = $service->getUser();
        if ($user === null || $user->getTrustPoints() < self::MIN_TRUST_POINTS) {
            throw new \InvalidArgumentException(
                'L\'utilisateur doit posséder au moins 20 points de confiance.'
            );
        }

        return true;
    }

    public function validatePublication(Publication $publication): bool
    {
        if (strlen($publication->getTitre()) < self::MIN_TITLE_LENGTH) {
            throw new \InvalidArgumentException(
                'Le titre de la publication doit comporter au moins 6 caractères.'
            );
        }

        if (empty($publication->getMessage())) {
            throw new \InvalidArgumentException(
                'Le message de la publication ne peut pas être vide.'
            );
        }

        if (strlen($publication->getMessage()) < self::MIN_CONTENT_LENGTH) {
            throw new \InvalidArgumentException(
                'Le message de la publication doit comporter au moins 40 caractères.'
            );
        }

        if ($publication->getCategory() === null) {
            throw new \InvalidArgumentException(
                'La catégorie de la publication est obligatoire.'
            );
        }

        $localisation = $publication->getLocalisation();
        if ($localisation === null || empty(trim($localisation))) {
            throw new \InvalidArgumentException(
                'La localisation de la publication est obligatoire.'
            );
        }

        if ($publication->getTypePublication() === 'VENTE_OBJET') {
            $prix = $publication->getPrixVente();

            if ($prix === null || $prix <= 0) {
                throw new \InvalidArgumentException(
                    'Le prix de vente est obligatoire et doit être strictement positif.'
                );
            }

            if ($prix > self::MAX_PUBLICATION_PRICE) {
                throw new \InvalidArgumentException(
                    'Le prix de vente ne peut pas dépasser 1 000 €.'
                );
            }
        }

        return true;
    }
}