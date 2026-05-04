<?php

namespace App\Service;

use App\Entity\Publication;
use App\Entity\Service;
use App\Repository\NotificationRepository;
use App\Repository\PublicationRepository;
use App\Repository\ServiceRepository;

class MatchingService
{
    private const THRESHOLD = 30;

    public function __construct(
        private readonly PublicationRepository $pubRepo,
        private readonly ServiceRepository $svcRepo,
        private readonly NotificationService $notif,
        private readonly NotificationRepository $notifRepo,
    ) {}

    public function analyseRecentPublications(): int
    {
        $pubs = $this->pubRepo->findRecentDemandeService();
        $svcs = $this->svcRepo->findActiveServices();
        $total = 0;

        $antiSpamSince = new \DateTime('-24 hours');

        foreach ($pubs as $pub) {
            foreach ($svcs as $svc) {
                $score = $this->score($pub, $svc);
                if ($score < self::THRESHOLD || $svc->getUser() === null) {
                    continue;
                }

                // Éviter les doublons : ne pas renvoyer si déjà notifié dans les 24 dernières heures
                $alreadyNotified = $this->notifRepo->hasRecentWithTitleContaining(
                    $svc->getUser(),
                    '🎯 Nouvelle demande compatible',
                    $antiSpamSince
                );

                if ($alreadyNotified) {
                    continue;
                }

                $this->notif->notifyInApp(
                    $svc->getUser(),
                    '🎯 Nouvelle demande compatible!',
                    sprintf(
                        '"%s" correspond à votre service "%s" – Score: %d%%',
                        $pub->getTitre(),
                        $svc->getTitle(),
                        (int) $score
                    )
                );
                $total++;
            }
        }

        return $total;
    }

    // Fix #1 & #2: replace `object` with concrete entity types so PHPStan
    // can resolve all method calls (getLocalisation, getTitre, getTitle, etc.)
    private function score(Publication $pub, Service $svc): float
    {
        return min(
            $this->scoreKeywords($pub, $svc)
            + $this->scoreCategory($pub, $svc)
            + $this->scorePrice($pub, $svc)
            + ($pub->getLocalisation() ? 5 : 2),
            100
        );
    }

    private function scoreKeywords(Publication $pub, Service $svc): float
    {
        $pt = strtolower($pub->getTitre() . ' ' . $pub->getMessage());
        $st = strtolower($svc->getTitle() . ' ' . ($svc->getDescription() ?? ''));

        if ($svc->getTitle() && str_contains($pt, strtolower($svc->getTitle()))) {
            return 50;
        }

        $pw = $this->keywords($pt);
        $sw = $this->keywords($st);
        if (!$pw || !$sw) {
            return 0;
        }

        $common = array_intersect($pw, $sw);
        if (!$common) {
            return 0;
        }

        return min((count($common) / max(count($pw), count($sw))) * 50, 50);
    }

    private function scoreCategory(Publication $pub, Service $svc): float
    {
        $pt = strtolower($pub->getTitre() . ' ' . $pub->getMessage());
        $cat = $svc->getCategory()?->getName();
        if (!$cat) {
            return 0;
        }

        if (str_contains($pt, strtolower($cat))) {
            return 25;
        }

        foreach ($this->categoryDict()[strtolower($cat)] ?? [] as $kw) {
            if (str_contains($pt, $kw)) {
                return 20;
            }
        }

        return 0;
    }

    private function scorePrice(Publication $pub, Service $svc): float
    {
        $p = (float) ($pub->getProposedPrice() ?? $pub->getPrixVente() ?? 0);
        $s = (float) $svc->getPrice();
        if ($p <= 0 || $s <= 0) {
            return 5;
        }

        $diff = abs($p - $s) / $s;

        return match (true) {
            $diff <= 0.10 => 15,
            $diff <= 0.20 => 12,
            $diff <= 0.30 => 8,
            $diff <= 0.50 => 4,
            default => 0,
        };
    }

    /**
     * @return list<string>
     */
    private function keywords(string $text): array
    {
        $stop = ['le', 'la', 'les', 'un', 'une', 'des', 'de', 'du', 'et', 'ou', 'pour', 'avec', 'dans'];

        return array_values(array_filter(
            preg_split('/[\s,;:.!?()\[\]\'\"]+/', $text) ?: [],
            fn(string $word) => strlen($word) > 2 && !in_array($word, $stop, true)
        ));
    }

    /**
     * @return array<string, list<string>>
     */
    private function categoryDict(): array
    {
        return [
            'programmation' => ['code', 'python', 'java', 'javascript', 'web', 'sql', 'html'],
            'mathématiques' => ['math', 'algèbre', 'calcul', 'équation', 'statistiques'],
            'physique' => ['mécanique', 'électricité', 'optique', 'thermodynamique'],
            'langues' => ['anglais', 'espagnol', 'grammaire', 'vocabulaire'],
            'cours' => ['aide', 'soutien', 'tutorat', 'leçon', 'formation'],
        ];
    }
}