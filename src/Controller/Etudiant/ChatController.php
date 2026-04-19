<?php

namespace App\Controller\Etudiant;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ChatController extends AbstractController
{
    public function __construct(
        private ParameterBagInterface $params
    ) {}

    #[Route('/api/chat', name: 'api_chat', methods: ['POST'])]
    public function chat(
        Request $request,
        HttpClientInterface $client
    ): JsonResponse {

        try {
            $groq_api_key = $this->params->get('groq_api_key');

            $data = json_decode($request->getContent(), true);
            $messages = $data['messages'] ?? [];

            $response = $client->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $groq_api_key,
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