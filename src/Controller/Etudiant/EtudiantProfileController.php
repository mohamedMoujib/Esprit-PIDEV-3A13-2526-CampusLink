<?php

namespace App\Controller\Etudiant;

use App\Controller\UserController;
use App\Repository\UserRepository;
use App\Service\CloudinaryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/etudiant')]
class EtudiantProfileController extends AbstractController
{
    public function __construct(
        private UserController $userController,
        private CloudinaryService $cloudinary,
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
    ) {}

    #[Route('/profile', name: 'etudiant_profile', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        /** @var \App\Entity\User $sessionUser */
            $sessionUser = $this->getUser();
            $user = $this->userRepository->find($sessionUser->getId());

        if ($request->isMethod('POST')) {
            $action = $request->request->get('action');

            // ── Save profile ──
            if ($action === 'save_profile') {
                $prenom = trim($request->request->get('prenom', ''));
                $nom    = trim($request->request->get('nom', ''));



                // ── Handle profile picture upload ──
                $profilePicture = $request->files->get('profilePicture');

                if ($profilePicture) {
                    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

                    if (!in_array($profilePicture->getMimeType(), $allowed)) {
                        $this->addFlash('error', 'Format non supporté. Utilisez JPG, PNG ou WebP.');
                        return $this->redirectToRoute('etudiant_profile');
                    }

                    if ($profilePicture->getSize() > 3 * 1024 * 1024) {
                        $this->addFlash('error', 'Image trop volumineuse (max 3MB).');
                        return $this->redirectToRoute('etudiant_profile');
                    }

                    $oldPicture = $user->getProfilePicture();
                    if ($oldPicture && str_contains($oldPicture, 'res.cloudinary.com')) {
                        $this->cloudinary->deleteByUrl($oldPicture);
                    }

                    $user->setProfilePicture($this->cloudinary->upload($profilePicture, 'profiles'));
                }

                // ── Build payload ──
                $payload = [
                    'name'           => $prenom . ' ' . $nom,
                    'email'          => trim($request->request->get('email', '')),
                    'phone'          => $request->request->get('phone') ?: null,
                    'address'        => $request->request->get('address') ?: null,
                    'gender'         => $request->request->get('gender') ?: null,
                    'universite'     => $request->request->get('universite') ?: null,
                    'filiere'        => $request->request->get('filiere') ?: null,
                    'specialization' => $request->request->get('specialization') ?: null,
                ];

                // ── Validate ──
                $errors = $this->userController->validateUserData($payload, isUpdate: true);

                if (!empty($errors)) {
                    foreach ($errors as $field => $message) {
                        $this->addFlash('error', "$field : $message");
                    }
                    return $this->redirectToRoute('etudiant_profile');
                }

                // ── Email uniqueness ──
                if (!empty($payload['email']) && $payload['email'] !== $user->getEmail()) {
                    if ($this->userRepository->findByEmail($payload['email'])) {
                        $this->addFlash('error', 'Cet email est déjà utilisé.');
                        return $this->redirectToRoute('etudiant_profile');
                    }
                }

                // ── Hydrate & save ──
                $this->userController->hydrate($user, $payload);

                $this->em->flush();

                $this->addFlash('success', 'Profil mis à jour avec succès !');
            }

            // ── Change password ──
            if ($action === 'change_password') {
                $current = $request->request->get('current_password', '');
                $new     = $request->request->get('new_password', '');
                $confirm = $request->request->get('confirm_password', '');

                if (!password_verify($current, $user->getPassword())) {
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
                        $this->userController->hydrate($user, ['password' => $new]);
                        $this->em->flush();
                        $this->addFlash('success', 'Mot de passe changé avec succès !');
                    }
                }
            }

            return $this->redirectToRoute('etudiant_profile');
        }

        $nameParts = explode(' ', $user->getName(), 2);

        return $this->render('Etudiant/profile.html.twig', [
            'user'       => $user,
            'prenom'     => $nameParts[0] ?? '',
            'nom'        => $nameParts[1] ?? '',
            'save_route' => 'etudiant_profile',
        ]);
    }
}