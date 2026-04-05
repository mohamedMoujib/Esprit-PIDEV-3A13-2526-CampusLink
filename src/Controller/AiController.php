<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\AiAssistantService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/ai')]
#[IsGranted('ROLE_USER')]
class AiController extends AbstractController
{
    public function __construct(private readonly AiAssistantService $ai) {}

    #[Route('/generate-service', methods: ['POST'])]
    public function generateService(Request $req): JsonResponse
    {
        try {
            /** @var User $user */
            $user   = $this->getUser();
            $data   = json_decode($req->getContent(), true);
            $result = $this->ai->generateServiceDescription(
                $user->getId(), $data['idea'] ?? '', '', '', 0
            );
            $result = \is_array($result) ? $result : [];
            $norm   = [
                'title'       => $result['title'] ?? $result['titre'] ?? '',
                'description' => $result['description'] ?? $result['message'] ?? $result['description_service'] ?? '',
            ];

            return $this->json(array_merge(['success' => true], $norm));
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    #[Route('/generate-publication', methods: ['POST'])]
    public function generatePublication(Request $req): JsonResponse
    {
        try {
            /** @var User $user */
            $user   = $this->getUser();
            $data   = json_decode($req->getContent(), true);
            $result = $this->ai->helpWritePublication(
                $user->getId(),
                $data['type'] ?? 'demande',
                '',
                $data['idea'] ?? '',
                (float) ($data['budget'] ?? 0)
            );
            $result = \is_array($result) ? $result : [];
            $norm   = [
                'titre'   => $result['titre'] ?? $result['title'] ?? '',
                'message' => $result['message'] ?? $result['description'] ?? '',
            ];

            return $this->json(array_merge(['success' => true], $norm));
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    #[Route('/chat', methods: ['POST'])]
    public function chat(Request $req): JsonResponse
    {
        try {
            /** @var User $user */
            $user     = $this->getUser();
            $data     = json_decode($req->getContent(), true);
            $response = $this->ai->chat($user->getId(), $data['message'] ?? '', $data['history'] ?? []);
            return $this->json(['response' => $response]);
        } catch (\Exception $e) {
            return $this->json(['response' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }
}
