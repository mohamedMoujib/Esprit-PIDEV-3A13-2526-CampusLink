<?php

namespace App\Tests\Service;

use App\Service\GroqReviewsModerationService;
use PHPUnit\Framework\TestCase;

class GroqReviewsModerationServiceTest extends TestCase
{
    private GroqReviewsModerationService $service;

    protected function setUp(): void
    {
        // On crée le service avec des dépendances mockées
        $httpClient = $this->createMock(\Symfony\Contracts\HttpClient\HttpClientInterface::class);
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $groqApiKey = 'test-api-key';
        
        $this->service = new GroqReviewsModerationService($httpClient, $logger, $groqApiKey);
    }

    // ==================== TESTS POUR hasObviousBadWords() ====================

    /**
     * Règle métier : Un commentaire avec un gros mot doit être rejeté
     */
    public function testHasObviousBadWordsWithBadWord(): void
    {
        $comment = 'Ce tuteur est un connard';
        
        $result = $this->service->hasObviousBadWords($comment);
        
        $this->assertTrue($result);
    }

    /**
     * Règle métier : Un commentaire sans gros mot doit être accepté
     */
    public function testHasObviousBadWordsWithoutBadWord(): void
    {
        $comment = 'Excellent service, très professionnel';
        
        $result = $this->service->hasObviousBadWords($comment);
        
        $this->assertFalse($result);
    }
}
