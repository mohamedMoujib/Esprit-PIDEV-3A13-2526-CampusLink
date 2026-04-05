<?php

namespace App\Service;

use App\Repository\PublicationRepository;
use App\Repository\ServiceRepository;

class MatchingService
{
    private const THRESHOLD = 30;

    public function __construct(
        private readonly PublicationRepository $pubRepo,
        private readonly ServiceRepository $svcRepo,
        private readonly NotificationService $notif,
    ) {}

    public function analyseRecentPublications(): int
    {
        $pubs = $this->pubRepo->findRecentDemandeService();
        $svcs = $this->svcRepo->findActiveServices();
        $total = 0;

        foreach ($pubs as $pub) {
            foreach ($svcs as $svc) {
                $score = $this->score($pub, $svc);
                if ($score >= self::THRESHOLD && $svc->getUser() !== null) {
                    $this->notif->notifyInApp(
                        $svc->getUser(),
                        '🎯 Nouvelle demande compatible!',
                        sprintf('"%s" correspond à votre service "%s" – Score: %d%%', $pub->getTitre(), $svc->getTitle(), (int) $score)
                    );
                    $total++;
                }
            }
        }

        return $total;
    }

    private function score(object $pub, object $svc): float
    {
        return min(
            $this->scoreKeywords($pub, $svc)
            + $this->scoreCategory($pub, $svc)
            + $this->scorePrice($pub, $svc)
            + ($pub->getLocalisation() ? 5 : 2),
            100
        );
    }

    private function scoreKeywords(object $pub, object $svc): float
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

    private function scoreCategory(object $pub, object $svc): float
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

    private function scorePrice(object $pub, object $svc): float
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

    private function keywords(string $text): array
    {
        $stop = ['le', 'la', 'les', 'un', 'une', 'des', 'de', 'du', 'et', 'ou', 'pour', 'avec', 'dans'];

        return array_values(array_filter(
            preg_split('/[\s,;:.!?()\[\]\'\"]+/', $text) ?: [],
            fn($word) => strlen($word) > 2 && !in_array($word, $stop, true)
        ));
    }

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
