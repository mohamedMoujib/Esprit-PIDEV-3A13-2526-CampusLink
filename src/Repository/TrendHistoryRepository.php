<?php

namespace App\Repository;

use App\Entity\TrendHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TrendHistory>
 */
class TrendHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrendHistory::class);
    }

    public function save(TrendHistory $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return TrendHistory[]
     */
    public function findLastEntries(string $categoryName, int $limit = 7): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.categoryName = :name')
            ->setParameter('name', $categoryName)
            ->orderBy('t.recordedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
