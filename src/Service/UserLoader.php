<?php

namespace App\Service;

use App\Repository\UserRepository;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

/**
 * Extracted from the UserBadge callback in AppAuthenticator.
 * Contains all pure business rules — fully unit-testable.
 */
class UserLoader
{
    public function __construct(
        private UserRepository $userRepository,
    ) {}

    /**
     * Loads and validates a user for authentication.
     *
     * @throws CustomUserMessageAuthenticationException on any rule violation
     */
    public function loadUser(string $email, string $submittedRole): object
    {
        $user = $this->userRepository->findByEmail($email);

        // Rule 1 — user must exist
        if (!$user) {
            throw new CustomUserMessageAuthenticationException('Identifiants invalides.');
        }

        // Rule 2 — submitted role must match the account type
        if ($user->getUserType() !== strtoupper($submittedRole)) {
            throw new CustomUserMessageAuthenticationException(
                'Ce compte n\'est pas un compte ' . match(strtoupper($submittedRole)) {
                    'ETUDIANT'    => 'Étudiant',
                    'PRESTATAIRE' => 'Prestataire',
                    'ADMIN'       => 'Admin',
                    default       => $submittedRole,
                } . '.'
            );
        }

        // Rule 3 — account must be activated
        if ($user->getStatus() === 'INACTIVE') {
            throw new CustomUserMessageAuthenticationException(
                'Votre compte n\'est pas encore activé. Vérifiez votre email.'
            );
        }

        // Rule 4 — account must not be banned
        if ($user->getStatus() === 'BANNED') {
            throw new CustomUserMessageAuthenticationException(
                'Votre compte est banni.'
            );
        }

        return $user;
    }
}