<?php

namespace App\Service;

use App\Entity\Publication;
use App\Entity\Service;

class ModerationTrustService
{
    private const BAD_THRESHOLD = 45;

    /**
     * @return array{score:int,isBad:bool,label:string,reasons:array<int,string>}
     */
    public function evaluateService(Service $service): array
    {
        $score = 100;
        $reasons = [];

        $trustPoints = $service->getUser()?->getTrustPoints();
        if ($trustPoints === null) {
            $score -= 8;
            $reasons[] = 'Le prestataire n\'a pas encore de points de confiance.';
        } elseif ($trustPoints < 20) {
            $score -= 25;
            $reasons[] = 'Le prestataire a un niveau de confiance très faible.';
        } elseif ($trustPoints < 50) {
            $score -= 10;
            $reasons[] = 'Le prestataire a un niveau de confiance moyen.';
        } elseif ($trustPoints >= 80) {
            $score += 5;
        }

        $title = trim($service->getTitle());
        $description = trim((string) $service->getDescription());
        $content = mb_strtolower(trim($title . ' ' . $description));
        $price = (float) $service->getPrice();

        if (mb_strlen($title) < 6) {
            $score -= 15;
            $reasons[] = 'Le titre du service est trop court.';
        }

        if ($description === '') {
            $score -= 25;
            $reasons[] = 'La description du service est absente.';
        } elseif (mb_strlen($description) < 40) {
            $score -= 15;
            $reasons[] = 'La description du service est trop courte.';
        }

        if ($service->getCategory() === null) {
            $score -= 15;
            $reasons[] = 'La catégorie du service est manquante.';
        }

        if ($price <= 0) {
            $score -= 40;
            $reasons[] = 'Le prix du service est invalide.';
        } elseif ($price > 500) {
            $score -= 20;
            $reasons[] = 'Le prix du service est anormalement élevé.';
        } elseif ($price > 250) {
            $score -= 10;
            $reasons[] = 'Le prix du service est élevé pour la plateforme.';
        }

        if ($service->getStatus() === 'REFUSEE') {
            $score -= 10;
            $reasons[] = 'Le service a déjà été refusé en modération.';
        }

        foreach ($this->findSuspiciousKeywords($content) as $keyword) {
            $score -= 12;
            $reasons[] = 'Mot-clé sensible détecté : « ' . $keyword . ' ». ';
        }

        return $this->finalize($score, $reasons);
    }

    /**
     * @return array{score:int,isBad:bool,label:string,reasons:array<int,string>}
     */
    public function evaluatePublication(Publication $publication): array
    {
        $score = 100;
        $reasons = [];

        $trustPoints = $publication->getUser()?->getTrustPoints();
        if ($trustPoints === null) {
            $score -= 8;
            $reasons[] = 'L\'auteur n\'a pas encore de points de confiance.';
        } elseif ($trustPoints < 20) {
            $score -= 25;
            $reasons[] = 'L\'auteur a un niveau de confiance très faible.';
        } elseif ($trustPoints < 50) {
            $score -= 10;
            $reasons[] = 'L\'auteur a un niveau de confiance moyen.';
        } elseif ($trustPoints >= 80) {
            $score += 5;
        }

        $title = trim($publication->getTitre());
        $message = trim($publication->getMessage());
        $content = mb_strtolower(trim($title . ' ' . $message));
        $price = $publication->getTypePublication() === 'VENTE_OBJET'
            ? $publication->getPrixVente()
            : $publication->getProposedPrice();

        if (mb_strlen($title) < 6) {
            $score -= 15;
            $reasons[] = 'Le titre de la publication est trop court.';
        }

        if ($message === '') {
            $score -= 30;
            $reasons[] = 'La description de la publication est absente.';
        } elseif (mb_strlen($message) < 40) {
            $score -= 15;
            $reasons[] = 'La description de la publication est trop courte.';
        }

        if ($publication->getCategory() === null) {
            $score -= 12;
            $reasons[] = 'La catégorie de la publication est manquante.';
        }

        if ($publication->getLocalisation() === null || trim((string) $publication->getLocalisation()) === '') {
            $score -= 8;
            $reasons[] = 'La localisation de la publication est manquante.';
        }

        if ($publication->getTypePublication() === 'VENTE_OBJET' && ($price === null || (float) $price <= 0)) {
            $score -= 25;
            $reasons[] = 'Le prix de vente est absent ou invalide.';
        } elseif ($price !== null && (float) $price > 1000) {
            $score -= 15;
            $reasons[] = 'Le prix proposé paraît anormalement élevé.';
        }

        if ($publication->getStatus() === 'ANNULEE') {
            $score -= 8;
            $reasons[] = 'La publication a déjà été annulée.';
        }

        foreach ($this->findSuspiciousKeywords($content) as $keyword) {
            $score -= 12;
            $reasons[] = 'Mot-clé sensible détecté : « ' . $keyword . ' ». ';
        }

        return $this->finalize($score, $reasons);
    }

    /**
     * @param Service[] $services
     * @return array<int, array{score:int,isBad:bool,label:string,reasons:array<int,string>}>
     */
    public function evaluateServices(array $services): array
    {
        $rows = [];
        foreach ($services as $service) {
            if ($service->getId() === null) {
                continue;
            }
            $rows[$service->getId()] = $this->evaluateService($service);
        }

        return $rows;
    }

    /**
     * @param Publication[] $publications
     * @return array<int, array{score:int,isBad:bool,label:string,reasons:array<int,string>}>
     */
    public function evaluatePublications(array $publications): array
    {
        $rows = [];
        foreach ($publications as $publication) {
            if ($publication->getId() === null) {
                continue;
            }
            $rows[$publication->getId()] = $this->evaluatePublication($publication);
        }

        return $rows;
    }

    /**
     * @param array<int,string> $reasons
     * @return array{score:int,isBad:bool,label:string,reasons:array<int,string>}
     */
    private function finalize(int $score, array $reasons): array
    {
        $score = max(0, min(100, $score));
        $reasons = array_values(array_unique(array_map(static fn (string $reason): string => trim($reason), $reasons)));
        $isBad = $score < self::BAD_THRESHOLD;

        return [
            'score' => $score,
            'isBad' => $isBad,
            'label' => $isBad ? 'Mauvais' : 'Correct',
            'reasons' => $reasons,
        ];
    }

    /**
     * @return array<int,string>
     */
    private function findSuspiciousKeywords(string $content): array
    {
        $keywords = ['arnaque', 'fraude', 'spam', 'fake', 'bitcoin', 'casino', 'adult', 'urgent', 'gain facile'];
        $found = [];

        foreach ($keywords as $keyword) {
            if (str_contains($content, $keyword)) {
                $found[] = $keyword;
            }
        }

        return $found;
    }
}
