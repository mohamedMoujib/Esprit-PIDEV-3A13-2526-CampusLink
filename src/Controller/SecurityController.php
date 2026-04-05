<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils; // ← add this

class SecurityController extends AbstractController
{
    public function __construct(
        private UserController $userController,
    ) {}

    // ✅ Inject AuthenticationUtils here
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $error        = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('User/login.html.twig', [
            'error'         => $error,
            'last_username' => $lastUsername,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void {}

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request): Response
    {
        if ($request->isMethod('POST')) {

            if (!$this->isCsrfTokenValid('register', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('app_register');
            }

            $password        = $request->request->get('password', '');
            $confirmPassword = $request->request->get('confirmPassword', '');

            if ($password !== $confirmPassword) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
                return $this->redirectToRoute('app_register');
            }

            $prenom = trim($request->request->get('prenom', ''));
            $nom    = trim($request->request->get('nom', ''));

            $jsonData = json_encode([
                'name'           => $prenom . ' ' . $nom,
                'email'          => $request->request->get('email'),
                'password'       => $password,
                'userType'       => $request->request->get('userType', 'ETUDIANT'),
                'phone'          => $request->request->get('phone') ?: null,
                'address'        => $request->request->get('address') ?: null,
                'gender'         => $request->request->get('gender') ?: null,
                'universite'     => $request->request->get('universite') ?: null,
                'filiere'        => $request->request->get('filiere') ?: null,
                'specialization' => $request->request->get('specialization') ?: null,
                'status'         => 'ACTIVE',
            ]);

            $jsonRequest = Request::create('/api/users', 'POST', content: $jsonData);
            $jsonRequest->headers->set('Content-Type', 'application/json');

            $response   = $this->userController->create($jsonRequest);
            $statusCode = $response->getStatusCode();
            $body       = json_decode($response->getContent(), true);

            if ($statusCode === 201) {
                $this->addFlash('success', 'Compte créé avec succès ! Connectez-vous.');
                return $this->redirectToRoute('app_login');
            }

            if ($statusCode === 409) {
                $this->addFlash('error', 'Cet email est déjà utilisé.');
                return $this->redirectToRoute('app_register');
            }

            if ($statusCode === 422 && isset($body['errors'])) {
                foreach ($body['errors'] as $field => $message) {
                    $this->addFlash('error', "$field : $message");
                }
                return $this->redirectToRoute('app_register');
            }

            $this->addFlash('error', 'Une erreur est survenue. Réessayez.');
            return $this->redirectToRoute('app_register');
        }

        return $this->render('User/register.html.twig');
    }
}