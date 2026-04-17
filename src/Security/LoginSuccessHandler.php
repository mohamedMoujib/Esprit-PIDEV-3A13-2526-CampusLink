<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(private RouterInterface $router) {}

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): RedirectResponse
{
    $user = $token->getUser();

    if ($user->getUserType() === 'ADMIN') {
        // Clear stale 2FA state and send to setup
        $request->getSession()->remove('2fa_secret');
        $request->getSession()->remove('2fa_verified');
        return new RedirectResponse($this->router->generate('app_2fa_setup'));
    }

    return match ($user->getUserType()) {
        'PRESTATAIRE' => new RedirectResponse($this->router->generate('prestataire_reservations')),
        'ETUDIANT'    => new RedirectResponse($this->router->generate('etudiant_reservations')),
        default       => new RedirectResponse($this->router->generate('app_login')),
    };
}
}