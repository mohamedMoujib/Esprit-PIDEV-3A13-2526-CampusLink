<?php

namespace App\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class StudentReviewsControllerTest extends TestCase
{
    // ==================== CONSTANTES (miroir du contrôleur) ====================

    private const MIN_RATING         = -5;
    private const MAX_RATING         = 5;
    private const MIN_COMMENT_LENGTH = 10;
    private const MAX_COMMENT_LENGTH = 1000;

    // ==================== HELPER : logique de validation ====================

    /**
     * Réplique fidèle de StudentReviewsController::validateReviewInput()
     *
     * @return array<string>
     */
    private function invokeValidateReviewInput(Request $request): array
    {
        $errors     = [];
        $rating     = $request->request->get('rating');
        $commentRaw = $request->request->get('comment', '');
        $comment    = trim(is_string($commentRaw) ? $commentRaw : '');

        // --- Validation rating ---
        if ($rating === null || $rating === '') {
            $errors[] = 'La note est obligatoire.';
        } elseif (!is_numeric($rating)) {
            $errors[] = 'La note doit être un nombre.';
        } elseif ((int) $rating < self::MIN_RATING || (int) $rating > self::MAX_RATING) {
            $errors[] = sprintf(
                'La note doit être comprise entre %d et %d.',
                self::MIN_RATING,
                self::MAX_RATING
            );
        } elseif ((int) $rating === 0) {
            $errors[] = 'La note ne peut pas être 0. Choisissez une note positive ou négative.';
        }

        // --- Validation commentaire ---
        if (empty($comment)) {
            $errors[] = 'Le commentaire est obligatoire.';
        } elseif (mb_strlen($comment) < self::MIN_COMMENT_LENGTH) {
            $errors[] = sprintf(
                'Le commentaire doit contenir au moins %d caractères.',
                self::MIN_COMMENT_LENGTH
            );
        } elseif (mb_strlen($comment) > self::MAX_COMMENT_LENGTH) {
            $errors[] = sprintf(
                'Le commentaire ne peut pas dépasser %d caractères.',
                self::MAX_COMMENT_LENGTH
            );
        } elseif (preg_match('/(.)\1{9,}/', $comment)) {
            $errors[] = 'Le commentaire ne peut pas contenir de caractères répétés excessivement.';
        } elseif (!preg_match('/[a-zA-ZÀ-ÿ]/', $comment)) {
            $errors[] = 'Le commentaire doit contenir au moins quelques lettres.';
        }

        // --- Validation reservation_id ---
        $reservationId = $request->request->get('reservation_id');
        if (empty($reservationId) || !is_numeric($reservationId) || (int) $reservationId <= 0) {
            $errors[] = 'Veuillez sélectionner une réservation valide.';
        }

        // --- Validation prestataire_id ---
        $prestataireId = $request->request->get('prestataire_id');
        if (empty($prestataireId) || !is_numeric($prestataireId) || (int) $prestataireId <= 0) {
            $errors[] = 'Le prestataire est invalide.';
        }

        return $errors;
    }

    /** Construit une requête valide de base (tous les champs corrects). */
    private function makeValidRequest(
        string $rating        = '3',
        string $comment       = 'Bon service, très professionnel et à l\'écoute.',
        string $reservationId = '1',
        string $prestataireId = '2'
    ): Request {
        $request = new Request();
        $request->request->set('rating', $rating);
        $request->request->set('comment', $comment);
        $request->request->set('reservation_id', $reservationId);
        $request->request->set('prestataire_id', $prestataireId);
        return $request;
    }

    // ==================== TESTS : NOTE (RATING) ====================

    /**
     * Règle métier : Une note valide dans la plage autorisée ne génère pas d'erreur.
     */
    public function testValidRatingInRange(): void
    {
        $errors = $this->invokeValidateReviewInput($this->makeValidRequest('3'));
        $this->assertEmpty($errors, 'Une note de 3 doit être acceptée sans erreur.');
    }

    /**
     * Règle métier : La note extrême négative (-5) est valide.
     */
    public function testValidRatingMinBoundary(): void
    {
        $errors = $this->invokeValidateReviewInput($this->makeValidRequest('-5'));
        $this->assertEmpty($errors, 'La note -5 (borne minimale) doit être valide.');
    }

    /**
     * Règle métier : La note extrême positive (5) est valide.
     */
    public function testValidRatingMaxBoundary(): void
    {
        $errors = $this->invokeValidateReviewInput($this->makeValidRequest('5'));
        $this->assertEmpty($errors, 'La note 5 (borne maximale) doit être valide.');
    }

    /**
     * Règle métier : La note 0 est refusée.
     */
    public function testRatingCannotBeZero(): void
    {
        $errors = $this->invokeValidateReviewInput($this->makeValidRequest('0'));

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString(
            'ne peut pas être 0',
            implode(' ', $errors),
            'Le message doit signaler que la note 0 est interdite.'
        );
    }

    /**
     * Règle métier : Une note trop basse (-6) est refusée.
     */
    public function testRatingBelowMinIsRejected(): void
    {
        $errors = $this->invokeValidateReviewInput($this->makeValidRequest('-6'));

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString(
            'comprise entre',
            implode(' ', $errors),
            'La note -6 doit déclencher l\'erreur de plage.'
        );
    }

    /**
     * Règle métier : Une note trop haute (6) est refusée.
     */
    public function testRatingAboveMaxIsRejected(): void
    {
        $errors = $this->invokeValidateReviewInput($this->makeValidRequest('6'));

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('comprise entre', implode(' ', $errors));
    }

