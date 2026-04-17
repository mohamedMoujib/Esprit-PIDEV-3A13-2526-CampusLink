<?php

namespace App\Controller\Admin;

use App\Controller\UserController;
use App\Repository\UserRepository;
use App\Service\CloudinaryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin')]
class AdminProfileController extends AbstractController
{
    public function __construct(
        private UserController $userController,
        private CloudinaryService $cloudinary,
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
    ) {}

    #[Route('/profile', name: 'admin_profile', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        /** @var \App\Entity\User $sessionUser */
        $sessionUser = $this->getUser();
        $admin = $this->userRepository->find($sessionUser->getId());

        if ($request->isMethod('POST')) {
            $action = $request->request->get('action');

            // ── Upload photo ──
            if ($action === 'upload_photo') {
                $file = $request->files->get('profile_picture');

                if (!$file) {
                    $this->addFlash('error', 'Aucun fichier sélectionné.');
                    return $this->redirectToRoute('admin_profile');
                }

                if ($file->getSize() > 5 * 1024 * 1024) {
                    $this->addFlash('error', 'Image trop volumineuse (max 5MB).');
                    return $this->redirectToRoute('admin_profile');
                }

                $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($file->getMimeType(), $allowedMimes)) {
                    $this->addFlash('error', 'Format invalide. Utilisez JPG, PNG, GIF ou WEBP.');
                    return $this->redirectToRoute('admin_profile');
                }

                // Delete old Cloudinary image if exists
                $oldPicture = $admin->getProfilePicture();
                if ($oldPicture && str_contains($oldPicture, 'res.cloudinary.com')) {
                    $this->cloudinary->deleteByUrl($oldPicture);
                }

                // Upload to Cloudinary and persist
                $newUrl = $this->cloudinary->upload($file, 'profiles');
                $admin->setProfilePicture($newUrl);
                $this->em->flush();

                $this->addFlash('success', 'Photo de profil mise à jour !');
                return $this->redirectToRoute('admin_profile');
            }

            // ── Remove photo ──
            if ($action === 'remove_photo') {
                $oldPicture = $admin->getProfilePicture();
                if ($oldPicture && str_contains($oldPicture, 'res.cloudinary.com')) {
                    $this->cloudinary->deleteByUrl($oldPicture);
                }

                $admin->setProfilePicture(null);
                $this->em->flush();

                $this->addFlash('success', 'Photo supprimée.');
                return $this->redirectToRoute('admin_profile');
            }

            // ── Save profile info ──
            if ($action === 'save_profile') {
                $payload = [
                    'name'    => trim($request->request->get('name', '')),
                    'email'   => trim($request->request->get('email', '')),
                    'phone'   => $request->request->get('phone') ?: null,
                    'gender'  => $request->request->get('gender') ?: null,
                    'address' => $request->request->get('address') ?: null,
                ];

                // Validate
                $errors = $this->userController->validateUserData($payload, isUpdate: true);
                if (!empty($errors)) {
                    foreach ($errors as $field => $message) {
                        $this->addFlash('error', "$field : $message");
                    }
                    return $this->redirectToRoute('admin_profile');
                }

                // Email uniqueness check
                if (!empty($payload['email']) && $payload['email'] !== $admin->getEmail()) {
                    if ($this->userRepository->findByEmail($payload['email'])) {
                        $this->addFlash('error', 'Cet email est déjà utilisé.');
                        return $this->redirectToRoute('admin_profile');
                    }
                }

                // Hydrate & persist
                $this->userController->hydrate($admin, $payload);
                $this->em->flush();

                $this->addFlash('success', 'Profil mis à jour avec succès !');
                return $this->redirectToRoute('admin_profile');
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
                    $errors = $this->userController->validateUserData(['password' => $new], isUpdate: true);
                    if (!empty($errors)) {
                        foreach ($errors as $message) {
                            $this->addFlash('error', $message);
                        }
                    } else {
                        $this->userController->hydrate($admin, ['password' => $new]);
                        $this->em->flush();
                        $this->addFlash('success', 'Mot de passe changé avec succès !');
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