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

        return match($user->getUserType()) {
            'ADMIN'       => new RedirectResponse($this->router->generate('admin_dashboard')),
            'PRESTATAIRE' => new RedirectResponse($this->router->generate('prestataire_profile')),
            'ETUDIANT'    => new RedirectResponse($this->router->generate('etudiant_profile')),
            default       => new RedirectResponse($this->router->generate('app_login')),
        };
    }
}