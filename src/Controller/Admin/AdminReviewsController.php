<?php

namespace App\Controller\Admin;

use App\Entity\Review;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Knp\Snappy\Pdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/reviews', name: 'admin_reviews_')]
class AdminReviewsController extends AbstractController
{
    public function __construct(
        private ReviewRepository       $repo,
        private EntityManagerInterface $em,
        private Pdf                    $pdfGenerator
    ) {}

    private function getCurrentAdmin(): \App\Entity\User
    {
        $user = $this->getUser();
        
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour accéder à cette page.');
        }

        if ($user->getUserType() !== 'ADMIN') {
            throw $this->createAccessDeniedException('Accès réservé aux administrateurs.');
        }

        return $user;
    }

    // ===================== INDEX =====================

    #[Route('/', name: 'index')]
    public function index(Request $request): Response
    {
        $admin = $this->getCurrentAdmin();
        $allReviews = $this->repo->findAllWithDetails();

        // Récupérer les filtres
        $filterPrestataire = $request->query->get('prestataire', '');
        $filterRating      = $request->query->get('rating', '');
        $filterSearch      = trim($request->query->get('search', ''));

        // Appliquer les filtres
        $reviews = $allReviews;

        if (!empty($filterPrestataire)) {
            $reviews = array_filter($reviews, fn(Review $r) =>
                $r->getPrestataire()?->getName() === $filterPrestataire
            );
        }

        if (!empty($filterRating)) {
            $reviews = array_filter($reviews, function (Review $r) use ($filterRating) {
                $rating = $r->getRating() ?? 0;
                return match ($filterRating) {
                    'positive'      => $rating > 0,
                    'negative'      => $rating < 0,
                    'very_positive' => $rating >= 4,
                    'very_negative' => $rating <= -4,
                    'neutral'       => $rating === 0,
                    'reported'      => $r->isReported() === true,
                    default         => true,
                };
            });
        }

        if (!empty($filterSearch)) {
            $search  = mb_strtolower($filterSearch);
            $reviews = array_filter($reviews, function (Review $r) use ($search) {
                return str_contains(mb_strtolower($r->getComment() ?? ''), $search)
                    || str_contains(mb_strtolower($r->getReservation()?->getService()?->getTitle() ?? ''), $search)
                    || str_contains(mb_strtolower($r->getStudent()?->getName() ?? ''), $search)
                    || str_contains(mb_strtolower($r->getPrestataire()?->getName() ?? ''), $search);
            });
        }

        $reviews = array_values($reviews);

        // Statistiques (toujours sur tous les avis)
        $stats = [
            'total'    => count($allReviews),
            'positive' => count(array_filter($allReviews, fn($r) => ($r->getRating() ?? 0) > 0)),
            'negative' => count(array_filter($allReviews, fn($r) => ($r->getRating() ?? 0) < 0)),
            'reported' => count(array_filter($allReviews, fn($r) => $r->isReported() === true)),
        ];

        // Liste des prestataires pour le filtre
        $prestataires = array_unique(array_filter(array_map(
            fn($r) => $r->getPrestataire()?->getName(),
            $allReviews
        )));
        sort($prestataires);

        return $this->render('admin/pages/AdminReviews.html.twig', [
            'reviews'           => $reviews,
            'stats'             => $stats,
            'prestataires'      => $prestataires,
            'filterPrestataire' => $filterPrestataire,
            'filterRating'      => $filterRating,
            'filterSearch'      => $filterSearch,
            'admin'             => $admin,
        ]);
    }

    // ===================== UNREPORT =====================

    #[Route('/unreport/{id}', name: 'unreport', methods: ['POST'])]
    public function unreport(int $id): Response
    {
        $review = $this->repo->find($id);

        if (!$review) {
            throw $this->createNotFoundException('Avis introuvable.');
        }

        if (!$review->isReported()) {
            $this->addFlash('error', 'Cet avis n\'est pas signalé.');
            return $this->redirectToRoute('admin_reviews_index');
        }

        $review->setIsReported(false)
               ->setReportReason(null)
               ->setReportedAt(null);

        $this->em->flush();

        $this->addFlash('success', 'Le signalement a été marqué comme traité.');
        return $this->redirectToRoute('admin_reviews_index');
    }

    // ===================== DELETE =====================

    #[Route('/delete/{id}', name: 'delete', methods: ['POST'])]
    public function delete(int $id): Response
    {
        $review = $this->repo->find($id);

        if (!$review) {
            throw $this->createNotFoundException('Avis introuvable.');
        }

        $prestataireId = $review->getPrestataire()?->getId();
        $rating        = $review->getRating() ?? 0;

        $this->em->remove($review);
        $this->em->flush();

        // Retirer les trust points
        if ($prestataireId && $rating !== 0) {
            $this->repo->applyTrustPoints($prestataireId, -$rating);
        }

        $this->addFlash('success', 'L\'avis a été supprimé avec succès.');
        return $this->redirectToRoute('admin_reviews_index');
    }

    // ===================== API: SEARCH & FILTER =====================

    #[Route('/api/search', name: 'api_search', methods: ['GET'])]
    public function searchReviews(Request $request): Response
    {
        $allReviews = $this->repo->findAllWithDetails();

        $filterPrestataire = $request->query->get('prestataire', '');
        $filterRating      = $request->query->get('rating', '');
        $filterSearch      = trim($request->query->get('search', ''));

        $reviews = $allReviews;

        if (!empty($filterPrestataire)) {
            $reviews = array_filter($reviews, fn(Review $r) =>
                $r->getPrestataire()?->getName() === $filterPrestataire
            );
        }

        if (!empty($filterRating)) {
            $reviews = array_filter($reviews, function (Review $r) use ($filterRating) {
                $rating = $r->getRating() ?? 0;
                return match ($filterRating) {
                    'positive'      => $rating > 0,
                    'negative'      => $rating < 0,
                    'very_positive' => $rating >= 4,
                    'very_negative' => $rating <= -4,
                    'neutral'       => $rating === 0,
                    'reported'      => $r->isReported() === true,
                    default         => true,
                };
            });
        }

        if (!empty($filterSearch)) {
            $search  = mb_strtolower($filterSearch);
            $reviews = array_filter($reviews, function (Review $r) use ($search) {
                return str_contains(mb_strtolower($r->getComment() ?? ''), $search)
                    || str_contains(mb_strtolower($r->getReservation()?->getService()?->getTitle() ?? ''), $search)
                    || str_contains(mb_strtolower($r->getStudent()?->getName() ?? ''), $search)
                    || str_contains(mb_strtolower($r->getPrestataire()?->getName() ?? ''), $search);
            });
        }

        $reviews = array_values($reviews);

        $data = array_map(function (Review $r) {
            $rating = $r->getRating() ?? 0;
            $absRating = abs($rating);
            if ($absRating > 5) $absRating = 5;

            return [
                'id'            => $r->getId(),
                'student'       => $r->getStudent()?->getName() ?? 'Inconnu',
                'prestataire'   => $r->getPrestataire()?->getName() ?? 'Inconnu',
                'service'       => $r->getReservation()?->getService()?->getTitle() ?? 'Inconnu',
                'rating'        => $rating,
                'absRating'     => $absRating,
                'comment'       => $r->getComment() ?? '',
                'isReported'    => $r->isReported(),
                'reportReason'  => $r->getReportReason() ?? '',
                'reportedAt'    => $r->getReportedAt()?->format('d/m/Y à H:i') ?? '',
            ];
        }, $reviews);

        return $this->json([
            'reviews' => $data,
            'count'   => count($data),
        ]);
    }

    // ===================== API: GET REVIEW DETAILS =====================

    #[Route('/api/details/{id}', name: 'api_details', methods: ['GET'])]
    public function getReviewDetails(int $id): Response
    {
        $review = $this->repo->find($id);

        if (!$review) {
            return $this->json(['error' => 'Avis introuvable'], 404);
        }

        $data = [
            'student'     => $review->getStudent()?->getName() ?? 'Inconnu',
            'prestataire' => $review->getPrestataire()?->getName() ?? 'Inconnu',
            'service'     => $review->getReservation()?->getService()?->getTitle() ?? 'Inconnu',
            'rating'      => $review->getRating() ?? 0,
            'comment'     => $review->getComment() ?? '',
            'isReported'  => $review->isReported(),
            'reportReason' => $review->getReportReason() ?? '',
            'reportedAt'  => $review->getReportedAt()?->format('d/m/Y à H:i') ?? '',
        ];

        return $this->json($data);
    }

    // ===================== EXPORT =====================

    #[Route('/export/{format}', name: 'export', methods: ['GET'])]
    public function export(string $format, Request $request): Response
    {
        $admin = $this->getCurrentAdmin();
        $allowedFormats = ['csv', 'pdf'];
        
        if (!in_array($format, $allowedFormats, true)) {
            throw $this->createNotFoundException('Format non supporté.');
        }

        $allReviews = $this->repo->findAllWithDetails();
        $timestamp  = (new \DateTime())->format('Ymd_His');

        // Calculer les statistiques
        $stats = [
            'total'    => count($allReviews),
            'positive' => count(array_filter($allReviews, fn($r) => ($r->getRating() ?? 0) > 0)),
            'negative' => count(array_filter($allReviews, fn($r) => ($r->getRating() ?? 0) < 0)),
            'reported' => count(array_filter($allReviews, fn($r) => $r->isReported() === true)),
        ];

        if ($format === 'csv') {
            $csv  = "\xEF\xBB\xBF"; // UTF-8 BOM pour Excel
            $csv .= "ID,Étudiant,Prestataire,Service,Note,Commentaire,Signalé,Raison,Date signalement\n";
            
            foreach ($allReviews as $r) {
                $csv .= implode(',', [
                    $r->getId(),
                    '"' . ($r->getStudent()?->getName() ?? 'N/A') . '"',
                    '"' . ($r->getPrestataire()?->getName() ?? 'N/A') . '"',
                    '"' . ($r->getReservation()?->getService()?->getTitle() ?? 'N/A') . '"',
                    $r->getRating() ?? 0,
                    '"' . str_replace('"', '""', $r->getComment() ?? '') . '"',
                    $r->isReported() ? 'Oui' : 'Non',
                    '"' . str_replace('"', '""', $r->getReportReason() ?? '') . '"',
                    $r->getReportedAt()?->format('d/m/Y H:i') ?? '',
                ]) . "\n";
            }

            return new Response($csv, 200, [
                'Content-Type'        => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="avis_campuslink_' . $timestamp . '.csv"',
            ]);
        }

        // Export PDF avec KnpSnappyBundle
        $html = $this->renderView('admin/pages/reviews_pdf.html.twig', [
            'reviews'    => $allReviews,
            'stats'      => $stats,
            'exportDate' => (new \DateTime())->format('d/m/Y à H:i'),
            'adminName'  => $admin->getName(),
        ]);

        return new PdfResponse(
            $this->pdfGenerator->getOutputFromHtml($html),
            'avis_campuslink_' . $timestamp . '.pdf'
        );
    }
}