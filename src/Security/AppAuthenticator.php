<?php

namespace App\Security;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

class AppAuthenticator extends AbstractLoginFormAuthenticator
{
    public function __construct(
        private UserRepository $userRepository,
        private RouterInterface $router,
        private LoginSuccessHandler $successHandler,
    ) {}

    protected function getLoginUrl(Request $request): string
    {
        return $this->router->generate('app_login');
    }

    public function authenticate(Request $request): Passport
    {
        $email            = $request->request->get('_username', '');
        $password         = $request->request->get('_password', '');
        $submittedRole    = strtoupper($request->request->get('userType', ''));

        return new Passport(
            new UserBadge($email, function (string $email) use ($submittedRole) {
                $user = $this->userRepository->findByEmail($email);

                if (!$user) {
                    throw new CustomUserMessageAuthenticationException('Identifiants invalides.');
                }

                // ✅ THE KEY CHECK — submitted role must match DB role
                if ($user->getUserType() !== $submittedRole) {
                    throw new CustomUserMessageAuthenticationException(
                        'Ce compte n\'est pas un compte ' . match($submittedRole) {
                            'ETUDIANT'    => 'Étudiant',
                            'PRESTATAIRE' => 'Prestataire',
                            'ADMIN'       => 'Admin',
                            default       => $submittedRole,
                        } . '.'
                    );
                }

                return $user;
            }),
            new PasswordCredentials($password),
            [new CsrfTokenBadge('authenticate', $request->request->get('_csrf_token'))]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        return $this->successHandler->onAuthenticationSuccess($request, $token);
    }
}