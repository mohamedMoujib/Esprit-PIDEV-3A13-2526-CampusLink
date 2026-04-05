<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class AiAssistantService
{
    private string $base;

    public function __construct(
        private readonly HttpClientInterface $client,
        string $n8nBaseUrl,
    ) {
        $this->base = rtrim($n8nBaseUrl, '/');
    }

    public function helpWritePublication(int $userId, string $type, string $category, string $idea, float $budget): array
    {
        return $this->post('/aide-publication', [
            'user_id' => $userId,
            'type' => $type,
            'categorie' => $category,
            'idee' => $idea,
            'budget' => $budget,
        ]);
    }

    public function generateServiceDescription(int $userId, string $title, string $category, string $skills, float $price): array
    {
        return $this->post('/generer-service', [
            'user_id' => $userId,
            'titre' => $title,
            'categorie' => $category,
            'competences' => $skills,
            'prix' => $price,
        ]);
    }

    public function chat(int $userId, string $message, array $history = []): string
    {
        $result = $this->post('/chatbot', [
            'user_id' => $userId,
            'message' => $message,
            'history' => $history,
        ]);

        return $result['response'] ?? '';
    }

    private function post(string $path, array $data): array
    {
        if ($this->base === '') {
            throw new \RuntimeException('N8N_BASE_URL is not configured.');
        }

        $response = $this->client->request('POST', $this->base . $path, [
            'json' => $data,
            'timeout' => 30,
        ]);

        $body = $response->toArray(false);

        return isset($body[0]) && is_array($body[0]) ? $body[0] : $body;
    }
}
