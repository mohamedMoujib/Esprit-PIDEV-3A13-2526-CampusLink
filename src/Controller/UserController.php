<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/users')]
class UserController extends AbstractController
{
    // Valid values — single source of truth
    private const ALLOWED_TYPES    = ['ETUDIANT', 'PRESTATAIRE', 'ADMIN'];
    private const ALLOWED_STATUSES = ['ACTIVE', 'INACTIVE', 'BANNED'];
    private const ALLOWED_GENDERS  = ['male', 'female', 'other'];

    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
    ) {}

    // LIST ALL
    // GET /api/users
    #[Route('', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $users = $this->userRepository->findAll();
        return $this->json(array_map(fn(User $u) => $this->serialize($u), $users));
    }

    // GET ONE
    // GET /api/users/{id}
    #[Route('/{id}', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }
        return $this->json($this->serialize($user));
    }

    // CREATE
    // POST /api/users
    // ─────────────────────────────────────────
    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // 1. JSON must be valid
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        // 2. Validate all fields
        $errors = $this->validateUserData($data, isUpdate: false);
        if (!empty($errors)) {
            return $this->json(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // 3. Email uniqueness
        if ($this->userRepository->findByEmail($data['email'])) {
            return $this->json(['error' => 'Email already in use'], Response::HTTP_CONFLICT);
        }

        // 4. Build entity
        $user = new User();
        $this->hydrate($user, $data);

        $this->em->persist($user);
        $this->em->flush();

        return $this->json($this->serialize($user), Response::HTTP_CREATED);
    }

    // UPDATE
    // PUT /api/users/{id}
    #[Route('/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        // Validate only fields that are present (partial update)
        $errors = $this->validateUserData($data, isUpdate: true);
        if (!empty($errors)) {
            return $this->json(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Email uniqueness — only if email is being changed
        if (!empty($data['email']) && $data['email'] !== $user->getEmail()) {
            if ($this->userRepository->findByEmail($data['email'])) {
                return $this->json(['error' => 'Email already in use'], Response::HTTP_CONFLICT);
            }
        }

        $this->hydrate($user, $data);
        $this->em->flush();

        return $this->json($this->serialize($user));
    }

    // DELETE
    // DELETE /api/users/{id}
    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($user);
        $this->em->flush();

        return $this->json(['message' => 'User deleted successfully']);
    }

    // FILTER BY TYPE
    // GET /api/users/type/{type}
    #[Route('/type/{type}', methods: ['GET'])]
    public function filterByType(string $type): JsonResponse
    {
        $type = strtoupper($type);

        if (!in_array($type, self::ALLOWED_TYPES)) {
            return $this->json([
                'error' => 'Invalid type. Allowed: ' . implode(', ', self::ALLOWED_TYPES)
            ], Response::HTTP_BAD_REQUEST);
        }

        $users = $this->userRepository->findByType($type);
        return $this->json(array_map(fn(User $u) => $this->serialize($u), $users));
    }

    // SEARCH
    // GET /api/users/search?q=...
    #[Route('/search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $q = trim($request->query->get('q', ''));

        if (strlen($q) < 2) {
            return $this->json(['error' => 'Search query must be at least 2 characters'], Response::HTTP_BAD_REQUEST);
        }

        $users = $this->userRepository->search($q);
        return $this->json(array_map(fn(User $u) => $this->serialize($u), $users));
    }

    // PRIVATE HELPERS

    /**
     * Validates incoming data.
     * $isUpdate = true  → all fields optional, only validate what's present
     * $isUpdate = false → required fields enforced
     */
    public function validateUserData(array $data, bool $isUpdate): array
    {
        $errors = [];

        // ── Required fields (create only) ──
        if (!$isUpdate) {
            foreach (['name', 'email', 'password', 'userType'] as $field) {
                if (empty($data[$field])) {
                    $errors[$field] = "Field '$field' is required.";
                }
            }
        }

        // ── name ──
        if (isset($data['name'])) {
            if (strlen(trim($data['name'])) < 2) {
                $errors['name'] = 'Name must be at least 2 characters.';
            } elseif (strlen($data['name']) > 100) {
                $errors['name'] = 'Name must not exceed 100 characters.';
            }
        }

        // ── email ──
        if (isset($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Invalid email address.';
            }
        }

        // ── password ──
        if (isset($data['password'])) {
            if (strlen($data['password']) < 8) {
                $errors['password'] = 'Password must be at least 8 characters.';
            } elseif (!preg_match('/[A-Z]/', $data['password'])) {
                $errors['password'] = 'Password must contain at least one uppercase letter.';
            } elseif (!preg_match('/[0-9]/', $data['password'])) {
                $errors['password'] = 'Password must contain at least one number.';
            }
        }

        // ── userType ──
        if (isset($data['userType'])) {
            if (!in_array($data['userType'], self::ALLOWED_TYPES)) {
                $errors['userType'] = 'Invalid userType. Allowed: ' . implode(', ', self::ALLOWED_TYPES);
            }
        }

        // ── status ──
        if (isset($data['status'])) {
            if (!in_array($data['status'], self::ALLOWED_STATUSES)) {
                $errors['status'] = 'Invalid status. Allowed: ' . implode(', ', self::ALLOWED_STATUSES);
            }
        }

        // ── gender ──
        if (isset($data['gender'])) {
            if (!in_array($data['gender'], self::ALLOWED_GENDERS)) {
                $errors['gender'] = 'Invalid gender. Allowed: ' . implode(', ', self::ALLOWED_GENDERS);
            }
        }

        // ── phone ──
        if (isset($data['phone'])) {
            // Accepts +216 12 345 678 or 0021612345678 etc.
            if (!preg_match('/^\+?[0-9\s\-]{7,20}$/', $data['phone'])) {
                $errors['phone'] = 'Invalid phone number format.';
            }
        }

        // ── dateNaissance ──
        if (isset($data['dateNaissance'])) {
            $date = \DateTime::createFromFormat('Y-m-d', $data['dateNaissance']);
            if (!$date) {
                $errors['dateNaissance'] = 'Invalid date format. Use Y-m-d (e.g. 1999-05-21).';
            } elseif ($date > new \DateTime()) {
                $errors['dateNaissance'] = 'Date of birth cannot be in the future.';
            } elseif ($date < new \DateTime('-120 years')) {
                $errors['dateNaissance'] = 'Date of birth is not realistic.';
            }
        }

        // ── trustPoints ──
        if (isset($data['trustPoints'])) {
            if (!is_int($data['trustPoints']) || $data['trustPoints'] < 0) {
                $errors['trustPoints'] = 'Trust points must be a non-negative integer.';
            }
        }

        // ── Role-specific warnings ──
        if (isset($data['userType'])) {
            if ($data['userType'] === 'ADMIN' && isset($data['trustPoints'])) {
                $errors['trustPoints'] = 'Admins cannot have trust points.';
            }
        }

        return $errors;
    }

    /**
     * Maps request data onto the User entity.
     * Safe to call on both create and update.
     */
    public function hydrate(User $user, array $data): void
    {
        isset($data['name'])           && $user->setName(trim($data['name']));
        isset($data['email'])          && $user->setEmail(strtolower(trim($data['email'])));
        isset($data['userType'])       && $user->setUserType($data['userType']);
        isset($data['phone'])          && $user->setPhone($data['phone']);
        isset($data['address'])        && $user->setAddress(trim($data['address']));
        isset($data['gender'])         && $user->setGender($data['gender']);
        isset($data['status'])         && $user->setStatus($data['status']);
        isset($data['profilePicture']) && $user->setProfilePicture($data['profilePicture']);
        isset($data['universite'])     && $user->setUniversite($data['universite']);
        isset($data['filiere'])        && $user->setFiliere($data['filiere']);
        isset($data['specialization']) && $user->setSpecialization($data['specialization']);
        isset($data['trustPoints'])    && $user->setTrustPoints($data['trustPoints']);

        if (!empty($data['password'])) {
            $user->setPassword(password_hash($data['password'], PASSWORD_BCRYPT));
        }

        if (!empty($data['dateNaissance'])) {
            $user->setDateNaissance(\DateTime::createFromFormat('Y-m-d', $data['dateNaissance']));
        }
    }

    /**
     * Safe response shape — password is never exposed.
     */
    private function serialize(User $u): array
    {
        return [
            'id'             => $u->getId(),
            'userType'       => $u->getUserType(),
            'name'           => $u->getName(),
            'email'          => $u->getEmail(),
            'phone'          => $u->getPhone(),
            'address'        => $u->getAddress(),
            'gender'         => $u->getGender(),
            'status'         => $u->getStatus(),
            'profilePicture' => $u->getProfilePicture(),
            'dateNaissance'  => $u->getDateNaissance()?->format('Y-m-d'),
            'universite'     => $u->getUniversite(),
            'filiere'        => $u->getFiliere(),
            'specialization' => $u->getSpecialization(),
            'trustPoints'    => $u->getTrustPoints(),
            'createdAt'      => $u->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt'      => $u->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}