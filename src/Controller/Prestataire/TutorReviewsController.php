<?php

namespace App\Controller\Prestataire;

use App\Entity\Review;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/tutor/reviews', name: 'tutor_reviews_')]
class TutorReviewsController extends AbstractController
{
    private const MIN_REASON_LENGTH = 10;
    private const MAX_REASON_LENGTH = 500;
    private const ALLOWED_LANGS     = ['en', 'fr', 'ar', 'es', 'de', 'it'];
    private const REPORT_REASONS    = [
        'Contenu inapproprié',
        'Langage offensant',
        'Informations fausses',
        'Harcèlement',
        'Spam',
        'Autre',
    ];

    public function __construct(
        private ReviewRepository       $repo,
        private EntityManagerInterface $em,
        private HttpClientInterface    $client,
        private \App\Service\ReviewsBadgeService $badgeService,
        private PaginatorInterface     $paginator
    ) {}

    // ===================== GET CURRENT USER =====================

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

    // ===================== VALIDATION REPORT =====================

    private function validateReportInput(Request $request): array
    {
        $errors   = [];
        $reason   = trim($request->request->get('reason', ''));
        $category = trim($request->request->get('reason_category', ''));

        if (empty($category)) {
            $errors[] = 'Veuillez choisir une catégorie de signalement.';
        } elseif (!in_array($category, self::REPORT_REASONS, true)) {
            $errors[] = 'La catégorie choisie est invalide.';
        }

        if (empty($reason)) {
            $errors[] = 'La raison du signalement est obligatoire.';
            return $errors;
        }

        if (mb_strlen($reason) < self::MIN_REASON_LENGTH) {
            $errors[] = sprintf('La raison doit contenir au moins %d caractères.', self::MIN_REASON_LENGTH);
        }

        if (mb_strlen($reason) > self::MAX_REASON_LENGTH) {
            $errors[] = sprintf('La raison ne peut pas dépasser %d caractères.', self::MAX_REASON_LENGTH);
        }

        if (!preg_match('/[a-zA-ZÀ-ÿ]/', $reason)) {
            $errors[] = 'La raison doit contenir au moins quelques lettres.';
        }

        if (preg_match('/(.)\1{9,}/', $reason)) {
            $errors[] = 'La raison ne peut pas contenir de caractères répétés excessivement.';
        }

        return $errors;
    }

    // ===================== API: VALIDATE REPORT INPUT =====================

    #[Route('/api/validate-report', name: 'api_validate_report', methods: ['POST'])]
    public function validateReportApi(Request $request): Response
    {
        $errors = $this->validateReportInput($request);
        
        if (!empty($errors)) {
            return $this->json(['valid' => false, 'errors' => $errors], 400);
        }

        return $this->json(['valid' => true]);
    }

    // ===================== API: FILTER REVIEWS =====================

    #[Route('/api/filter', name: 'api_filter', methods: ['GET'])]
    public function filterReviews(Request $request): Response
    {
        $user = $this->getCurrentTutor();
        $filter = $request->query->get('filter');
        $page   = max(1, $request->query->getInt('page', 1));
        $limit  = 5;
        
        $allReviews = $this->repo->findByTutorWithDetails($user->getId());

        $reviews = $allReviews;
        if ($filter !== null) {
            $reviews = array_values(array_filter($allReviews, function (Review $r) use ($filter) {
                $rating = $r->getRating() ?? 0;
                return match ($filter) {
                    '5'   => $rating === 5,
                    '4'   => $rating === 4,
                    '3'   => $rating === 3,
                    'low' => $rating >= 1 && $rating <= 2,
                    'neg' => $rating < 0,
                    default => true,
                };
            }));
        }

        $total      = count($reviews);
        $totalPages = (int) ceil($total / $limit);
        $page       = min($page, max(1, $totalPages));
        $paged      = array_slice($reviews, ($page - 1) * $limit, $limit);

        $data = array_map(function (Review $r) {
            $rating = $r->getRating() ?? 0;
            $absRating = abs($rating);
            if ($absRating > 5) $absRating = 5;

            return [
                'id'          => $r->getId(),
                'student'     => $r->getStudent()?->getName() ?? 'Étudiant inconnu',
                'service'     => $r->getReservation()?->getService()?->getTitle() ?? 'Service inconnu',
                'rating'      => $rating,
                'absRating'   => $absRating,
                'comment'     => $r->getComment() ?? '',
                'isReported'  => $r->isReported(),
            ];
        }, $paged);

        return $this->json([
            'reviews'     => $data,
            'count'       => $total,
            'page'        => $page,
            'totalPages'  => $totalPages,
            'limit'       => $limit,
        ]);
    }

