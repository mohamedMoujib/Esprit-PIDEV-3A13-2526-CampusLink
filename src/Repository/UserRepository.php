<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    // Find by email (used for login + uniqueness check)
    public function findByEmail(string $email): ?User
    {
        return $this->createQueryBuilder('u')
            ->where('u.email = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // Find all users by type
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.userType = :type')
            ->setParameter('type', $type)
            ->orderBy('u.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // Find all active users
    public function findActiveUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.status = :status')
            ->setParameter('status', 'ACTIVE')
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // Find active users by type
    public function findActiveByType(string $type): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.userType = :type')
            ->andWhere('u.status = :status')
            ->setParameter('type', $type)
            ->setParameter('status', 'ACTIVE')
            ->orderBy('u.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // Search by name or email (partial match)
    public function search(string $query): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.name LIKE :q OR u.email LIKE :q')
            ->setParameter('q', '%' . $query . '%')
            ->orderBy('u.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // Find prestataires by specialization
    public function findBySpecialization(string $specialization): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.userType = :type')
            ->andWhere('u.specialization LIKE :spec')
            ->setParameter('type', 'PRESTATAIRE')
            ->setParameter('spec', '%' . $specialization . '%')
            ->getQuery()
            ->getResult();
    }

    // Find etudiants by universite
    public function findByUniversite(string $universite): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.userType = :type')
            ->andWhere('u.universite = :universite')
            ->setParameter('type', 'ETUDIANT')
            ->setParameter('universite', $universite)
            ->orderBy('u.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // Top prestataires by trust points
    public function findTopPrestataires(int $limit = 10): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.userType = :type')
            ->andWhere('u.status = :status')
            ->setParameter('type', 'PRESTATAIRE')
            ->setParameter('status', 'ACTIVE')
            ->orderBy('u.trustPoints', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    // Count users by type
    public function countByType(): array
    {
        $results = $this->createQueryBuilder('u')
            ->select('u.userType, COUNT(u.id) as total')
            ->groupBy('u.userType')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $row) {
            $counts[$row['userType']] = (int) $row['total'];
        }

        return $counts;
    }
}