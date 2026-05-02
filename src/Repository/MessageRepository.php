<?php

namespace App\Repository;

use App\Entity\Message;
use App\Entity\User; // ✅ IMPORTANT
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    // ✅ Compter messages non lus
    public function countUnread(User $user): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.receiver = :u')
            ->andWhere('m.isRead = false')
            ->setParameter('u', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
}