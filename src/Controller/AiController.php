<?php

namespace App\Controller;

use App\Entity\Categorie;
use App\Entity\Publication;
use App\Entity\Service;
use App\Entity\User;
use App\Service\AiAssistantService;
use Doctrine\ORM\EntityManagerInterface;
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

    // ─── Generate only (existing — kept for backward compatibility) ──────

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
            if (str_contains($e->getMessage(), 'not configured')) {
                return $this->json([
                    'success' => false,
                    'message' => 'Le service IA n\'est pas configuré. Veuillez contacter l\'administrateur.',
                ], 503);
            }

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
            if (str_contains($e->getMessage(), 'not configured')) {
                return $this->json([
                    'success' => false,
                    'message' => 'Le service IA n\'est pas configuré. Veuillez contacter l\'administrateur.',
                ], 503);
            }

            return $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── Generate AND save to DB ────────────────────────────────────────

    #[Route('/generate-and-save-service', methods: ['POST'])]
    public function generateAndSaveService(Request $req, EntityManagerInterface $em): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();

            if (!\in_array($user->getUserType(), ['PRESTATAIRE', 'ADMIN'], true)) {
                return $this->json(['success' => false, 'message' => 'Seuls les prestataires peuvent créer des services.'], 403);
            }

            $data       = json_decode($req->getContent(), true);
            $idea       = trim($data['idea'] ?? '');
            $categoryId = !empty($data['category_id']) ? (int) $data['category_id'] : null;
            $price      = (float) ($data['price'] ?? 0);

            if ($idea === '') {
                return $this->json(['success' => false, 'message' => 'Veuillez décrire votre service.'], 422);
            }

            // Call AI — n8n will generate AND save to DB
            $result = $this->ai->generateServiceDescription(
                $user->getId(),
                $idea,
                '',
                '',
                $price > 0 ? $price : 10,
                $categoryId
            );
            $result = \is_array($result) ? $result : [];

            // If n8n already saved (result contains 'saved' flag + 'id')
            if (!empty($result['saved']) && !empty($result['id'])) {
                return $this->json([
                    'success'     => true,
                    'saved'       => true,
                    'id'          => $result['id'],
                    'title'       => $result['title'] ?? '',
                    'description' => $result['description'] ?? '',
                ]);
            }

            // Fallback: n8n didn't save (direct OpenRouter), save via Doctrine
            $title       = $result['title'] ?? $result['titre'] ?? 'Service';
            $description = $result['description'] ?? $result['message'] ?? '';

            $service = new Service();
            $service->setTitle(mb_substr($title, 0, 200))
                ->setDescription($description)
                ->setPrice(number_format($price > 0 ? $price : 10, 2, '.', ''))
                ->setUser($user)
                ->setStatus('EN_ATTENTE');

            if ($categoryId) {
                $cat = $em->find(Categorie::class, $categoryId);
                if ($cat) {
                    $service->setCategory($cat);
                }
            }

            $em->persist($service);
            $em->flush();

            return $this->json([
                'success'     => true,
                'saved'       => true,
                'id'          => $service->getId(),
                'title'       => $service->getTitle(),
                'description' => $service->getDescription(),
            ]);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'not configured')) {
                return $this->json([
                    'success' => false,
                    'message' => 'Le service IA n\'est pas configuré. Veuillez contacter l\'administrateur.',
                ], 503);
            }

            return $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    #[Route('/generate-and-save-publication', methods: ['POST'])]
    public function generateAndSavePublication(Request $req, EntityManagerInterface $em): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();

            if (!\in_array($user->getUserType(), ['ETUDIANT', 'ADMIN'], true)) {
                return $this->json(['success' => false, 'message' => 'Seuls les étudiants peuvent créer des publications.'], 403);
            }

            $data       = json_decode($req->getContent(), true);
            $idea       = trim($data['idea'] ?? '');
            $type       = $data['type'] ?? 'DEMANDE_SERVICE';
            $budget     = (float) ($data['budget'] ?? 0);
            $categoryId = !empty($data['category_id']) ? (int) $data['category_id'] : null;

            if (!\in_array($type, ['DEMANDE_SERVICE', 'VENTE_OBJET'], true)) {
                $type = 'DEMANDE_SERVICE';
            }

            if ($idea === '') {
                return $this->json(['success' => false, 'message' => 'Veuillez décrire votre publication.'], 422);
            }

            // Call AI — n8n will generate AND save to DB
            $result = $this->ai->helpWritePublication(
                $user->getId(),
                $type,
                '',
                $idea,
                $budget,
                $categoryId
            );
            $result = \is_array($result) ? $result : [];

            // If n8n already saved (result contains 'saved' flag + 'id')
            if (!empty($result['saved']) && !empty($result['id'])) {
                return $this->json([
                    'success' => true,
                    'saved'   => true,
                    'id'      => $result['id'],
                    'titre'   => $result['titre'] ?? '',
                    'message' => $result['message'] ?? '',
                ]);
            }

            // Fallback: n8n didn't save (direct OpenRouter), save via Doctrine
            $titre   = $result['titre'] ?? $result['title'] ?? 'Publication';
            $message = $result['message'] ?? $result['description'] ?? '';

            $pub = new Publication();
            $pub->setUser($user)
                ->setTypePublication($type)
                ->setTitre(mb_substr($titre, 0, 200))
                ->setMessage($message)
                ->setStatus('ACTIVE');

            if ($type === 'VENTE_OBJET' && $budget > 0) {
                $pub->setPrixVente(number_format($budget, 2, '.', ''));
            } elseif ($budget > 0) {
                $pub->setProposedPrice(number_format($budget, 2, '.', ''));
            }

            if ($categoryId) {
                $cat = $em->find(Categorie::class, $categoryId);
                if ($cat) {
                    $pub->setCategory($cat);
                }
            }

            $em->persist($pub);
            $em->flush();

            return $this->json([
                'success' => true,
                'saved'   => true,
                'id'      => $pub->getId(),
                'titre'   => $pub->getTitre(),
                'message' => $pub->getMessage(),
            ]);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'not configured')) {
                return $this->json([
                    'success' => false,
                    'message' => 'Le service IA n\'est pas configuré. Veuillez contacter l\'administrateur.',
                ], 503);
            }

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
            if (str_contains($e->getMessage(), 'not configured')) {
                return $this->json([
                    'success' => false,
                    'message' => 'Le service IA n\'est pas configuré. Veuillez contacter l\'administrateur.',
                ], 503);
            }

            return $this->json(['response' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }
}
