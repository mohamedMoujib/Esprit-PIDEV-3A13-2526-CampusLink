<?php

namespace App\Controller;

use App\Repository\ReviewRepository;
use App\Repository\UserRepository;
use App\Service\ReviewsBadgeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/leaderboard', name: 'leaderboard_')]
class ReviewsLeaderboardController extends AbstractController
{
    public function __construct(
        private UserRepository   $userRepo,
        private ReviewRepository $reviewRepo,
        private ReviewsBadgeService     $badgeService
    ) {}

    #[Route('/', name: 'index')]
    public function index(Request $request): Response
    {
        // Récupérer tous les prestataires actifs avec au moins 1 avis
        $conn = $this->userRepo->getEntityManager()->getConnection();
        
        $sql = "
            SELECT 
                u.id,
                u.name,
                u.trust_points,
                COUNT(DISTINCT r.id) as review_count,
                AVG(r.rating) as avg_rating
            FROM users u
            INNER JOIN reviews r ON r.prestataire_id = u.id
            WHERE u.user_type = 'PRESTATAIRE' 
            AND u.status = 'ACTIVE'
            GROUP BY u.id, u.name, u.trust_points
            HAVING COUNT(DISTINCT r.id) > 0
            ORDER BY u.trust_points DESC, avg_rating DESC
        ";
        
        $results = $conn->fetchAllAssociative($sql);
        
        $leaderboard = [];
        foreach ($results as $index => $row) {
            $trustPoints = (int) ($row['trust_points'] ?? 0);
            $avgRating = round((float) $row['avg_rating'], 2);
            $reviewCount = (int) $row['review_count'];
            
            $badge = $this->badgeService->getBadge($trustPoints, $avgRating);
            
            $leaderboard[] = [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'trustPoints' => $trustPoints,
                'avgRating' => $avgRating,
                'reviewCount' => $reviewCount,
                'badge' => $badge,
                'rank' => $index + 1,
                'allBadges' => $this->badgeService->getAllBadges(
                    $trustPoints,
                    $avgRating,
                    $reviewCount,
                    $index + 1
                ),
            ];
        }

        // Statistiques globales
        $stats = [
            'totalTutors' => count($leaderboard),
            'avgTrustPoints' => count($leaderboard) > 0 
                ? round(array_sum(array_column($leaderboard, 'trustPoints')) / count($leaderboard), 1)
                : 0,
            'avgRating' => count($leaderboard) > 0
                ? round(array_sum(array_column($leaderboard, 'avgRating')) / count($leaderboard), 2)
                : 0,
            'totalReviews' => array_sum(array_column($leaderboard, 'reviewCount')),
        ];

        return $this->render('leaderboard/index.html.twig', [
            'leaderboard' => $leaderboard,
            'stats' => $stats,
        ]);
    }

    #[Route('/api/top', name: 'api_top', methods: ['GET'])]
    public function getTopTutors(Request $request): Response
    {
        $limit = min((int) $request->query->get('limit', 10), 50);

        $prestataires = $this->userRepo->findBy(
            ['userType' => 'PRESTATAIRE', 'status' => 'ACTIVE'],
            ['trustPoints' => 'DESC'],
            $limit
        );

        $data = [];
        foreach ($prestataires as $prestataire) {
            $reviews = $this->reviewRepo->findByTutorWithDetails($prestataire->getId());
            
            if (count($reviews) === 0) continue;

            $trustPoints = $prestataire->getTrustPoints() ?? 0;
            $avgRating = round(
                array_sum(array_map(fn($r) => $r->getRating() ?? 0, $reviews)) / count($reviews),
                2
            );

            $badge = $this->badgeService->getBadge($trustPoints, $avgRating);

            $data[] = [
                'id' => $prestataire->getId(),
                'name' => $prestataire->getName(),
                'trustPoints' => $trustPoints,
                'avgRating' => $avgRating,
                'reviewCount' => count($reviews),
                'badge' => $badge,
            ];
        }

        return $this->json($data);
    }
}
