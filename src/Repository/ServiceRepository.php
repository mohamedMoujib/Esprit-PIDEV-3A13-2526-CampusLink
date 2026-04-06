<?php

namespace App\Repository;

use App\Entity\Service;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Service>
 */
class ServiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Service::class);
    }

    public function findAllWithDetails(): array
    {
        return $this->createBaseQueryBuilder()
            ->orderBy('s.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByUser(User $user): array
    {
        return $this->createBaseQueryBuilder()
            ->andWhere('s.user = :u')->setParameter('u', $user)
            ->orderBy('s.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPublicServices(): array
    {
        return $this->findAllServicesForListing();
    }

    /** @return Service[] */
    public function findAllServicesForListing(): array
    {
        return $this->createBaseQueryBuilder()
            ->orderBy('s.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return Service[] */
    public function searchAllServicesForListing(
        string $kw = '',
        ?float $pMin = null,
        ?float $pMax = null,
    ): array {
        $qb = $this->createBaseQueryBuilder();

        if ($kw !== '') {
            $qb->andWhere('s.title LIKE :kw OR s.description LIKE :kw')
               ->setParameter('kw', '%' . $kw . '%');
        }
        if ($pMin !== null) {
            $qb->andWhere('s.price >= :pmin')->setParameter('pmin', $pMin);
        }
        if ($pMax !== null) {
            $qb->andWhere('s.price <= :pmax')->setParameter('pmax', $pMax);
        }

        return $qb->orderBy('s.id', 'DESC')->getQuery()->getResult();
    }

    /** @return Service[] */
    public function searchCatalogServices(
        string $kw = '',
        ?int $catId = null,
        ?float $pMin = null,
        ?float $pMax = null,
    ): array {
        $qb = $this->createBaseQueryBuilder();

        if ($kw !== '') {
            $qb->andWhere('s.title LIKE :kw OR s.description LIKE :kw')
               ->setParameter('kw', '%' . $kw . '%');
        }
        if ($catId) {
            $qb->andWhere('c.id = :cat')->setParameter('cat', $catId);
        }
        if ($pMin !== null) {
            $qb->andWhere('s.price >= :pmin')->setParameter('pmin', $pMin);
        }
        if ($pMax !== null) {
            $qb->andWhere('s.price <= :pmax')->setParameter('pmax', $pMax);
        }

        return $qb->orderBy('s.id', 'DESC')->getQuery()->getResult();
    }

    /** @return Service[] */
    public function search(
        string $kw = '',
        ?int $catId = null,
        ?float $pMin = null,
        ?float $pMax = null,
        ?string $status = null,
        ?User $owner = null,
    ): array {
        $qb = $this->createBaseQueryBuilder();

        if ($kw !== '') {
            $qb->andWhere('s.title LIKE :kw OR s.description LIKE :kw')
               ->setParameter('kw', '%' . $kw . '%');
        }
        if ($catId) {
            $qb->andWhere('c.id = :cat')->setParameter('cat', $catId);
        }
        if ($pMin !== null) {
            $qb->andWhere('s.price >= :pmin')->setParameter('pmin', $pMin);
        }
        if ($pMax !== null) {
            $qb->andWhere('s.price <= :pmax')->setParameter('pmax', $pMax);
        }
        if ($status) {
            $qb->andWhere('s.status = :st')->setParameter('st', $status);
        }
        if ($owner instanceof User) {
            $qb->andWhere('s.user = :owner')->setParameter('owner', $owner);
        }

        return $qb->orderBy('s.id', 'DESC')->getQuery()->getResult();
    }

    public function searchPublicServices(
        string $kw = '',
        ?int $catId = null,
        ?float $pMin = null,
        ?float $pMax = null,
    ): array {
        if ($catId) {
            return $this->searchCatalogServices($kw, $catId, $pMin, $pMax);
        }

        return $this->searchAllServicesForListing($kw, $pMin, $pMax);
    }

    /**
     * @return Service[]
     */
    public function findActiveServices(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.status = :st')
            ->setParameter('st', 'CONFIRMEE')
            ->getQuery()
            ->getResult();
    }

    private function createBaseQueryBuilder()
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.category', 'c')->addSelect('c')
            ->leftJoin('s.user', 'u')->addSelect('u')
            ->leftJoin('s.demandes', 'd')->addSelect('d')
            ->leftJoin('s.reservations', 'r')->addSelect('r');
    }
}
