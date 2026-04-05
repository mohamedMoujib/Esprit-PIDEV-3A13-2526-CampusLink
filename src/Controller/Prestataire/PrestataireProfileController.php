<?php

namespace App\Controller\Prestataire;

use App\Controller\UserController;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/prestataire')]
class PrestataireProfileController extends AbstractController
{
    public function __construct(
        private UserController $userController,
    ) {}

    #[Route('/profile', name: 'prestataire_profile', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $action = $request->request->get('action');

            if ($action === 'save_profile') {
                $prenom = trim($request->request->get('prenom', ''));
                $nom    = trim($request->request->get('nom', ''));

                $jsonData = json_encode([
                    'name'           => $prenom . ' ' . $nom,
                    'email'          => trim($request->request->get('email', '')),
                    'phone'          => $request->request->get('phone') ?: null,
                    'address'        => $request->request->get('address') ?: null,
                    'gender'         => $request->request->get('gender') ?: null,
                    'universite'     => $request->request->get('universite') ?: null,
                    'filiere'        => $request->request->get('filiere') ?: null,
                    'specialization' => $request->request->get('specialization') ?: null,
                ]);

                $jsonRequest = Request::create('/api/users/' . $user->getId(), 'PUT', content: $jsonData);
                $jsonRequest->headers->set('Content-Type', 'application/json');

                $response   = $this->userController->update($user->getId(), $jsonRequest);
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

            if ($action === 'change_password') {
                $current = $request->request->get('current_password', '');
                $new     = $request->request->get('new_password', '');
                $confirm = $request->request->get('confirm_password', '');

                if (!password_verify($current, $user->getPassword())) {
                    $this->addFlash('error', 'Mot de passe actuel incorrect.');
                } elseif ($new !== $confirm) {
                    $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
                } else {
                    $jsonData    = json_encode(['password' => $new]);
                    $jsonRequest = Request::create('/api/users/' . $user->getId(), 'PUT', content: $jsonData);
                    $jsonRequest->headers->set('Content-Type', 'application/json');

                    $response   = $this->userController->update($user->getId(), $jsonRequest);
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

            return $this->redirectToRoute('prestataire_profile');
        }

        $nameParts = explode(' ', $user->getName(), 2);

        return $this->render('Prestataire/profile.html.twig', [
            'user'      => $user,
            'prenom'    => $nameParts[0] ?? '',
            'nom'       => $nameParts[1] ?? '',
            'save_route'=> 'prestataire_profile',
        ]);
    }
}