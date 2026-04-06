<?php
namespace App\Service;

use App\Entity\Demande;
use App\Entity\Service;
use Doctrine\ORM\EntityManagerInterface;

/** Mirrors TutorDashboardService.java — uses CampusLink schema (demandes, services). */
class TutorDashboardService
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function getDashboardStats(int $tutorId): array
    {
        $serviceRepo = $this->em->getRepository(Service::class);
        $demandeRepo = $this->em->getRepository(Demande::class);

        $totalServices = (int) $serviceRepo->count(['prestataireId' => $tutorId]);
        $totalReservations = (int) $demandeRepo->count(['prestataireId' => $tutorId]);

        $revenueQb = $this->em->createQueryBuilder()
            ->select('COALESCE(SUM(s.price), 0)')
            ->from(Demande::class, 'd')
            ->join('d.service', 's')
            ->where('d.prestataireId = :tid')
            ->andWhere('d.status IN (:states)')
            ->setParameter('tid', $tutorId)
            ->setParameter('states', ['CONFIRMEE', 'TERMINEE']);

        $totalRevenue = (float) $revenueQb->getQuery()->getSingleScalarResult();

        $since = new \DateTimeImmutable('-6 months');
        $recentDemandes = $this->em->createQueryBuilder()
            ->select('d', 's')
            ->from(Demande::class, 'd')
            ->join('d.service', 's')
            ->where('d.prestataireId = :tid')
            ->andWhere('d.createdAt >= :since')
            ->setParameter('tid', $tutorId)
            ->setParameter('since', $since)
            ->orderBy('d.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        $monthlyBuckets = [];
        foreach ($recentDemandes as $demande) {
            /** @var Demande $demande */
            $key = $demande->getCreatedAt()->format('Y-m');
            if (!isset($monthlyBuckets[$key])) {
                $monthlyBuckets[$key] = [
                    'month' => $key,
                    'reservations' => 0,
                    'revenue' => 0.0,
                ];
            }
            $monthlyBuckets[$key]['reservations']++;
            $monthlyBuckets[$key]['revenue'] += $demande->getService()->getPrice();
        }
        ksort($monthlyBuckets);
        $monthlyStats = array_values($monthlyBuckets);

        $topRows = $this->em->createQueryBuilder()
            ->select('s.title AS name, COUNT(d.id) AS bookings')
            ->from(Service::class, 's')
            ->leftJoin(Demande::class, 'd', 'WITH', 'd.service = s')
            ->where('s.prestataireId = :tid')
            ->groupBy('s.id')
            ->addGroupBy('s.title')
            ->orderBy('bookings', 'DESC')
            ->setMaxResults(5)
            ->setParameter('tid', $tutorId)
            ->getQuery()
            ->getArrayResult();

        $topServices = array_map(static function (array $row): array {
            return [
                'name' => $row['name'],
                'bookings' => (int) $row['bookings'],
                'avgRating' => 0.0,
            ];
        }, $topRows);

        return [
            'totalServices' => $totalServices,
            'totalReservations' => $totalReservations,
            'totalRevenue' => $totalRevenue,
            'averageRating' => 0.0,
            'trustPoints' => 0,
            'totalReviews' => 0,
            'monthlyStats' => $monthlyStats,
            'topServices' => $topServices,
            'recentReviews' => [],
            'ratingDistribution' => [],
        ];
    }
}
