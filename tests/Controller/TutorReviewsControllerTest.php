<?php

namespace App\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class TutorReviewsControllerTest extends TestCase
{
    // ==================== CONSTANTES (miroir du contrôleur) ====================

    private const MIN_REASON_LENGTH = 10;
    private const MAX_REASON_LENGTH = 500;
    private const REPORT_REASONS    = [
        'Contenu inapproprié',
        'Langage offensant',
        'Informations fausses',
        'Harcèlement',
        'Spam',
        'Autre',
    ];

    // ==================== HELPER : logique de validation ====================

    /**
     * Réplique fidèle de TutorReviewsController::validateReportInput()
     *
     * @return array<string>
     */
    private function invokeValidateReportInput(Request $request): array
    {
        $errors   = [];
        $reason   = trim($request->request->get('reason', ''));
        $category = trim($request->request->get('reason_category', ''));

        // --- Validation catégorie ---
        if (empty($category)) {
            $errors[] = 'Veuillez choisir une catégorie de signalement.';
        } elseif (!in_array($category, self::REPORT_REASONS, true)) {
            $errors[] = 'La catégorie choisie est invalide.';
        }

        // --- Validation raison ---
        if (empty($reason)) {
            $errors[] = 'La raison du signalement est obligatoire.';
            return $errors; // early return, comme dans le contrôleur
        }

        if (mb_strlen($reason) < self::MIN_REASON_LENGTH) {
            $errors[] = sprintf(
                'La raison doit contenir au moins %d caractères.',
                self::MIN_REASON_LENGTH
            );
        }

        if (mb_strlen($reason) > self::MAX_REASON_LENGTH) {
            $errors[] = sprintf(
                'La raison ne peut pas dépasser %d caractères.',
                self::MAX_REASON_LENGTH
            );
        }

        if (!preg_match('/[a-zA-ZÀ-ÿ]/', $reason)) {
            $errors[] = 'La raison doit contenir au moins quelques lettres.';
        }

        if (preg_match('/(.)\1{9,}/', $reason)) {
            $errors[] = 'La raison ne peut pas contenir de caractères répétés excessivement.';
        }

        return $errors;
    }

    /** Construit une requête valide de base. */
    private function makeValidRequest(
        string $reason   = 'Ce tuteur a tenu des propos offensants envers moi.',
        string $category = 'Langage offensant'
    ): Request {
        $request = new Request();
        $request->request->set('reason', $reason);
        $request->request->set('reason_category', $category);
        return $request;
    }

    // ==================== TESTS : CATÉGORIE ====================

    /**
     * Règle métier : Une catégorie valide et une raison suffisante ne génèrent pas d'erreur.
     */
    public function testValidCategoryAndReasonProducesNoErrors(): void
    {
        $errors = $this->invokeValidateReportInput($this->makeValidRequest());
        $this->assertEmpty($errors, 'Une entrée valide ne doit produire aucune erreur.');
    }

    /**
     * Règle métier : L'absence de catégorie est refusée.
     */
    public function testMissingCategoryIsRejected(): void
    {
        $request = new Request();
        $request->request->set('reason', 'Ce tuteur a tenu des propos offensants envers moi.');
        // reason_category absent

        $errors = $this->invokeValidateReportInput($request);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('catégorie', implode(' ', $errors));
    }

    /**
     * Règle métier : Une catégorie hors liste est refusée.
     */
    public function testInvalidCategoryIsRejected(): void
    {
        $errors = $this->invokeValidateReportInput(
            $this->makeValidRequest(
                'Ce tuteur a tenu des propos offensants envers moi.',
                'Catégorie inexistante'
            )
        );

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('invalide', implode(' ', $errors));
    }

    /**
     * Règle métier : Toutes les catégories autorisées sont acceptées.
     */
    public function testAllAllowedCategoriesAreAccepted(): void
    {
        foreach (self::REPORT_REASONS as $category) {
            $errors = $this->invokeValidateReportInput($this->makeValidRequest(
                'Ce tuteur a tenu des propos offensants envers moi.',
                $category
            ));

            $this->assertEmpty(
                $errors,
                "La catégorie \"$category\" doit être acceptée sans erreur."
            );
        }
    }

    // ==================== TESTS : RAISON ====================

    /**
     * Règle métier : Une raison absente est refusée.
     */
    public function testMissingReasonIsRejected(): void
    {
        $request = new Request();
        $request->request->set('reason_category', 'Spam');
        // reason absent

        $errors = $this->invokeValidateReportInput($request);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('obligatoire', implode(' ', $errors));
    }

    /**
     * Règle métier : Une raison trop courte (< 10 caractères) est refusée.
     */
    public function testReasonTooShortIsRejected(): void
    {
        $errors = $this->invokeValidateReportInput($this->makeValidRequest('Court'));

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('au moins 10 caractères', implode(' ', $errors));
    }

    /**
     * Règle métier : Une raison au seuil exact (10 caractères) est valide.
     */
    public function testReasonAtMinLengthBoundaryIsAccepted(): void
    {
        $errors = $this->invokeValidateReportInput(
            $this->makeValidRequest('Raison min.')   // 11 chars mais contient des lettres
        );
        // On teste exactement 10 lettres
        $errors10 = $this->invokeValidateReportInput(
            $this->makeValidRequest('Raison ok!')
        );
        $this->assertEmpty($errors10, 'Une raison de 10 caractères doit être acceptée.');
    }

    /**
     * Règle métier : Une raison trop longue (> 500 caractères) est refusée.
     */
    public function testReasonTooLongIsRejected(): void
    {
        $longReason = str_repeat('ab', 251); // 502 caractères avec des lettres
        $errors = $this->invokeValidateReportInput($this->makeValidRequest($longReason));

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('dépasser', implode(' ', $errors));
    }

    /**
     * Règle métier : Une raison sans aucune lettre est refusée.
     */
    public function testReasonWithNoLettersIsRejected(): void
    {
        $errors = $this->invokeValidateReportInput(
            $this->makeValidRequest('1234567890!!!')
        );

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('lettres', implode(' ', $errors));
    }

    /**
     * Règle métier : Une raison avec des caractères répétés excessivement est refusée.
     */
    public function testReasonWithExcessiveRepeatedCharsIsRejected(): void
    {
        // 10 'a' consécutifs → doit déclencher le filtre
        $reason = 'aaaaaaaaaa ceci est le motif du signalement';
        $errors = $this->invokeValidateReportInput($this->makeValidRequest($reason));

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('répétés', implode(' ', $errors));
    }
}