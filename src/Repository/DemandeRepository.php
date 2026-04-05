<?php

namespace App\Repository;

use App\Entity\Demande;
use App\Entity\Service;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Demande>
 */
class DemandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Demande::class);
    }

    /** @return Demande[] */
    public function findByStudent(User $student): array
    {
        return $this->createQueryBuilder('d')
            ->join('d.service', 's')->addSelect('s')
            ->leftJoin('s.category', 'c')->addSelect('c')
            ->where('d.student = :s')->setParameter('s', $student)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()->getResult();
    }

    /** @return Demande[] */
    public function findByPrestataire(User $prestataire): array
    {
        return $this->createQueryBuilder('d')
            ->join('d.service', 's')->addSelect('s')
            ->where('d.prestataire = :p')->setParameter('p', $prestataire)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()->getResult();
    }

    public function hasPendingDemande(User $student, Service $service): bool
    {
        $count = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.student = :s')->setParameter('s', $student)
            ->andWhere('d.service = :svc')->setParameter('svc', $service)
            ->andWhere("d.status IN ('PENDING', 'ACCEPTED')")
            ->getQuery()->getSingleScalarResult();

        return $count > 0;
    }

    public function countActiveForService(Service $service): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.service = :s')->setParameter('s', $service)
            ->andWhere("d.status IN ('PENDING', 'ACCEPTED')")
            ->getQuery()->getSingleScalarResult();
    }
}
