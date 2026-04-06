<?php

namespace App\Repository;

use App\Entity\Review;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    // ===================== READ BY STUDENT =====================

    public function findByStudentWithDetails(int $studentId): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.student', 's')
            ->leftJoin('r.prestataire', 'p')
            ->leftJoin('r.reservation', 'res')
            ->leftJoin('res.service', 'srv')
            ->addSelect('s', 'p', 'res', 'srv')
            ->where('s.id = :studentId')
            ->setParameter('studentId', $studentId)
            ->orderBy('r.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // ===================== READ BY TUTOR =====================

    public function findByTutorWithDetails(int $tutorId): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.student', 's')
            ->leftJoin('r.prestataire', 'p')
            ->leftJoin('r.reservation', 'res')
            ->leftJoin('res.service', 'srv')
            ->addSelect('s', 'p', 'res', 'srv')
            ->where('p.id = :tutorId')
            ->setParameter('tutorId', $tutorId)
            ->orderBy('r.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // ===================== READ ALL (ADMIN) =====================

    public function findAllWithDetails(): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.student', 's')
            ->leftJoin('r.prestataire', 'p')
            ->leftJoin('r.reservation', 'res')
            ->leftJoin('res.service', 'srv')
            ->addSelect('s', 'p', 'res', 'srv')
            ->orderBy('r.isReported', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // ===================== CHECK EXISTENCE =====================

    public function existsByStudentAndReservation(int $studentId, int $reservationId): bool
    {
        return (bool) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->join('r.student', 's')
            ->join('r.reservation', 'res')
            ->where('s.id = :studentId')
            ->andWhere('res.id = :reservationId')
            ->setParameter('studentId', $studentId)
            ->setParameter('reservationId', $reservationId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    // ===================== CONFIRMED RESERVATIONS WITHOUT REVIEW =====================

    public function getConfirmedReservationsForStudent(int $studentId): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT 
                r.id            AS reservation_id,
                s.title         AS service_title,
                u.name          AS prestataire_name,
                s.prestataire_id
            FROM reservations r
            JOIN services s ON r.service_id = s.id
            JOIN users u    ON s.prestataire_id = u.id
            WHERE r.student_id = :studentId
              AND r.status = :status
              AND NOT EXISTS (
                  SELECT 1 FROM reviews rev
                  WHERE rev.reservation_id = r.id
                    AND rev.student_id = :studentId2
              )
            ORDER BY r.date DESC
        ';

        return $conn->executeQuery($sql, [
            'studentId'  => $studentId,
            'status'     => 'CONFIRMED',
            'studentId2' => $studentId,
        ])->fetchAllAssociative();
    }

    // ===================== REPORTED COUNT =====================

    public function getReportedReviewsCount(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.isReported = true')
            ->getQuery()
            ->getSingleScalarResult();
    }

    // ===================== REPORT =====================

    public function reportReview(int $reviewId, string $reason): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            'UPDATE reviews SET is_reported = 1, report_reason = ?, reported_at = NOW() WHERE id = ?',
            [$reason, $reviewId]
        );
    }

    // ===================== UNREPORT =====================

    public function unreportReview(int $reviewId): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            'UPDATE reviews SET is_reported = 0, report_reason = NULL, reported_at = NULL WHERE id = ?',
            [$reviewId]
        );
    }

    // ===================== FIND PRESTATAIRE ID BY RESERVATION =====================

    public function findPrestataireIdByReservation(int $reservationId): ?int
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT s.prestataire_id
            FROM reservations r
            JOIN services s ON r.service_id = s.id
            JOIN users u    ON s.prestataire_id = u.id
            WHERE r.id = :reservationId
              AND u.user_type = :userType
        ';

        $result = $conn->executeQuery($sql, [
            'reservationId' => $reservationId,
            'userType'      => 'PRESTATAIRE',
        ])->fetchOne();

        return $result !== false ? (int) $result : null;
    }

    // ===================== TRUST POINTS =====================

    public function getTrustPointsByUserId(int $userId): int
    {
        $result = $this->getEntityManager()->getConnection()->executeQuery(
            'SELECT trust_points FROM users WHERE id = ?',
            [$userId]
        )->fetchOne();

        return $result !== false ? (int) $result : 0;
    }

    public function applyTrustPoints(int $prestataireId, int $points): void
    {
        if ($points === 0) return;

        $conn = $this->getEntityManager()->getConnection();

        // Initialiser à 0 si NULL
        $conn->executeStatement(
            'UPDATE users SET trust_points = COALESCE(trust_points, 0) + ? WHERE id = ?',
            [$points, $prestataireId]
        );

        $conn->executeStatement(
            "INSERT INTO trust_point_history (prestataire_id, points_added, reason, date) VALUES (?, ?, 'REVIEW_RATING', NOW())",
            [$prestataireId, $points]
        );
    }

    public function applyTrustPointsForEdit(int $prestataireId, int $oldRating, int $newRating): void
    {
        if ($oldRating === $newRating) return;

        $conn = $this->getEntityManager()->getConnection();

        // Retirer l'ancienne note
        if ($oldRating !== 0) {
            $conn->executeStatement(
                'UPDATE users SET trust_points = COALESCE(trust_points, 0) - ? WHERE id = ?',
                [$oldRating, $prestataireId]
            );

            $conn->executeStatement(
                "INSERT INTO trust_point_history (prestataire_id, points_added, reason, date) VALUES (?, ?, 'REVIEW_RATING', NOW())",
                [$prestataireId, -$oldRating]
            );
        }

        // Ajouter la nouvelle note
        if ($newRating !== 0) {
            $conn->executeStatement(
                'UPDATE users SET trust_points = COALESCE(trust_points, 0) + ? WHERE id = ?',
                [$newRating, $prestataireId]
            );

            $conn->executeStatement(
                "INSERT INTO trust_point_history (prestataire_id, points_added, reason, date) VALUES (?, ?, 'REVIEW_RATING', NOW())",
                [$prestataireId, $newRating]
            );
        }
    }
}