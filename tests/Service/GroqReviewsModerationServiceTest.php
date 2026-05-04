<?php

namespace App\Tests\Service;

use App\Service\GroqReviewsModerationService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class GroqReviewsModerationServiceTest extends TestCase
{
    private GroqReviewsModerationService $service;
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->logger     = $this->createMock(LoggerInterface::class);

        $this->service = new GroqReviewsModerationService(
            $this->httpClient,
            $this->logger,
            'test-api-key'
        );
    }

    // ==================== TESTS : hasObviousBadWords() ====================

    /**
     * Règle métier : Un commentaire sain ne contient aucun gros mot.
     */
    public function testHasObviousBadWordsReturnsFalseForCleanComment(): void
    {
        $result = $this->service->hasObviousBadWords('Excellent service, très professionnel.');
        $this->assertFalse($result, 'Un commentaire propre ne doit pas être signalé.');
    }

    /**
     * Règle métier : Un commentaire avec un gros mot explicite est détecté.
     */
    public function testHasObviousBadWordsReturnsTrueForBadWord(): void
    {
        $result = $this->service->hasObviousBadWords('Ce tuteur est un connard.');
        $this->assertTrue($result, 'Un commentaire avec un gros mot doit être détecté.');
    }

    /**
     * Règle métier : La détection est insensible à la casse (CONNARD = connard).
     */
    public function testHasObviousBadWordsIsCaseInsensitive(): void
    {
        $result = $this->service->hasObviousBadWords('Quel CONNARD ce prof !');
        $this->assertTrue($result, 'La vérification doit être insensible à la casse.');
    }

    /**
     * Règle métier : Un gros mot en milieu de phrase est détecté.
     */
    public function testHasObviousBadWordsDetectsWordInMiddleOfSentence(): void
    {
        $result = $this->service->hasObviousBadWords('Ce cours est vraiment de la merde, zéro pédagogie.');
        $this->assertTrue($result, 'Un gros mot en milieu de phrase doit être détecté.');
    }

    /**
     * Règle métier : Une chaîne vide ne déclenche pas le filtre.
     */
    public function testHasObviousBadWordsReturnsFalseForEmptyString(): void
    {
        $result = $this->service->hasObviousBadWords('');
        $this->assertFalse($result, 'Une chaîne vide ne doit pas déclencher le filtre.');
    }

    /**
     * Règle métier : Un commentaire avec "pute" est détecté.
     */
    public function testHasObviousBadWordsDetectsVariousBadWords(): void
    {
        $badComments = [
            'C\'est une pute',
            'va te faire foutre putain',
            'quel fdp ce tuteur',
            'salaud va',
        ];

        foreach ($badComments as $comment) {
            $this->assertTrue(
                $this->service->hasObviousBadWords($comment),
                "Le commentaire \"$comment\" doit être détecté comme inapproprié."
            );
        }
    }

    // ==================== TESTS : analyzeComment() — fail-safe ====================

    /**
     * Règle métier : En cas d'erreur HTTP, le service accepte le commentaire (fail-safe).
     */
    public function testAnalyzeCommentReturnsTrueWhenHttpClientThrowsException(): void
    {
        $this->httpClient
            ->method('request')
            ->willThrowException(new \RuntimeException('Network error'));

        // Le logger doit enregistrer l'erreur
        $this->logger
            ->expects($this->once())
            ->method('error');

        $result = $this->service->analyzeComment('Un commentaire quelconque.');

        $this->assertTrue(
            $result['is_appropriate'],
            'En cas d\'erreur réseau, le commentaire doit être accepté (fail-safe).'
        );
        $this->assertArrayHasKey('error', $result, 'Le résultat doit contenir une clé "error".');
    }

    /**
     * Règle métier : Une réponse JSON valide "appropriée" est correctement parsée.
     */
    public function testAnalyzeCommentParsesAppropriateResponse(): void
    {
        $jsonPayload = json_encode([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'is_appropriate'  => true,
                        'reason'          => null,
                        'confidence'      => 0.95,
                        'detected_issues' => [],
                    ])
                ]
            ]]
        ]);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(json_decode($jsonPayload, true));

        $this->httpClient->method('request')->willReturn($response);

        $result = $this->service->analyzeComment('Super cours, très instructif !');

        $this->assertTrue($result['is_appropriate']);
        $this->assertNull($result['reason']);
        $this->assertEqualsWithDelta(0.95, $result['confidence'], 0.001);
    }

    /**
     * Règle métier : Une réponse JSON valide "inappropriée" est correctement parsée.
     */
    public function testAnalyzeCommentParsesInappropriateResponse(): void
    {
        $jsonPayload = json_encode([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'is_appropriate'  => false,
                        'reason'          => 'Langage offensant détecté',
                        'confidence'      => 0.99,
                        'detected_issues' => ['Insulte'],
                    ])
                ]
            ]]
        ]);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(json_decode($jsonPayload, true));

        $this->httpClient->method('request')->willReturn($response);

        $result = $this->service->analyzeComment('Commentaire offensant.');

        $this->assertFalse($result['is_appropriate']);
        $this->assertEquals('Langage offensant détecté', $result['reason']);
        $this->assertContains('Insulte', $result['detected_issues']);
    }

    /**
     * Règle métier : Si la réponse de l'IA n'est pas du JSON valide, le service accepte (fail-safe).
     */
    public function testAnalyzeCommentAcceptsWhenResponseIsNotValidJson(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'choices' => [[
                'message' => ['content' => 'ceci n\'est pas du JSON valide ###']
            ]]
        ]);

        $this->httpClient->method('request')->willReturn($response);

        $this->logger->expects($this->once())->method('warning');

        $result = $this->service->analyzeComment('Un commentaire quelconque.');

        $this->assertTrue(
            $result['is_appropriate'],
            'En cas de JSON invalide, le commentaire doit être accepté (fail-safe).'
        );
    }

    // ==================== TESTS : generateCommentSuggestion() ====================

    /**
     * Règle métier : Si le texte est trop court (< 3 chars) et sans nom de service,
     * la suggestion retournée est vide sans appel HTTP.
     */
    public function testGenerateCommentSuggestionReturnsEmptyForTooShortText(): void
    {
        // On vérifie uniquement le résultat retourné, pas le nombre d'appels HTTP
        // (la condition dans le service est : mb_strlen < 3 AND empty($serviceName))
        $result = $this->service->generateCommentSuggestion('Hi', 3, '');

        $this->assertEquals('', $result['suggestion'], 'La suggestion doit être vide pour un texte trop court.');
        $this->assertEquals(0.0, $result['confidence'], 'La confiance doit être 0 pour un texte trop court.');
    }

    /**
     * Règle métier : En cas d'erreur HTTP, la suggestion retourne une chaîne vide (fail-safe).
     */
    public function testGenerateCommentSuggestionReturnsFallbackOnHttpError(): void
    {
        $this->httpClient
            ->method('request')
            ->willThrowException(new \RuntimeException('Timeout'));

        $this->logger->expects($this->once())->method('error');

        $result = $this->service->generateCommentSuggestion('Le cours était', 4, 'Mathématiques');

        $this->assertEquals('', $result['suggestion']);
        $this->assertArrayHasKey('error', $result);
    }

    /**
     * Règle métier : Une réponse valide de l'IA est correctement parsée en suggestion.
     */
    public function testGenerateCommentSuggestionParsesValidResponse(): void
    {
        $jsonPayload = json_encode([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'suggestion' => 'très bien expliqué et pédagogique.',
                        'confidence' => 0.85,
                    ])
                ]
            ]]
        ]);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(json_decode($jsonPayload, true));

        $this->httpClient->method('request')->willReturn($response);

        $result = $this->service->generateCommentSuggestion('Le cours était', 4, 'Mathématiques');

        $this->assertEquals('très bien expliqué et pédagogique.', $result['suggestion']);
        $this->assertEqualsWithDelta(0.85, $result['confidence'], 0.001);
    }
}