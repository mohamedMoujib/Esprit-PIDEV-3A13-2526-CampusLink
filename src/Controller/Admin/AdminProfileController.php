<?php

namespace App\Controller\Admin;

use App\Controller\UserController;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin')]
class AdminProfileController extends AbstractController
{
    public function __construct(
        private UserController $userController,
    ) {}

    #[Route('/profile', name: 'admin_profile', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        /** @var \App\Entity\User $admin */
        $admin = $this->getUser();

        if ($request->isMethod('POST')) {
            $action = $request->request->get('action');

            // ── Upload photo ──
            if ($action === 'upload_photo') {
                $file = $request->files->get('profile_picture');

                if (!$file) {
                    $this->addFlash('error', 'Aucun fichier sélectionné.');
                    return $this->redirectToRoute('admin_profile');
                }

                // Validate size (max 5MB)
                if ($file->getSize() > 5 * 1024 * 1024) {
                    $this->addFlash('error', 'Image trop volumineuse (max 5MB).');
                    return $this->redirectToRoute('admin_profile');
                }

                // Validate mime type
                $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($file->getMimeType(), $allowedMimes)) {
                    $this->addFlash('error', 'Format invalide. Utilisez JPG, PNG, GIF ou WEBP.');
                    return $this->redirectToRoute('admin_profile');
                }

                // Delete old picture
                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/profiles/';
                if ($admin->getProfilePicture()) {
                    $oldFile = $uploadDir . $admin->getProfilePicture();
                    if (file_exists($oldFile)) {
                        unlink($oldFile);
                    }
                }

                // Save new picture
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $newName = 'profile_' . $admin->getId() . '_' . time() . '.' . $file->guessExtension();
                $file->move($uploadDir, $newName);

                // Update via UserController
                $jsonData    = json_encode(['profilePicture' => $newName]);
                $jsonRequest = Request::create('/api/users/' . $admin->getId(), 'PUT', content: $jsonData);
                $jsonRequest->headers->set('Content-Type', 'application/json');
                $this->userController->update($admin->getId(), $jsonRequest);

                $this->addFlash('success', 'Photo de profil mise à jour !');
                return $this->redirectToRoute('admin_profile');
            }
// ── Remove photo ──
if ($action === 'remove_photo') {
    if ($admin->getProfilePicture()) {
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/profiles/';
        $oldFile   = $uploadDir . $admin->getProfilePicture();
        if (file_exists($oldFile)) {
            unlink($oldFile);
        }
    }

    $jsonData    = json_encode(['profilePicture' => null]);
    $jsonRequest = Request::create('/api/users/' . $admin->getId(), 'PUT', content: $jsonData);
    $jsonRequest->headers->set('Content-Type', 'application/json');
    $this->userController->update($admin->getId(), $jsonRequest);

    $this->addFlash('success', 'Photo supprimée.');
    return $this->redirectToRoute('admin_profile');
}
            // ── Save profile info ──
            if ($action === 'save_profile') {
                $jsonData = json_encode([
                    'name'    => trim($request->request->get('name', '')),
                    'email'   => trim($request->request->get('email', '')),
                    'phone'   => $request->request->get('phone') ?: null,
                    'gender'  => $request->request->get('gender') ?: null,
                    'address' => $request->request->get('address') ?: null,
                ]);

                $jsonRequest = Request::create('/api/users/' . $admin->getId(), 'PUT', content: $jsonData);
                $jsonRequest->headers->set('Content-Type', 'application/json');

                $response   = $this->userController->update($admin->getId(), $jsonRequest);
                $statusCode = $response->getStatusCode();
                $body       = json_decode($response->getContent(), true);

                if ($statusCode === 200) {
                    $this->addFlash('success', 'Profil mis à jour avec succès !');
                } elseif ($statusCode === 409) {
                    $this->addFlash('error', 'Cet email est déjà utilisé.');
                } elseif ($statusCode === 422 && isset($body['errors'])) {
                    foreach ($body['errors'] as $field => $message) {
                        $this->addFlash('error', "$field : $message");
                    }
                } else {
                    $this->addFlash('error', 'Une erreur est survenue.');
                }
            }

            // ── Change password ──
            if ($action === 'change_password') {
                $current = $request->request->get('current_password', '');
                $new     = $request->request->get('new_password', '');
                $confirm = $request->request->get('confirm_password', '');

                if (!password_verify($current, $admin->getPassword())) {
                    $this->addFlash('error', 'Mot de passe actuel incorrect.');
                } elseif ($new !== $confirm) {
                    $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
                } else {
                    $jsonData    = json_encode(['password' => $new]);
                    $jsonRequest = Request::create('/api/users/' . $admin->getId(), 'PUT', content: $jsonData);
                    $jsonRequest->headers->set('Content-Type', 'application/json');

                    $response   = $this->userController->update($admin->getId(), $jsonRequest);
                    $statusCode = $response->getStatusCode();
                    $body       = json_decode($response->getContent(), true);

                    if ($statusCode === 200) {
                        $this->addFlash('success', 'Mot de passe changé avec succès !');
                    } elseif ($statusCode === 422 && isset($body['errors'])) {
                        foreach ($body['errors'] as $field => $message) {
                            $this->addFlash('error', "$field : $message");
                        }
                    } else {
                        $this->addFlash('error', 'Une erreur est survenue.');
                    }
                }
            }

            return $this->redirectToRoute('admin_profile');
        }

        return $this->render('admin/pages/profile.html.twig', [
            'admin' => $admin,
        ]);
    }
}