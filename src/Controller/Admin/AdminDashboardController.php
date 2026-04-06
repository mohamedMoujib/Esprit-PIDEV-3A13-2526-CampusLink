<?php

namespace App\Controller\Admin;

use App\Controller\UserController;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin')]
class AdminDashboardController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private UserController $userController,
    ) {}

    #[Route('/dashboard', name: 'admin_dashboard')]
    public function index(Request $request): Response
    {
        // ── Filter by type ──
        $filter = strtoupper($request->query->get('filter', 'ALL'));
        $search = trim($request->query->get('q', ''));

        // ── Load users ──
        $users = match($filter) {
            'ETUDIANT'    => $this->userRepository->findByType('ETUDIANT'),
            'PRESTATAIRE' => $this->userRepository->findByType('PRESTATAIRE'),
            default       => array_merge(
                $this->userRepository->findByType('ETUDIANT'),
                $this->userRepository->findByType('PRESTATAIRE'),
            ),
        };

        // ── Search filter ──
        if ($search !== '') {
            $users = array_filter($users, fn($u) =>
                str_contains(strtolower($u->getName()), strtolower($search)) ||
                str_contains(strtolower($u->getEmail()), strtolower($search))
            );
        }

        // ── Statistics ──
        $allUsers      = array_merge(
            $this->userRepository->findByType('ETUDIANT'),
            $this->userRepository->findByType('PRESTATAIRE'),
        );
        $stats = [
            'total'       => count($allUsers),
            'etudiants'   => count($this->userRepository->findByType('ETUDIANT')),
            'prestataires'=> count($this->userRepository->findByType('PRESTATAIRE')),
            'active'      => count($this->userRepository->findActiveByType('ETUDIANT')) +
                             count($this->userRepository->findActiveByType('PRESTATAIRE')),
            'inactive'    => count(array_filter($allUsers, fn($u) => $u->getStatus() === 'INACTIVE')),
        ];

        return $this->render('admin/pages/users.html.twig', [
            'users'        => array_values($users),
            'stats'        => $stats,
            'filter'       => $filter,
            'search'       => $search,
        ]);
    }

    // ── Activate ──
    #[Route('/user/{id}/activate', name: 'admin_user_activate', methods: ['POST'])]
    public function activate(int $id): Response
    {
        $jsonRequest = Request::create('/api/users/' . $id, 'PUT',
            content: json_encode(['status' => 'ACTIVE'])
        );
        $jsonRequest->headers->set('Content-Type', 'application/json');
        $this->userController->update($id, $jsonRequest);
        $this->addFlash('success', 'Utilisateur activé avec succès !');
        return $this->redirectToRoute('admin_dashboard');
    }

    // ── Deactivate ──
    #[Route('/user/{id}/deactivate', name: 'admin_user_deactivate', methods: ['POST'])]
    public function deactivate(int $id): Response
    {
        $jsonRequest = Request::create('/api/users/' . $id, 'PUT',
            content: json_encode(['status' => 'INACTIVE'])
        );
        $jsonRequest->headers->set('Content-Type', 'application/json');
        $this->userController->update($id, $jsonRequest);
        $this->addFlash('success', 'Utilisateur désactivé !');
        return $this->redirectToRoute('admin_dashboard');
    }

    // ── Ban ──
    #[Route('/user/{id}/ban', name: 'admin_user_ban', methods: ['POST'])]
    public function ban(int $id): Response
    {
        $jsonRequest = Request::create('/api/users/' . $id, 'PUT',
            content: json_encode(['status' => 'BANNED'])
        );
        $jsonRequest->headers->set('Content-Type', 'application/json');
        $this->userController->update($id, $jsonRequest);
        $this->addFlash('success', 'Utilisateur banni !');
        return $this->redirectToRoute('admin_dashboard');
    }
}