<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;

class ResetPasswordController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
        private MailerInterface $mailer,
    ) {}

    // ── Step 1: Show email form & send code ──
   // ── Step 1: Send code ──
#[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
public function requestCode(Request $request): Response
{
    if ($request->isMethod('POST')) {
        $email = trim($request->request->get('email', ''));
        $user  = $this->userRepository->findByEmail($email);

        if ($user) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            // Store in session instead of DB
            $request->getSession()->set('reset_email', $email);
            $request->getSession()->set('reset_code', $code);
            $request->getSession()->set('reset_code_expires', time() + 900); // 15 min

            $emailMessage = (new Email())
                ->from('no-reply@campuslink.tn')
                ->to($user->getEmail())
                ->subject('Code de réinitialisation — CampusLink')
                ->html("
                    <div style='font-family:sans-serif; max-width:400px; margin:auto;'>
                        <h2>🎓 CampusLink</h2>
                        <p>Votre code de réinitialisation :</p>
                        <div style='font-size:36px; font-weight:800; letter-spacing:8px; color:#5D5FEF;'>
                            {$code}
                        </div>
                        <p style='color:#6B7280; font-size:13px;'>
                            Expire dans <strong>15 minutes</strong>.
                        </p>
                    </div>
                ");

            $this->mailer->send($emailMessage);
            
        }

        $this->addFlash('success', 'Si cet email existe, un code a été envoyé.');
        return $this->redirectToRoute('app_verify_code');
    }

    return $this->render('User/forgot_password.html.twig');
}

// ── Step 2: Verify code ──
#[Route('/verify-code', name: 'app_verify_code', methods: ['GET', 'POST'])]
public function verifyCode(Request $request): Response
{
    $session = $request->getSession();
    $email   = $session->get('reset_email');

    if (!$email) {
        return $this->redirectToRoute('app_forgot_password');
    }

    if ($request->isMethod('POST')) {
        $code           = trim($request->request->get('code', ''));
        $storedCode     = $session->get('reset_code');
        $storedExpires  = $session->get('reset_code_expires');

        // Check expiry
        if (time() > $storedExpires) {
            // Clean up session
            $session->remove('reset_code');
            $session->remove('reset_code_expires');
            $session->remove('reset_email');

            $this->addFlash('error', 'Code expiré. Recommencez.');
            return $this->redirectToRoute('app_forgot_password');
        }

        // Check code
        if ($code !== $storedCode) {
            $this->addFlash('error', 'Code invalide.');
            return $this->redirectToRoute('app_verify_code');
        }

        // ✅ Valid — mark as verified, remove code
        $session->set('reset_verified', true);
        $session->remove('reset_code');
        $session->remove('reset_code_expires');

        return $this->redirectToRoute('app_reset_password');
    }

    return $this->render('User/verify_code.html.twig');
}

// ── Step 3: New password ──
#[Route('/reset-password', name: 'app_reset_password', methods: ['GET', 'POST'])]
public function resetPassword(Request $request): Response
{
    $session  = $request->getSession();
    $email    = $session->get('reset_email');
    $verified = $session->get('reset_verified');

    if (!$email || !$verified) {
        return $this->redirectToRoute('app_forgot_password');
    }

    if ($request->isMethod('POST')) {
        $password = $request->request->get('password', '');
        $confirm  = $request->request->get('confirmPassword', '');

        if ($password !== $confirm) {
            $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
            return $this->redirectToRoute('app_reset_password');
        }

        if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $this->addFlash('error', 'Le mot de passe doit contenir 8 caractères, une majuscule et un chiffre.');
            return $this->redirectToRoute('app_reset_password');
        }

        $user = $this->userRepository->findByEmail($email);
        $user->setPassword(password_hash($password, PASSWORD_BCRYPT));
        $this->em->flush();

        // Clean up all session reset data
        $session->remove('reset_email');
        $session->remove('reset_verified');

        $this->addFlash('success', 'Mot de passe modifié avec succès !');
        return $this->redirectToRoute('app_login');
    }

    return $this->render('User/reset_password.html.twig');
}
}