    // ===================== INDEX =====================

    #[Route('/', name: 'index')]
    public function index(Request $request): Response
    {
        $user = $this->getCurrentTutor();
        $filter     = $request->query->get('filter');
        $allReviews = $this->repo->findByTutorWithDetails($user->getId());

        // Filtrage
        $filtered = $allReviews;
        if ($filter !== null) {
            $filtered = array_values(array_filter($allReviews, function (Review $r) use ($filter) {
                $rating = $r->getRating() ?? 0;
                return match ($filter) {
                    '5'   => $rating === 5,
                    '4'   => $rating === 4,
                    '3'   => $rating === 3,
                    'low' => $rating >= 1 && $rating <= 2,
                    'neg' => $rating < 0,
                    default => true,
                };
            }));
        }

        $pagination = $this->paginator->paginate(
            $filtered,
            $request->query->getInt('page', 1),
            5
        );

        $trust = (int) $this->em->getConnection()
            ->fetchOne('SELECT trust_points FROM users WHERE id = ?', [$user->getId()]);

        $avg = count($allReviews)
            ? round(array_sum(array_map(fn($r) => $r->getRating() ?? 0, $allReviews)) / count($allReviews), 1)
            : 0;

        // Obtenir le badge et la progression
        $badge = $this->badgeService->getBadge($trust, $avg);
        $allBadges = $this->badgeService->getAllBadges($trust, $avg, count($allReviews), 0);
        $progress = $this->badgeService->getProgressToNextBadge($trust, $avg);

        return $this->render('prestataire/TutorReviews.html.twig', [
            'reviews'         => $pagination,
            'trustPoints'     => $trust,
            'averageRating'   => $avg,
            'totalReviews'    => count($allReviews),
            'currentFilter'   => $filter,
            'reportReasons'   => self::REPORT_REASONS,
            'minReasonLength' => self::MIN_REASON_LENGTH,
            'maxReasonLength' => self::MAX_REASON_LENGTH,
            'badge'           => $badge,
            'allBadges'       => $allBadges,
            'progress'        => $progress,
        ]);
    }

    // ===================== TRANSLATE =====================

    #[Route('/translate', name: 'translate', methods: ['POST'])]
    public function translate(Request $request): JsonResponse
    {
        $text = trim($request->request->get('text', ''));
        $lang = trim($request->request->get('lang', 'en'));

        if (empty($text)) {
            return new JsonResponse(['error' => 'Texte vide.'], 400);
        }

        if (!in_array($lang, self::ALLOWED_LANGS, true)) {
            return new JsonResponse(['error' => 'Langue cible non supportée.'], 400);
        }

        if (mb_strlen($text) > 1000) {
            return new JsonResponse(['error' => 'Texte trop long pour la traduction.'], 400);
        }

        $sourceLang = preg_match('/[\x{0600}-\x{06FF}]/u', $text) ? 'ar' : 'fr';

        if ($sourceLang === $lang) {
            return new JsonResponse(['translated' => $text]);
        }

        try {
            $response = $this->client->request('GET', 'https://api.mymemory.translated.net/get', [
                'query' => ['q' => $text, 'langpair' => $sourceLang . '|' . $lang],
            ]);
            $data   = $response->toArray();
            $result = $data['responseData']['translatedText'] ?? $text;
        } catch (\Exception $e) {
            $result = $text;
        }

        return new JsonResponse(['translated' => $result]);
    }

    // ===================== REPORT =====================

    #[Route('/report/{id}', name: 'report', methods: ['POST'])]
    public function report(int $id, Request $request): Response
    {
        $user = $this->getCurrentTutor();
        $review = $this->repo->find($id);

        if (!$review) {
            throw $this->createNotFoundException('Avis introuvable.');
        }

        if ($review->getPrestataire()?->getId() !== $user->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas signaler un avis qui ne vous concerne pas.');
            return $this->redirectToRoute('tutor_reviews_index');
        }

        if ($review->isReported()) {
            $this->addFlash('error', 'Cet avis a déjà été signalé.');
            return $this->redirectToRoute('tutor_reviews_index');
        }

        $errors = $this->validateReportInput($request);
        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
            return $this->redirectToRoute('tutor_reviews_index');
        }

        $category   = trim($request->request->get('reason_category'));
        $reason     = trim($request->request->get('reason'));
        $fullReason = '[' . $category . '] ' . $reason;

        $review->setIsReported(true)
               ->setReportReason($fullReason)
               ->setReportedAt(new \DateTime());

        $this->em->flush();

        $this->addFlash('success', 'L\'avis a été signalé avec succès. Notre équipe va l\'examiner.');
        return $this->redirectToRoute('tutor_reviews_index');
    }
}