<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils; 
use Symfony\Component\Mailer\MailerInterface;

class SecurityController extends AbstractController
{
    public function __construct(
        private UserController $userController,
        private MailerInterface $mailer,
        private UserRepository $userRepository,
        private EntityManagerInterface $em,   


    ) {}

    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
        return match($this->getUser()->getUserType()) {
            'ADMIN'       => $this->redirectToRoute('admin_dashboard'),
            'PRESTATAIRE' => $this->redirectToRoute('prestataire_reservations'),
            'ETUDIANT'    => $this->redirectToRoute('etudiant_reservations'),
            default       => $this->redirectToRoute('app_home'),
        };
    }
    
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
                'status'         => 'INACTIVE',
            ]);

            $jsonRequest = Request::create('/api/users', 'POST', content: $jsonData);
            $jsonRequest->headers->set('Content-Type', 'application/json');

            $response   = $this->userController->create($jsonRequest);
            $statusCode = $response->getStatusCode();
            $body       = json_decode($response->getContent(), true);

                    if ($statusCode === 201) {
            // Generate activation code and store in session 
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            $request->getSession()->set('activation_email', $request->request->get('email'));
            $request->getSession()->set('activation_code', $code);
            $request->getSession()->set('activation_code_expires', time() + 900); // 15 min

            // Send activation email
            $emailMessage = (new \Symfony\Component\Mime\Email())
                ->from('no-reply@campuslink.tn')
                ->to($request->request->get('email'))
                ->subject('Activez votre compte — CampusLink')
                ->html("
                    <div style='font-family:sans-serif; max-width:400px; margin:auto;'>
                        <h2>🎓 CampusLink</h2>
                        <p>Bienvenue ! Votre code d'activation :</p>
                        <div style='font-size:36px; font-weight:800; letter-spacing:8px; color:#5D5FEF;'>
                            {$code}
                        </div>
                        <p style='color:#6B7280; font-size:13px;'>
                            Expire dans <strong>15 minutes</strong>.
                        </p>
                    </div>
                ");

            $this->mailer->send($emailMessage);

            $this->addFlash('success', 'Compte créé ! Vérifiez votre email pour activer votre compte.');
            return $this->redirectToRoute('app_activate_account');
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
    #[Route('/activate-account', name: 'app_activate_account', methods: ['GET', 'POST'])]
    public function activateAccount(Request $request): Response
    {
        $session = $request->getSession();
        $email   = $session->get('activation_email');

        if (!$email) {
            return $this->redirectToRoute('app_register');
        }

        if ($request->isMethod('POST')) {
            $code          = trim($request->request->get('code', ''));
            $storedCode    = $session->get('activation_code');
            $storedExpires = $session->get('activation_code_expires');

            if (time() > $storedExpires) {
                $session->remove('activation_email');
                $session->remove('activation_code');
                $session->remove('activation_code_expires');

                $this->addFlash('error', 'Code expiré. Veuillez vous réinscrire.');
                return $this->redirectToRoute('app_register');
            }

            if ($code !== $storedCode) {
                $this->addFlash('error', 'Code invalide. Réessayez.');
                return $this->redirectToRoute('app_activate_account');
            }

            $user = $this->userRepository->findByEmail($email);

            if (!$user) {
                $this->addFlash('error', 'Utilisateur introuvable.');
                return $this->redirectToRoute('app_register');
            }

            $user->setStatus('ACTIVE');
            $this->em->flush();

            $session->remove('activation_email');
            $session->remove('activation_code');
            $session->remove('activation_code_expires');

            $this->addFlash('success', 'Compte activé avec succès ! Vous pouvez vous connecter.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('User/activate_account.html.twig');
    }

}