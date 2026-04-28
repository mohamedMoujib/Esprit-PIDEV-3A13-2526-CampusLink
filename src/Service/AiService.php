<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use League\CommonMark\CommonMarkConverter;
class AiService
{
    private HttpClientInterface $client;
    private string $apiKey;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
       $this->apiKey = $_SERVER['OPENAI_API_KEY'] ?? $_ENV['OPENAI_API_KEY'];
    }

    public function analyseRevenue(array $stats): string
    {
        $data = json_encode($stats);

        $response = $this->client->request('POST', 'https://openrouter.ai/api/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' =>
                        "Analyse ces revenus journaliers : ".$data.
                        ". Donne une analyse claire, une prédiction mensuelle et des conseils pour augmenter les revenus."
                    ]
                ]
            ]
        ]);

        $result = $response->toArray();

        return $result['choices'][0]['message']['content'] ?? "Erreur IA";
    }
}