<?php

namespace App\Controller\Etudiant;

use App\Entity\Review;
use App\Form\ReviewFormType;
use App\Repository\ReviewRepository;
use App\Service\GroqReviewsModerationService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/student/reviews', name: 'student_reviews_')]
class StudentReviewsController extends AbstractController
{
    public const MAX_COMMENT_LENGTH = 1000;
    public const MIN_COMMENT_LENGTH = 10;
    public const MIN_RATING         = -5;
    public const MAX_RATING         = 5;

    public function __construct(
        private ReviewRepository       $repo,
        private EntityManagerInterface $em,
        private GroqReviewsModerationService  $moderationService,
        private PaginatorInterface     $paginator
    ) {}

    private function getCurrentStudent(): \App\Entity\User
    {
        $user = $this->getUser();
        
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour accéder à cette page.');
        }

        if ($user->getUserType() !== 'ETUDIANT') {
            throw $this->createAccessDeniedException('Accès réservé aux étudiants.');
        }

        return $user;
    }

    private function validateReviewInput(Request $request): array
    {
        $errors  = [];
        $rating  = $request->request->get('rating');
        $comment = trim($request->request->get('comment', ''));

        // Validation rating
        if ($rating === null || $rating === '') {
            $errors[] = 'La note est obligatoire.';
        } elseif (!is_numeric($rating)) {
            $errors[] = 'La note doit être un nombre.';
        } elseif ((int) $rating < self::MIN_RATING || (int) $rating > self::MAX_RATING) {
            $errors[] = sprintf('La note doit être comprise entre %d et %d.', self::MIN_RATING, self::MAX_RATING);
        } elseif ((int) $rating === 0) {
            $errors[] = 'La note ne peut pas être 0. Choisissez une note positive ou négative.';
        }

        // Validation commentaire
        if (empty($comment)) {
            $errors[] = 'Le commentaire est obligatoire.';
        } elseif (mb_strlen($comment) < self::MIN_COMMENT_LENGTH) {
            $errors[] = sprintf('Le commentaire doit contenir au moins %d caractères.', self::MIN_COMMENT_LENGTH);
        } elseif (mb_strlen($comment) > self::MAX_COMMENT_LENGTH) {
            $errors[] = sprintf('Le commentaire ne peut pas dépasser %d caractères.', self::MAX_COMMENT_LENGTH);
        } elseif (preg_match('/(.)\1{9,}/', $comment)) {
            $errors[] = 'Le commentaire ne peut pas contenir de caractères répétés excessivement.';
        } elseif (!preg_match('/[a-zA-ZÀ-ÿ]/', $comment)) {
            $errors[] = 'Le commentaire doit contenir au moins quelques lettres.';
        }

        // Validation reservation_id
        $reservationId = $request->request->get('reservation_id');
        if (empty($reservationId) || !is_numeric($reservationId) || (int) $reservationId <= 0) {
            $errors[] = 'Veuillez sélectionner une réservation valide.';
        }

        // Validation prestataire_id
        $prestataireId = $request->request->get('prestataire_id');
        if (empty($prestataireId) || !is_numeric($prestataireId) || (int) $prestataireId <= 0) {
            $errors[] = 'Le prestataire est invalide.';
        }

        return $errors;
    }

    private function validateEditInput(Request $request): array
    {
        $errors  = [];
        $rating  = $request->request->get('rating');
        $comment = trim($request->request->get('comment', ''));

        // Validation rating
        if ($rating === null || $rating === '') {
            $errors[] = 'La note est obligatoire.';
        } elseif (!is_numeric($rating)) {
            $errors[] = 'La note doit être un nombre.';
        } elseif ((int) $rating < self::MIN_RATING || (int) $rating > self::MAX_RATING) {
            $errors[] = sprintf('La note doit être comprise entre %d et %d.', self::MIN_RATING, self::MAX_RATING);
        } elseif ((int) $rating === 0) {
            $errors[] = 'La note ne peut pas être 0. Choisissez une note positive ou négative.';
        }

        // Validation commentaire
        if (empty($comment)) {
            $errors[] = 'Le commentaire est obligatoire.';
        } elseif (mb_strlen($comment) < self::MIN_COMMENT_LENGTH) {
            $errors[] = sprintf('Le commentaire doit contenir au moins %d caractères.', self::MIN_COMMENT_LENGTH);
        } elseif (mb_strlen($comment) > self::MAX_COMMENT_LENGTH) {
            $errors[] = sprintf('Le commentaire ne peut pas dépasser %d caractères.', self::MAX_COMMENT_LENGTH);
        } elseif (preg_match('/(.)\1{9,}/', $comment)) {
            $errors[] = 'Le commentaire ne peut pas contenir de caractères répétés excessivement.';
        } elseif (!preg_match('/[a-zA-ZÀ-ÿ]/', $comment)) {
            $errors[] = 'Le commentaire doit contenir au moins quelques lettres.';
        }

        return $errors;
    }

    // ===================== API: VALIDATE REVIEW INPUT =====================

    #[Route('/api/validate-review', name: 'api_validate_review', methods: ['POST'])]
    public function validateReviewApi(Request $request): Response
    {
        $errors = $this->validateReviewInput($request);
        
        if (!empty($errors)) {
            return $this->json(['valid' => false, 'errors' => $errors], 400);
        }

        return $this->json(['valid' => true]);
    }

    // ===================== API: AI SUGGESTION =====================

    #[Route('/api/suggest-comment', name: 'api_suggest_comment', methods: ['POST'])]
    public function suggestComment(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        
        $partialText = $data['text'] ?? '';
        $rating = (int) ($data['rating'] ?? 0);
        $serviceName = $data['service'] ?? '';

        // Limiter les appels si le texte est trop court
        if (mb_strlen($partialText) < 3 && empty($serviceName)) {
            return $this->json([
                'suggestion' => '',
                'confidence' => 0.0
            ]);
        }

        $result = $this->moderationService->generateCommentSuggestion(
            $partialText,
            $rating,
            $serviceName
        );

        return $this->json($result);
    }

    // ===================== API: GET PRESTATAIRE FROM RESERVATION =====================

    #[Route('/api/reservation/{id}/prestataire', name: 'api_get_prestataire', methods: ['GET'])]
    public function getPrestataireFromReservation(int $id): Response
    {
        $reservation = $this->em->getRepository(\App\Entity\Reservation::class)->find($id);
        
        if (!$reservation) {
            return $this->json(['error' => 'Réservation introuvable'], 404);
        }

        $prestataireId = $reservation->getService()?->getPrestataire()?->getId();
        
        if (!$prestataireId) {
            return $this->json(['error' => 'Prestataire introuvable'], 404);
        }

        return $this->json(['prestataireId' => $prestataireId]);
    }

    #[Route('/', name: 'index')]
    public function index(Request $request): Response
    {
        $user = $this->getCurrentStudent();
        $reviews = $this->repo->findByStudentWithDetails($user->getId());

        $pagination = $this->paginator->paginate(
            $reviews,
            $request->query->getInt('page', 1),
            5
        );

        $confirmedReservations = $this->repo->getConfirmedReservationsForStudent($user->getId());

        // Créer le formulaire avec le bundle
        $review = new Review();
        $form = $this->createForm(ReviewFormType::class, $review);

        return $this->render('etudiant/StudentReviews.html.twig', [
            'reviews'               => $pagination,
            'totalReviews'          => count($reviews),
            'currentPage'           => $request->query->getInt('page', 1),
            'totalPages'            => $pagination->getPageCount(),
            'confirmedReservations' => $confirmedReservations,
            'maxCommentLength'      => self::MAX_COMMENT_LENGTH,
            'minCommentLength'      => self::MIN_COMMENT_LENGTH,
            'form'                  => $form->createView(),
        ]);
    }

    #[Route('/add', name: 'add', methods: ['POST'])]
    public function add(Request $request): Response
    {
        $user = $this->getCurrentStudent();

        $review = new Review();
        $form = $this->createForm(ReviewFormType::class, $review);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $reservationId = (int) $request->request->get('reservation_id');
            $prestataireId = (int) $request->request->get('prestataire_id');

            // Vérifier doublon
            if ($this->repo->existsByStudentAndReservation($user->getId(), $reservationId)) {
                $this->addFlash('error', 'Vous avez déjà laissé un avis pour cette réservation.');
                return $this->redirectToRoute('student_reviews_index');
            }

            // ========== MODÉRATION IA GROQ ==========
            $comment = $review->getComment();
            
            // Vérification rapide des gros mots évidents
            if ($this->moderationService->hasObviousBadWords($comment)) {
                $this->addFlash('error', '❌ Votre commentaire contient un langage inapproprié. Veuillez reformuler de manière respectueuse.');
                return $this->redirectToRoute('student_reviews_index');
            }
            
            // Analyse IA complète
            $moderationResult = $this->moderationService->analyzeComment($comment);
            
            if (!$moderationResult['is_appropriate']) {
                $reason = $moderationResult['reason'] ?? 'Contenu inapproprié détecté';
                $this->addFlash('error', "❌ Votre commentaire a été rejeté : $reason");
                $this->addFlash('info', '💡 Conseil : Exprimez votre avis de manière constructive et respectueuse.');
                return $this->redirectToRoute('student_reviews_index');
            }
            // ========================================

            $prestataire = $this->em->getRepository(\App\Entity\User::class)->find($prestataireId);
            $reservation = $this->em->getRepository(\App\Entity\Reservation::class)->find($reservationId);

            if (!$prestataire) {
                $this->addFlash('error', 'Prestataire introuvable.');
                return $this->redirectToRoute('student_reviews_index');
            }
            if (!$reservation) {
                $this->addFlash('error', 'Réservation introuvable.');
                return $this->redirectToRoute('student_reviews_index');
            }

            $review->setStudent($user)
                   ->setPrestataire($prestataire)
                   ->setReservation($reservation)
                   ->setIsReported(false);

            $this->em->persist($review);
            $this->em->flush();

            $this->repo->applyTrustPoints($prestataire->getId(), $review->getRating());

            $this->addFlash('success', '✅ Votre avis a été publié avec succès !');
            return $this->redirectToRoute('student_reviews_index');
        }

        // Si le formulaire n'est pas valide, afficher les erreurs
        foreach ($form->getErrors(true) as $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->redirectToRoute('student_reviews_index');
    }

    #[Route('/edit/{id}', name: 'edit', methods: ['POST'])]
    public function edit(int $id, Request $request): Response
    {
        $user = $this->getCurrentStudent();
        $review = $this->repo->find($id);

        if (!$review) {
            throw $this->createNotFoundException('Avis introuvable.');
        }

        // Vérifier ownership
        if ($review->getStudent()?->getId() !== $user->getId()) {
            $this->addFlash('error', 'Cet avis ne vous appartient pas.');
            return $this->redirectToRoute('student_reviews_index');
        }

        // Validation des champs
        $errors = $this->validateEditInput($request);
        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
            return $this->redirectToRoute('student_reviews_index');
        }

        $oldRating = $review->getRating() ?? 0;
        $newRating = (int) $request->request->get('rating');
        $comment   = trim($request->request->get('comment'));

        $review->setRating($newRating)
               ->setComment($comment);
        $this->em->flush();

        $this->repo->applyTrustPointsForEdit($review->getPrestataire()->getId(), $oldRating, $newRating);

        $this->addFlash('success', 'Votre avis a été modifié avec succès !');
        return $this->redirectToRoute('student_reviews_index');
    }

    #[Route('/delete/{id}', name: 'delete', methods: ['POST'])]
    public function delete(int $id): Response
    {
        $user = $this->getCurrentStudent();
        $review = $this->repo->find($id);

        if (!$review) {
            throw $this->createNotFoundException('Avis introuvable.');
        }

        // Vérifier ownership
        if ($review->getStudent()?->getId() !== $user->getId()) {
            $this->addFlash('error', 'Cet avis ne vous appartient pas.');
            return $this->redirectToRoute('student_reviews_index');
        }

        $prestataireId = $review->getPrestataire()->getId();
        $rating        = $review->getRating() ?? 0;

        $this->em->remove($review);
        $this->em->flush();

        $this->repo->applyTrustPoints($prestataireId, -$rating);

        $this->addFlash('success', 'Votre avis a été supprimé.');
        return $this->redirectToRoute('student_reviews_index');
    }
}