<?php

namespace App\Controller\Etudiant;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ChatController extends AbstractController
{
    public function __construct(
        #[Autowire('%groq_api_key%')]
        private string $groqApiKey
    ) {}

    #[Route('/api/chat', name: 'api_chat', methods: ['POST'])]
    public function chat(
        Request $request,
        HttpClientInterface $client
    ): JsonResponse {

        try {
            $data = json_decode($request->getContent(), true);
            $messages = $data['messages'] ?? [];

            $response = $client->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'llama-3.1-8b-instant',
                    'messages' => $messages,
                ],
            ]);

            return new JsonResponse($response->toArray());

        } catch (\Throwable $e) {
            return new JsonResponse([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}