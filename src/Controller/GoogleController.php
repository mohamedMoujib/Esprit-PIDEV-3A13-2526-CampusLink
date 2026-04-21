<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

class GoogleController extends AbstractController
{
   #[Route('/connect/google', name: 'app_google_connect')]
    public function connect(
        ClientRegistry $clientRegistry,
        Request $request
    ): RedirectResponse {
        // Save the selected role in session so the authenticator can read it
        $userType = strtoupper($request->query->get('userType', 'ETUDIANT'));
        $request->getSession()->set('google_requested_role', $userType);

        return $clientRegistry->getClient('google')->redirect(['email', 'profile']);
    }

    #[Route('/connect/google/callback', name: 'app_google_callback')]
    public function callback(): void
    {
        // Handled entirely by GoogleAuthenticator — this method stays empty
    }
}