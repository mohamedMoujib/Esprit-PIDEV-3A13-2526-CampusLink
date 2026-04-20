<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AiAssistantService
{
    private string $n8nBase;
    private string $openRouterKey;

    private const OPENROUTER_URL = 'https://openrouter.ai/api/v1/chat/completions';
    private const MODEL = 'openai/gpt-4o';

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly LoggerInterface $logger,
        ?string $n8nBaseUrl = null,
        ?string $openRouterApiKey = null,
    ) {
        $this->n8nBase = $n8nBaseUrl !== null && $n8nBaseUrl !== '' ? rtrim($n8nBaseUrl, '/') : '';
        $this->openRouterKey = $openRouterApiKey ?? '';
    }

    public function helpWritePublication(int $userId, string $type, string $category, string $idea, float $budget, ?int $categoryId = null): array
    {
        $payload = [
            'user_id'     => $userId,
            'type'        => $type,
            'categorie'   => $category,
            'idee'        => $idea,
            'budget'      => $budget,
            'category_id' => $categoryId,
        ];

        // Try n8n first, fall back to direct OpenRouter
        $result = $this->postN8n('/aide-publication', $payload);

        if ($result !== null) {
            return $result;
        }

        return $this->generatePublicationDirect($idea, $type, $budget);
    }

    public function generateServiceDescription(int $userId, string $title, string $category, string $skills, float $price, ?int $categoryId = null): array
    {
        $payload = [
            'user_id'      => $userId,
            'titre'        => $title,
            'categorie'    => $category,
            'competences'  => $skills,
            'prix'         => $price,
            'category_id'  => $categoryId,
        ];

        // Try n8n first, fall back to direct OpenRouter
        $result = $this->postN8n('/generer-service', $payload);

        if ($result !== null) {
            return $result;
        }

        return $this->generateServiceDirect($title, $category, $price);
    }

    public function chat(int $userId, string $message, array $history = []): string
    {
        $payload = [
            'user_id' => $userId,
            'message' => $message,
            'history' => $history,
        ];

        $result = $this->postN8n('/chatbot', $payload);

        if ($result !== null) {
            return $result['response'] ?? '';
        }

        return $this->chatDirect($message, $history);
    }

    // ─── n8n HTTP call ──────────────────────────────────────────────

    private function postN8n(string $path, array $data): ?array
    {
        if ($this->n8nBase === '') {
            $this->logger->info('N8N_BASE_URL not configured, using direct OpenRouter fallback.');
            return null;
        }

        try {
            $url = $this->n8nBase . $path;
            $this->logger->info('Calling n8n: ' . $url);

            $response = $this->client->request('POST', $url, [
                'json'    => $data,
                'timeout' => 30,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 400) {
                $this->logger->warning("n8n returned HTTP {$statusCode} for {$path}, falling back to OpenRouter.");
                return null;
            }

            $body = $response->toArray(false);

            // n8n may wrap response in an array
            $result = isset($body[0]) && is_array($body[0]) ? $body[0] : $body;

            $this->logger->info("n8n response OK for {$path}");

            return $result;
        } catch (\Throwable $e) {
            $this->logger->warning("n8n call failed for {$path}: " . $e->getMessage() . '. Falling back to OpenRouter.');
            return null;
        }
    }

    // ─── Direct OpenRouter fallback ─────────────────────────────────

    private function callOpenRouter(string $systemPrompt, string $userPrompt): array
    {
        if ($this->openRouterKey === '') {
            throw new \RuntimeException(
                'Neither N8N_BASE_URL nor HUGGINGFACE_API_KEY (OpenRouter) is configured. '
                . 'At least one is required for AI features.'
            );
        }

        try {
            $response = $this->client->request('POST', self::OPENROUTER_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openRouterKey,
                    'Content-Type'  => 'application/json',
                    'HTTP-Referer'  => 'https://campuslink.tn',
                    'X-Title'       => 'CampusLink',
                ],
                'json' => [
                    'model'       => self::MODEL,
                    'messages'    => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user',   'content' => $userPrompt],
                    ],
                    'temperature' => 0.7,
                    'max_tokens'  => 500,
                ],
                'timeout' => 45,
            ]);

            $data    = $response->toArray(false);
            $content = $data['choices'][0]['message']['content'] ?? '';

            // Strip markdown code fences
            $content = preg_replace('/```json\s*|\s*```/', '', $content);
            $parsed  = json_decode(trim($content), true);

            if (is_array($parsed)) {
                return $parsed;
            }

            // If not valid JSON, return as raw text
            return ['response' => trim($content)];
        } catch (\Throwable $e) {
            $this->logger->error('OpenRouter direct call failed: ' . $e->getMessage());
            throw new \RuntimeException('AI service unavailable: ' . $e->getMessage());
        }
    }

    private function generatePublicationDirect(string $idea, string $type, float $budget): array
    {
        $typeLabel = $type === 'VENTE_OBJET' ? 'vente d\'objet' : 'demande de service';
        $budgetStr = $budget > 0 ? " Budget: {$budget} DT." : '';

        $system = 'Tu es un assistant CampusLink. Tu aides les étudiants à rédiger des publications. '
            . 'Retourne UNIQUEMENT du JSON valide avec les clés "titre" et "message". '
            . 'Le titre doit être accrocheur (max 100 caractères). '
            . 'Le message doit être clair et détaillé (max 500 caractères).';

        $user = "Génère une publication de type \"{$typeLabel}\" pour l'idée suivante: \"{$idea}\".{$budgetStr}";

        $result = $this->callOpenRouter($system, $user);

        return [
            'titre'   => $result['titre'] ?? $result['title'] ?? '',
            'message' => $result['message'] ?? $result['description'] ?? '',
        ];
    }

    private function generateServiceDirect(string $title, string $category, float $price): array
    {
        $catStr   = $category !== '' ? " Catégorie: {$category}." : '';
        $priceStr = $price > 0 ? " Prix: {$price} DT." : '';

        $system = 'Tu es un assistant CampusLink. Tu aides les prestataires à créer des services. '
            . 'Retourne UNIQUEMENT du JSON valide avec les clés "title" et "description". '
            . 'Le titre doit être professionnel (max 100 caractères). '
            . 'La description doit être complète et engageante (max 500 caractères).';

        $user = "Génère un service pour: \"{$title}\".{$catStr}{$priceStr}";

        $result = $this->callOpenRouter($system, $user);

        return [
            'title'       => $result['title'] ?? $result['titre'] ?? '',
            'description' => $result['description'] ?? $result['message'] ?? '',
        ];
    }

    private function chatDirect(string $message, array $history): string
    {
        $system = 'Tu es l\'assistant IA de CampusLink, une plateforme universitaire. '
            . 'Tu aides les étudiants avec leurs questions sur les services, publications et la plateforme. '
            . 'Réponds de façon concise et utile en français.';

        $messages = [['role' => 'system', 'content' => $system]];

        foreach ($history as $msg) {
            $role = ($msg['role'] ?? 'user') === 'assistant' ? 'assistant' : 'user';
            $messages[] = ['role' => $role, 'content' => $msg['content'] ?? ''];
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        if ($this->openRouterKey === '') {
            throw new \RuntimeException('AI service is not configured.');
        }

        try {
            $response = $this->client->request('POST', self::OPENROUTER_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openRouterKey,
                    'Content-Type'  => 'application/json',
                    'HTTP-Referer'  => 'https://campuslink.tn',
                    'X-Title'       => 'CampusLink',
                ],
                'json' => [
                    'model'       => self::MODEL,
                    'messages'    => $messages,
                    'temperature' => 0.7,
                    'max_tokens'  => 500,
                ],
                'timeout' => 30,
            ]);

            $data = $response->toArray(false);
            return $data['choices'][0]['message']['content'] ?? 'Désolé, je n\'ai pas pu générer de réponse.';
        } catch (\Throwable $e) {
            $this->logger->error('OpenRouter chat failed: ' . $e->getMessage());
            return 'Erreur: impossible de contacter l\'IA. Réessayez plus tard.';
        }
    }
}