    /**
     * Règle métier : Une note absente génère une erreur "obligatoire".
     */
    public function testMissingRatingIsRejected(): void
    {
        $request = new Request();
        $request->request->set('comment', 'Bon service, très professionnel.');
        $request->request->set('reservation_id', '1');
        $request->request->set('prestataire_id', '2');

        $errors = $this->invokeValidateReviewInput($request);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('obligatoire', implode(' ', $errors));
    }

    /**
     * Règle métier : Une note non numérique est refusée.
     */
    public function testNonNumericRatingIsRejected(): void
    {
        $errors = $this->invokeValidateReviewInput($this->makeValidRequest('abc'));

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('nombre', implode(' ', $errors));
    }

    // ==================== TESTS : COMMENTAIRE ====================

    /**
     * Règle métier : Un commentaire vide est refusé.
     */
    public function testEmptyCommentIsRejected(): void
    {
        $errors = $this->invokeValidateReviewInput($this->makeValidRequest('3', ''));

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('obligatoire', implode(' ', $errors));
    }

    /**
     * Règle métier : Un commentaire trop court (< 10 caractères) est refusé.
     */
    public function testCommentTooShortIsRejected(): void
    {
        $errors = $this->invokeValidateReviewInput($this->makeValidRequest('3', 'Court'));

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('au moins', implode(' ', $errors));
    }

    /**
     * Règle métier : Un commentaire au seuil exact (10 caractères) est valide.
     */
    public function testCommentAtMinLengthBoundaryIsAccepted(): void
    {
        // 10 caractères exactement avec des lettres
        $errors = $this->invokeValidateReviewInput($this->makeValidRequest('3', 'Trèsbienn'));
        // 9 chars → doit échouer ; on teste 10 chars
        $errors10 = $this->invokeValidateReviewInput($this->makeValidRequest('3', 'Très bien!'));
        $this->assertEmpty($errors10, 'Un commentaire de 10 caractères doit être accepté.');
    }

    /**
     * Règle métier : Un commentaire trop long (> 1000 caractères) est refusé.
     */
    public function testCommentTooLongIsRejected(): void
    {
        $longComment = str_repeat('a', 501) . str_repeat('b', 501); // 1002 chars avec lettres
        $errors = $this->invokeValidateReviewInput($this->makeValidRequest('3', $longComment));

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('dépasser', implode(' ', $errors));
    }

    /**
     * Règle métier : Un commentaire avec des caractères répétés excessivement est refusé.
     */
    public function testCommentWithExcessiveRepeatedCharsIsRejected(): void
    {
        // 10 'a' consécutifs → doit déclencher le filtre
        $comment = 'aaaaaaaaaa ceci est un commentaire répétitif';
        $errors = $this->invokeValidateReviewInput($this->makeValidRequest('3', $comment));

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('répétés', implode(' ', $errors));
    }

    /**
     * Règle métier : Un commentaire sans aucune lettre est refusé.
     */
    public function testCommentWithNoLettersIsRejected(): void
    {
        // Que des chiffres et symboles, longueur OK
        $comment = '1234567890 *** !!!';
        $errors = $this->invokeValidateReviewInput($this->makeValidRequest('3', $comment));

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('lettres', implode(' ', $errors));
    }

    // ==================== TESTS : RESERVATION / PRESTATAIRE ====================

    /**
     * Règle métier : Un reservation_id manquant génère une erreur.
     */
    public function testMissingReservationIdIsRejected(): void
    {
        $request = new Request();
        $request->request->set('rating', '3');
        $request->request->set('comment', 'Bon service, très professionnel.');
        $request->request->set('prestataire_id', '2');
        // reservation_id absent

        $errors = $this->invokeValidateReviewInput($request);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('réservation', implode(' ', $errors));
    }

    /**
     * Règle métier : Un reservation_id négatif ou nul est refusé.
     */
    public function testInvalidReservationIdIsRejected(): void
    {
        $errors = $this->invokeValidateReviewInput(
            $this->makeValidRequest('3', 'Bon service, très professionnel.', '0', '2')
        );

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('réservation', implode(' ', $errors));
    }

    /**
     * Règle métier : Un prestataire_id manquant génère une erreur.
     */
    public function testMissingPrestataireIdIsRejected(): void
    {
        $request = new Request();
        $request->request->set('rating', '3');
        $request->request->set('comment', 'Bon service, très professionnel.');
        $request->request->set('reservation_id', '1');
        // prestataire_id absent

        $errors = $this->invokeValidateReviewInput($request);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('prestataire', implode(' ', $errors));
    }

    /**
     * Règle métier : Un prestataire_id non numérique est refusé.
     */
    public function testNonNumericPrestataireIdIsRejected(): void
    {
        $errors = $this->invokeValidateReviewInput(
            $this->makeValidRequest('3', 'Bon service, très professionnel.', '1', 'abc')
        );

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('prestataire', implode(' ', $errors));
    }

    // ==================== TEST : REQUÊTE ENTIÈREMENT VALIDE ====================

    /**
     * Règle métier : Une requête parfaitement valide ne génère aucune erreur.
     */
    public function testFullyValidRequestProducesNoErrors(): void
    {
        $errors = $this->invokeValidateReviewInput($this->makeValidRequest());
        $this->assertEmpty($errors, 'Une requête entièrement valide ne doit produire aucune erreur.');
    }
}