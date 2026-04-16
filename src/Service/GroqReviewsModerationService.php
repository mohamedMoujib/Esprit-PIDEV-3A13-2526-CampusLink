<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class GroqReviewsModerationService
{
    private const GROQ_API_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const MODEL = 'llama-3.3-70b-versatile'; // Modèle actif et performant

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $groqApiKey
    ) {}

    /**
     * Analyse un commentaire pour détecter du contenu inapproprié
     * 
     * @param string $comment Le commentaire à analyser
     * @return array ['is_appropriate' => bool, 'reason' => string|null, 'confidence' => float]
     */
    public function analyzeComment(string $comment): array
    {
        try {
            $prompt = $this->buildModerationPrompt($comment);
            
            $response = $this->httpClient->request('POST', self::GROQ_API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => self::MODEL,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Tu es un modérateur expert qui analyse les commentaires pour détecter du contenu inapproprié. Tu réponds UNIQUEMENT en JSON.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.3, // Faible température pour des réponses cohérentes
                    'max_tokens' => 200,
                ],
                'timeout' => 10,
            ]);

            $data = $response->toArray();
            $aiResponse = $data['choices'][0]['message']['content'] ?? '';

            return $this->parseAiResponse($aiResponse);

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'analyse Groq', [
                'error' => $e->getMessage(),
                'comment' => substr($comment, 0, 100),
            ]);

            // En cas d'erreur, on accepte le commentaire (fail-safe)
            return [
                'is_appropriate' => true,
                'reason' => null,
                'confidence' => 0.0,
                'error' => 'Service de modération temporairement indisponible'
            ];
        }
    }

    /**
     * Construit le prompt pour l'IA
     */
    private function buildModerationPrompt(string $comment): string
    {
        return <<<PROMPT
Analyse ce commentaire d'avis étudiant et détermine s'il est approprié ou non.

COMMENTAIRE À ANALYSER :
"$comment"

CRITÈRES DE REJET :
1. Insultes ou gros mots
2. Langage vulgaire ou obscène
3. Menaces ou violence
4. Discrimination (racisme, sexisme, homophobie, etc.)
5. Harcèlement ou attaque personnelle
6. Spam ou contenu promotionnel
7. Contenu hors sujet ou incohérent

CRITÈRES D'ACCEPTATION :
- Critique constructive (même négative)
- Frustration exprimée de manière respectueuse
- Commentaire honnête sur l'expérience

RÉPONDS UNIQUEMENT avec ce format JSON (sans markdown, sans backticks) :
{
  "is_appropriate": true ou false,
  "reason": "Raison du rejet si inappropriate, sinon null",
  "confidence": 0.0 à 1.0,
  "detected_issues": ["liste des problèmes détectés"]
}
PROMPT;
    }

    /**
     * Parse la réponse de l'IA
     */
    private function parseAiResponse(string $aiResponse): array
    {
        // Nettoyer la réponse (enlever les backticks markdown si présents)
        $aiResponse = trim($aiResponse);
        $aiResponse = preg_replace('/^```json\s*/', '', $aiResponse);
        $aiResponse = preg_replace('/\s*```$/', '', $aiResponse);

        try {
            $parsed = json_decode($aiResponse, true, 512, JSON_THROW_ON_ERROR);

            return [
                'is_appropriate' => $parsed['is_appropriate'] ?? true,
                'reason' => $parsed['reason'] ?? null,
                'confidence' => (float) ($parsed['confidence'] ?? 0.0),
                'detected_issues' => $parsed['detected_issues'] ?? [],
            ];
        } catch (\JsonException $e) {
            $this->logger->warning('Impossible de parser la réponse Groq', [
                'response' => $aiResponse,
                'error' => $e->getMessage(),
            ]);

            // En cas d'erreur de parsing, on accepte (fail-safe)
            return [
                'is_appropriate' => true,
                'reason' => null,
                'confidence' => 0.0,
                'detected_issues' => [],
            ];
        }
    }

    /**
     * Vérifie rapidement si un commentaire contient des mots interdits évidents
     * (Vérification locale rapide avant d'appeler l'IA)
     */
    public function hasObviousBadWords(string $comment): bool
    {
        $badWords = [
            'merde', 'putain', 'connard', 'salaud', 'enculé', 'fdp',
            'con', 'conne', 'pute', 'bite', 'couille', 'chier',
            // Ajoutez d'autres mots selon vos besoins
        ];

        $commentLower = mb_strtolower($comment);

        foreach ($badWords as $word) {
            if (str_contains($commentLower, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Génère une suggestion de commentaire basée sur le contexte
     * 
     * @param string $partialText Le texte déjà saisi par l'utilisateur
     * @param int $rating La note donnée (-5 à +5)
     * @param string $serviceName Le nom du service/cours
     * @return array ['suggestion' => string, 'confidence' => float]
     */
    public function generateCommentSuggestion(string $partialText, int $rating, string $serviceName = ''): array
    {
        try {
            $sentiment = $rating > 0 ? 'positif' : ($rating < 0 ? 'négatif' : 'neutre');
            $ratingAbs = abs($rating);

            $prompt = <<<PROMPT
Tu es un assistant qui aide les étudiants à rédiger des avis constructifs sur des cours/tuteurs.

CONTEXTE :
- Service/Cours : "$serviceName"
- Note donnée : $rating/5 (sentiment $sentiment, intensité $ratingAbs/5)
- Texte déjà saisi : "$partialText"

INSTRUCTIONS :
1. Continue le texte de manière naturelle et cohérente
2. Le ton doit correspondre à la note (positif si >0, négatif si <0)
3. Reste professionnel et constructif
4. Maximum 50 mots pour la suggestion
5. Ne répète pas ce qui est déjà écrit
6. Si le texte est vide, propose un début de phrase

RÉPONDS UNIQUEMENT avec ce format JSON (sans markdown, sans backticks) :
{
  "suggestion": "ta suggestion de texte ici",
  "confidence": 0.0 à 1.0
}
PROMPT;

            $response = $this->httpClient->request('POST', self::GROQ_API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => self::MODEL,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Tu es un assistant qui aide à rédiger des avis constructifs. Tu réponds UNIQUEMENT en JSON.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.7, // Plus créatif pour les suggestions
                    'max_tokens' => 150,
                ],
                'timeout' => 8,
            ]);

            $data = $response->toArray();
            $aiResponse = $data['choices'][0]['message']['content'] ?? '';

            return $this->parseSuggestionResponse($aiResponse);

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la génération de suggestion Groq', [
                'error' => $e->getMessage(),
                'partial_text' => substr($partialText, 0, 50),
            ]);

            return [
                'suggestion' => '',
                'confidence' => 0.0,
                'error' => 'Service de suggestion temporairement indisponible'
            ];
        }
    }

    /**
     * Parse la réponse de suggestion de l'IA
     */
    private function parseSuggestionResponse(string $aiResponse): array
    {
        // Nettoyer la réponse
        $aiResponse = trim($aiResponse);
        $aiResponse = preg_replace('/^```json\s*/', '', $aiResponse);
        $aiResponse = preg_replace('/\s*```$/', '', $aiResponse);

        try {
            $parsed = json_decode($aiResponse, true, 512, JSON_THROW_ON_ERROR);

            return [
                'suggestion' => $parsed['suggestion'] ?? '',
                'confidence' => (float) ($parsed['confidence'] ?? 0.0),
            ];
        } catch (\JsonException $e) {
            $this->logger->warning('Impossible de parser la suggestion Groq', [
                'response' => $aiResponse,
                'error' => $e->getMessage(),
            ]);

            return [
                'suggestion' => '',
                'confidence' => 0.0,
            ];
        }
    }
}
