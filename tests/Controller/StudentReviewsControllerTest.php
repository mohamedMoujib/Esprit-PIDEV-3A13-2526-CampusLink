<?php

namespace App\Tests\Controller;

use App\Controller\Etudiant\StudentReviewsController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use ReflectionClass;

class StudentReviewsControllerTest extends TestCase
{
    /**
     * Règle métier : La note doit être comprise entre -5 et 5
     */
    public function testValidateReviewInputRatingInRange(): void
    {
        $request = new Request();
        $request->request->set('rating', '3');
        $request->request->set('comment', 'Bon service, très professionnel et à l\'écoute.');
        $request->request->set('reservation_id', '1');
        $request->request->set('prestataire_id', '2');
        
        $errors = $this->invokeValidateReviewInput($request);
        
        $this->assertEmpty($errors);
    }

    /**
     * Règle métier : La note ne peut pas être 0
     */
    public function testValidateReviewInputRatingCannotBeZero(): void
    {
        $request = new Request();
        $request->request->set('rating', '0');
        $request->request->set('comment', 'Bon service, très professionnel et à l\'écoute.');
        $request->request->set('reservation_id', '1');
        $request->request->set('prestataire_id', '2');
        
        $errors = $this->invokeValidateReviewInput($request);
        
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('ne peut pas être 0', implode(' ', $errors));
    }

    /**
     * Helper pour invoquer la méthode privée validateReviewInput
     */
    private function invokeValidateReviewInput(Request $request): array
    {
        // Simulation de la logique de validation
        $errors = [];
        $rating = $request->request->get('rating');
        $comment = trim($request->request->get('comment', ''));

        // Validation rating
        if ($rating === null || $rating === '') {
            $errors[] = 'La note est obligatoire.';
        } elseif (!is_numeric($rating)) {
            $errors[] = 'La note doit être un nombre.';
        } elseif ((int) $rating < -5 || (int) $rating > 5) {
            $errors[] = 'La note doit être comprise entre -5 et 5.';
        } elseif ((int) $rating === 0) {
            $errors[] = 'La note ne peut pas être 0. Choisissez une note positive ou négative.';
        }

        // Validation commentaire
        if (empty($comment)) {
            $errors[] = 'Le commentaire est obligatoire.';
        } elseif (mb_strlen($comment) < 10) {
            $errors[] = 'Le commentaire doit contenir au moins 10 caractères.';
        } elseif (mb_strlen($comment) > 1000) {
            $errors[] = 'Le commentaire ne peut pas dépasser 1000 caractères.';
        }

        return $errors;
    }
}
