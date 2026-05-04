<?php

namespace App\Service;

class ActivationManager
{
    /**
     * Rule 1 — Passwords must match.
     *
     * @throws \InvalidArgumentException if they don't match
     */
    public function checkPasswordsMatch(string $password, string $confirm): bool
    {
        if ($password !== $confirm) {
            throw new \InvalidArgumentException('Les mots de passe ne correspondent pas.');
        }
        return true;
    }

    /**
     * Rule 2 — Activation code must not be expired.
     *
     * @param int $expiresAt  Unix timestamp when the code expires
     * @param int $now        Current Unix timestamp (injectable for testing)
     * @throws \RuntimeException if the code has expired
     */
    public function checkCodeNotExpired(int $expiresAt, int $now): bool
    {
        if ($now > $expiresAt) {
            throw new \RuntimeException('Code expiré. Veuillez vous réinscrire.');
        }
        return true;
    }

    /**
     * Rule 3 — Submitted code must match the stored code.
     *
     * @throws \InvalidArgumentException if codes don't match
     */
    public function checkCodeMatches(string $submittedCode, string $storedCode): bool
    {
        if ($submittedCode !== $storedCode) {
            throw new \InvalidArgumentException('Code invalide. Réessayez.');
        }
        return true;
    }
}