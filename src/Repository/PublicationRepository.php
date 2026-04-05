<?php

namespace App\Repository;

use App\Entity\Publication;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Publication>
 */
class PublicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Publication::class);
    }

    /** @return Publication[] */
    public function findAllOrderedByDate(): array
    {
        return $this->createBaseQueryBuilder()
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return Publication[] */
    public function findByUser(User $user): array
    {
        return $this->createBaseQueryBuilder()
            ->andWhere('p.user = :u')->setParameter('u', $user)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function createBaseQueryBuilder(): \Doctrine\ORM\QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')->addSelect('u')
            ->leftJoin('p.category', 'c')->addSelect('c')
            ->leftJoin('p.service', 's')->addSelect('s');
    }

    public function findRecentDemandeService(): array
    {
        return $this->createBaseQueryBuilder()
            ->andWhere('p.typePublication = :type')
            ->setParameter('type', 'DEMANDE_SERVICE')
            ->andWhere('p.status IN (:statuses)')
            ->setParameter('statuses', ['ACTIVE', 'EN_COURS'])
            ->andWhere('p.createdAt >= :since')
            ->setParameter('since', new \DateTime('-1 hour'))
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
