<?php

namespace App\Controller\Prestataire;

use App\Repository\ReviewRepository;
use App\Service\ReviewsBadgeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/tutor/stats', name: 'tutor_stats_')]
class TutorReviewsStatsController extends AbstractController
{
    public function __construct(
        private ReviewRepository       $repo,
        private EntityManagerInterface $em,
        private ReviewsBadgeService          $badgeService
    ) {}

    private function getCurrentTutor(): \App\Entity\User
    {
        $user = $this->getUser();
        
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour accéder à cette page.');
        }

        if ($user->getUserType() !== 'PRESTATAIRE') {
            throw $this->createAccessDeniedException('Accès réservé aux prestataires.');
        }

        return $user;
    }

    #[Route('/', name: 'index')]
    public function index(): Response
    {
        $user = $this->getCurrentTutor();
        $allReviews = $this->repo->findByTutorWithDetails($user->getId());
        
        $trustPoints = (int) $this->em->getConnection()
            ->fetchOne('SELECT trust_points FROM users WHERE id = ?', [$user->getId()]);

        $avgRating = count($allReviews) > 0
            ? round(array_sum(array_map(fn($r) => $r->getRating() ?? 0, $allReviews)) / count($allReviews), 2)
            : 0;

        // Badges
        $badge = $this->badgeService->getBadge($trustPoints, $avgRating);
        $allBadges = $this->badgeService->getAllBadges($trustPoints, $avgRating, count($allReviews), 0);
        $progress = $this->badgeService->getProgressToNextBadge($trustPoints, $avgRating);

        // Distribution des notes
        $ratingDistribution = [
            '5' => 0, '4' => 0, '3' => 0, '2' => 0, '1' => 0,
            '-1' => 0, '-2' => 0, '-3' => 0, '-4' => 0, '-5' => 0,
        ];
        
        foreach ($allReviews as $review) {
            $rating = (string) ($review->getRating() ?? 0);
            if (isset($ratingDistribution[$rating])) {
                $ratingDistribution[$rating]++;
            }
        }

        // Tendance des performances (10 derniers avis vs 10 premiers avis)
        $recentReviews = array_slice($allReviews, -10); // 10 derniers
        $oldReviews = array_slice($allReviews, 0, min(10, count($allReviews))); // 10 premiers
        
        $recentAvg = count($recentReviews) > 0
            ? round(array_sum(array_map(fn($r) => $r->getRating() ?? 0, $recentReviews)) / count($recentReviews), 2)
            : 0;
            
        $oldAvg = count($oldReviews) > 0
            ? round(array_sum(array_map(fn($r) => $r->getRating() ?? 0, $oldReviews)) / count($oldReviews), 2)
            : 0;
        
        $trend = $recentAvg - $oldAvg; // Positif = amélioration, Négatif = dégradation
        $trendPercent = $oldAvg != 0 ? round(($trend / abs($oldAvg)) * 100, 1) : 0;

        // Progression par tranches (tous les 5 avis)
        $progressionData = [];
        $chunkSize = max(5, ceil(count($allReviews) / 6)); // Diviser en 6 tranches max
        $chunks = array_chunk($allReviews, $chunkSize);
        
        foreach ($chunks as $index => $chunk) {
            $avg = count($chunk) > 0
                ? round(array_sum(array_map(fn($r) => $r->getRating() ?? 0, $chunk)) / count($chunk), 2)
                : 0;
            
            $progressionData[] = [
                'label' => 'Avis ' . (($index * $chunkSize) + 1) . '-' . (($index + 1) * $chunkSize),
                'avg' => $avg,
                'count' => count($chunk),
            ];
        }

        // Top services (basé sur les avis)
        $serviceStats = [];
        foreach ($allReviews as $review) {
            $serviceTitle = $review->getReservation()?->getService()?->getTitle() ?? 'Inconnu';
            
            if (!isset($serviceStats[$serviceTitle])) {
                $serviceStats[$serviceTitle] = [
                    'title' => $serviceTitle,
                    'count' => 0,
                    'totalRating' => 0,
                    'avgRating' => 0,
                ];
            }
            
            $serviceStats[$serviceTitle]['count']++;
            $serviceStats[$serviceTitle]['totalRating'] += $review->getRating() ?? 0;
        }

        foreach ($serviceStats as &$stat) {
            $stat['avgRating'] = $stat['count'] > 0
                ? round($stat['totalRating'] / $stat['count'], 1)
                : 0;
        }

        // Trier par nombre d'avis
        usort($serviceStats, fn($a, $b) => $b['count'] <=> $a['count']);
        $serviceStats = array_slice($serviceStats, 0, 5); // Top 5

        // Statistiques générales
        $positiveReviews = count(array_filter($allReviews, fn($r) => ($r->getRating() ?? 0) > 0));
        $negativeReviews = count(array_filter($allReviews, fn($r) => ($r->getRating() ?? 0) < 0));
        $neutralReviews = count(array_filter($allReviews, fn($r) => ($r->getRating() ?? 0) === 0));
        
        $reportedReviews = count(array_filter($allReviews, fn($r) => $r->isReported()));

        // Longueur moyenne des commentaires
        $avgCommentLength = count($allReviews) > 0
            ? round(array_sum(array_map(fn($r) => mb_strlen($r->getComment() ?? ''), $allReviews)) / count($allReviews))
            : 0;

        return $this->render('prestataire/TutorStats.html.twig', [
            'totalReviews' => count($allReviews),
            'trustPoints' => $trustPoints,
            'averageRating' => $avgRating,
            'badge' => $badge,
            'allBadges' => $allBadges,
            'progress' => $progress,
            'ratingDistribution' => $ratingDistribution,
            'progressionData' => $progressionData,
            'trend' => $trend,
            'trendPercent' => $trendPercent,
            'recentAvg' => $recentAvg,
            'oldAvg' => $oldAvg,
            'serviceStats' => $serviceStats,
            'positiveReviews' => $positiveReviews,
            'negativeReviews' => $negativeReviews,
            'neutralReviews' => $neutralReviews,
            'reportedReviews' => $reportedReviews,
            'avgCommentLength' => $avgCommentLength,
        ]);
    }
}
