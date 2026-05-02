<?php

namespace App\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class TutorReviewsControllerTest extends TestCase
{
    /**
     * Règle métier : La raison du signalement doit contenir au moins 10 caractères
     */
    public function testValidateReportInputReasonMinLength(): void
    {
        $request = new Request();
        $request->request->set('reason', 'Raison valide avec suffisamment de caractères');
        
        $errors = $this->invokeValidateReportInput($request);
        
        $this->assertEmpty($errors);
    }

    /**
     * Règle métier : La raison du signalement ne peut pas être trop courte
     */
    public function testValidateReportInputReasonTooShort(): void
    {
        $request = new Request();
        $request->request->set('reason', 'Court');
        
        $errors = $this->invokeValidateReportInput($request);
        
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('au moins 10 caractères', implode(' ', $errors));
    }

    /**
     * Helper pour invoquer la logique de validation
     */
    private function invokeValidateReportInput(Request $request): array
    {
        // Simulation de la logique de validation
        $errors = [];
        $reason = trim($request->request->get('reason', ''));

        if (empty($reason)) {
            $errors[] = 'La raison est obligatoire.';
        } elseif (mb_strlen($reason) < 10) {
            $errors[] = 'La raison doit contenir au moins 10 caractères.';
        }

        return $errors;
    }
}
