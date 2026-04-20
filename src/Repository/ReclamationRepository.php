<?php

namespace App\Repository;

use App\Entity\Reclamation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ReclamationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reclamation::class);
    }

    // 🔥 toutes les réclamations (admin)
    public function findAllOrdered()
    {
        return $this->createQueryBuilder('r')
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // 🔥 réclamations en attente
    public function findPending()
    {
        return $this->createQueryBuilder('r')
            ->where('r.statut = :s')
            ->setParameter('s', 'EN_ATTENTE')
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // 🔥 réclamations d’un utilisateur
    public function findByUser($user)
    {
        return $this->createQueryBuilder('r')
            ->where('r.user = :u')
            ->setParameter('u', $user)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // 🔥 réclamations contre un utilisateur (prestataire ou étudiant)
    public function findByCible($user)
    {
        return $this->createQueryBuilder('r')
            ->where('r.cible = :c')
            ->setParameter('c', $user)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // 🔥 statistiques admin
    public function countByStatut(string $statut): int
    {
        return $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.statut = :s')
            ->setParameter('s', $statut)
            ->getQuery()
            ->getSingleScalarResult();
    }

    // 🔥 recherche par type
    public function findByType(string $type)
    {
        return $this->createQueryBuilder('r')
            ->where('r.type = :t')
            ->setParameter('t', $type)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}