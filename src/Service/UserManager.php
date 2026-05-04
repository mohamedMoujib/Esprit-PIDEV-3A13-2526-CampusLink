<?php

namespace App\Service;

use App\Entity\User;

class UserManager
{
    private const ALLOWED_TYPES    = ['ETUDIANT', 'PRESTATAIRE', 'ADMIN'];
    private const ALLOWED_STATUSES = ['ACTIVE', 'INACTIVE', 'BANNED'];
    private const ALLOWED_GENDERS  = ['male', 'female', 'other'];

    /**
     * Validates user data array.
     * $isUpdate = false → required fields are enforced (create)
     * $isUpdate = true  → only present fields are validated (update)
     *
     * @throws \InvalidArgumentException on the first validation failure
     */
    public function validate(array $data, bool $isUpdate = false): bool
    {
        // ── Required fields (create only) ──────────────────────────────
        if (!$isUpdate) {
            foreach (['name', 'email', 'password', 'userType'] as $field) {
                if (empty($data[$field])) {
                    throw new \InvalidArgumentException("Field '$field' is required.");
                }
            }
        }

        // ── name ───────────────────────────────────────────────────────
        if (isset($data['name'])) {
            if (strlen(trim($data['name'])) < 2) {
                throw new \InvalidArgumentException('Name must be at least 2 characters.');
            }
            if (strlen($data['name']) > 100) {
                throw new \InvalidArgumentException('Name must not exceed 100 characters.');
            }
        }

        // ── email ──────────────────────────────────────────────────────
        if (isset($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Invalid email address.');
            }
        }

        // ── password ───────────────────────────────────────────────────
        if (isset($data['password'])) {
            if (strlen($data['password']) < 8) {
                throw new \InvalidArgumentException('Password must be at least 8 characters.');
            }
            if (!preg_match('/[A-Z]/', $data['password'])) {
                throw new \InvalidArgumentException('Password must contain at least one uppercase letter.');
            }
            if (!preg_match('/[0-9]/', $data['password'])) {
                throw new \InvalidArgumentException('Password must contain at least one number.');
            }
        }

        // ── userType ───────────────────────────────────────────────────
        if (isset($data['userType'])) {
            if (!in_array($data['userType'], self::ALLOWED_TYPES, true)) {
                throw new \InvalidArgumentException(
                    'Invalid userType. Allowed: ' . implode(', ', self::ALLOWED_TYPES)
                );
            }
        }

        // ── status ─────────────────────────────────────────────────────
        if (isset($data['status'])) {
            if (!in_array($data['status'], self::ALLOWED_STATUSES, true)) {
                throw new \InvalidArgumentException(
                    'Invalid status. Allowed: ' . implode(', ', self::ALLOWED_STATUSES)
                );
            }
        }

        // ── gender ─────────────────────────────────────────────────────
        if (isset($data['gender'])) {
            if (!in_array($data['gender'], self::ALLOWED_GENDERS, true)) {
                throw new \InvalidArgumentException(
                    'Invalid gender. Allowed: ' . implode(', ', self::ALLOWED_GENDERS)
                );
            }
        }

        // ── phone ──────────────────────────────────────────────────────
        if (isset($data['phone'])) {
            if (!preg_match('/^\+?[0-9\s\-]{7,20}$/', $data['phone'])) {
                throw new \InvalidArgumentException('Invalid phone number format.');
            }
        }

        // ── dateNaissance ──────────────────────────────────────────────
        if (isset($data['dateNaissance'])) {
            $date = \DateTime::createFromFormat('Y-m-d', $data['dateNaissance']);
            if (!$date) {
                throw new \InvalidArgumentException('Invalid date format. Use Y-m-d (e.g. 1999-05-21).');
            }
            if ($date > new \DateTime()) {
                throw new \InvalidArgumentException('Date of birth cannot be in the future.');
            }
            if ($date < new \DateTime('-120 years')) {
                throw new \InvalidArgumentException('Date of birth is not realistic.');
            }
        }

        // ── trustPoints ────────────────────────────────────────────────
        if (isset($data['trustPoints'])) {
            if (!is_int($data['trustPoints']) || $data['trustPoints'] < 0) {
                throw new \InvalidArgumentException('Trust points must be a non-negative integer.');
            }
        }

        // ── Role-specific rule ─────────────────────────────────────────
        if (isset($data['userType']) && $data['userType'] === 'ADMIN' && isset($data['trustPoints'])) {
            throw new \InvalidArgumentException('Admins cannot have trust points.');
        }

        return true;
    }
}