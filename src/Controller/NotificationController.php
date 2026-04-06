<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/notifications')]
#[IsGranted('ROLE_USER')]
class NotificationController extends AbstractController
{
    public function __construct(private readonly NotificationService $notif) {}

    #[Route('', name: 'notifications_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'notifications' => $this->notif->getForUser($user),
            'unreadCount' => $this->notif->getUnreadCount($user),
        ]);
    }

    #[Route('/count', name: 'notifications_count', methods: ['GET'])]
    public function count(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json(['count' => $this->notif->getUnreadCount($user)]);
    }

    #[Route('/{id}/read', name: 'notification_read', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function read(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->notif->markAsRead($user, $id)) {
            return $this->json(['success' => false, 'message' => 'Notification introuvable.'], 404);
        }

        return $this->json(['success' => true]);
    }

    #[Route('/read-all', name: 'notifications_read_all', methods: ['POST'])]
    public function readAll(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->notif->clearForUser($user);

        return $this->json(['success' => true]);
    }
}
