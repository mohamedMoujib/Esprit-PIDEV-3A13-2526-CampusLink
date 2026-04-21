<?php

namespace App\Controller\Admin;

use App\Service\TrendPredictionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/trends')]
#[IsGranted('ROLE_ADMIN')]
class TrendController extends AbstractController
{
    public function __construct(
        private readonly TrendPredictionService $trends,
    ) {}

    #[Route('', name: 'admin_trends', methods: ['GET'])]
    public function index(): Response
    {
        $trending = $this->trends->predictTrendingCategories(false);
        $stats = $this->trends->getSimpleStats();
        foreach ($trending as $i => $row) {
            $name = $row['category'] ?? '';
            $trending[$i]['publicationCount'] = $stats['byCategory'][$name] ?? 0;
        }

        $topCategory = $stats['topCategory'] ?? '—';
        $topScore = 0.5;
        if ($trending !== []) {
            $topCategory = (string) ($trending[0]['category'] ?? $topCategory);
            $topScore = (float) ($trending[0]['score'] ?? 0.5);
        }
        $weeklyData = $this->trends->getWeeklyTrendDataAllCategories($trending);

        $hausseCount = 0;
        foreach ($trending as $row) {
            if (($row['trend'] ?? '') === 'hausse') {
                ++$hausseCount;
            }
        }

        $avgScorePercent = 0;
        if ($trending !== []) {
            $scoreSum = array_sum(array_map(
                static fn (array $row): float => (float) ($row['score'] ?? 0),
                $trending
            ));
            $avgScorePercent = (int) round(($scoreSum / count($trending)) * 100);
        }
        $stats['avgScorePercent'] = $avgScorePercent;

        return $this->render('admin/pages/trends.html.twig', [
            'trending' => $trending,
            'weeklyData' => $weeklyData,
            'stats' => $stats,
            'topCategory' => $topCategory,
            'hausseCount' => $hausseCount,
        ]);
    }

    #[Route('/notify', name: 'admin_trends_notify', methods: ['POST'])]
    public function notify(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('trends_notify', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('admin_trends');
        }

        $fresh = $this->trends->predictTrendingCategories(true);
        $n = $this->trends->notifyPrestatairesOnTrend($fresh);
        $this->addFlash('success', "{$n} notification(s) envoyée(s) aux prestataires concernés.");

        return $this->redirectToRoute('admin_trends');
    }

    #[Route('/refresh', name: 'admin_trends_refresh', methods: ['POST'])]
    public function refresh(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('trends_refresh', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('admin_trends');
        }

        $this->trends->predictTrendingCategories(true);
        $this->addFlash('success', 'Tendances actualisées.');

        return $this->redirectToRoute('admin_trends');
    }
}
