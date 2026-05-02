<?php

namespace App\Controller\Prestataire;

use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Service\AiService;

#[Route('/prestataire/reservations')]
class ReservationController extends AbstractController
{
    #[Route('/', name: 'prestataire_reservations')]
    public function index(ReservationRepository $repo, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_PRESTATAIRE');

        $user = $this->getUser();

        // ── Toutes les réservations (pour stats et pagination manuelle) ──
        $allReservations = $repo->createQueryBuilder('r')
            ->join('r.service', 's')
            ->addSelect('s')
            ->where('s.user = :user')
            ->setParameter('user', $user)
            ->orderBy('r.date', 'DESC')
            ->getQuery()
            ->getResult();

        // ── Stats globales (calculées sur TOUTES les réservations) ──
        $total     = count($allReservations);
        $pending   = count(array_filter($allReservations, fn($r) => $r->getStatus() === 'PENDING'));
        $confirmed = count(array_filter($allReservations, fn($r) => $r->getStatus() === 'CONFIRMED'));
        $cancelled = count(array_filter($allReservations, fn($r) => $r->getStatus() === 'CANCELLED'));

        $revenue = array_reduce(
            array_filter($allReservations, fn($r) => $r->getStatus() === 'CONFIRMED'),
            fn($carry, $r) => $carry + (float) $r->getPrice(),
            0.0
        );

        // ── Pagination manuelle ──
        $page       = max(1, $request->query->getInt('page', 1));
        $limit      = 5;
        $totalPages = (int) ceil($total / $limit);
        $page       = min($page, max($totalPages, 1)); // clamp

        $reservations = array_slice($allReservations, ($page - 1) * $limit, $limit);

        return $this->render('prestataire/reservations.html.twig', [
            'reservations' => $reservations,
            'currentPage'  => $page,
            'totalPages'   => $totalPages,
            'total'        => $total,
            'pending'      => $pending,
            'confirmed'    => $confirmed,
            'cancelled'    => $cancelled,
            'revenue'      => number_format($revenue, 2, '.', ''),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CONFIRM
    // ─────────────────────────────────────────────────────────────────────────
    #[Route('/{id}/confirm', name: 'reservation_confirm', methods: ['POST'])]
    public function confirm(Reservation $reservation, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_PRESTATAIRE');

        if ($reservation->getService()->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $reservation->setStatus('CONFIRMED');
        $em->flush();

        $this->addFlash('success', 'Réservation confirmée.');

        return $this->redirectToRoute('prestataire_reservations');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CANCEL
    // ─────────────────────────────────────────────────────────────────────────
    #[Route('/{id}/cancel', name: 'reservation_cancel', methods: ['POST'])]
    public function cancel(Reservation $reservation, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_PRESTATAIRE');

        if ($reservation->getService()->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $reservation->setStatus('CANCELLED');
        $em->flush();

        $this->addFlash('success', 'Réservation annulée.');

        return $this->redirectToRoute('prestataire_reservations');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DELETE
    // ─────────────────────────────────────────────────────────────────────────
    #[Route('/{id}/delete', name: 'reservation_delete', methods: ['POST'])]
    public function delete(Reservation $reservation, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_PRESTATAIRE');

        if ($reservation->getService()->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($reservation);
        $em->flush();

        $this->addFlash('success', 'Réservation supprimée.');

        return $this->redirectToRoute('prestataire_reservations');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CALENDAR
    // ─────────────────────────────────────────────────────────────────────────
    #[Route('/calendar', name: 'prestataire_calendar')]
    public function calendar(ReservationRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_PRESTATAIRE');

        $user = $this->getUser();

        $reservations = $repo->createQueryBuilder('r')
            ->join('r.service', 's')
            ->addSelect('s')
            ->where('s.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        return $this->render('prestataire/calendar.html.twig', [
            'reservations' => $reservations,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // REVENUE
    // ─────────────────────────────────────────────────────────────────────────
    #[Route('/revenue', name: 'prestataire_revenue')]
public function revenue(ReservationRepository $repo, AiService $ai): Response
{
    $this->denyAccessUnlessGranted('ROLE_PRESTATAIRE');

    $user = $this->getUser();

    // 🔥 récupérer réservations confirmées
    $reservations = $repo->createQueryBuilder('r')
        ->join('r.service', 's')
        ->where('s.user = :user')
        ->andWhere('r.status = :status')
        ->setParameter('user', $user)
        ->setParameter('status', 'CONFIRMED')
        ->getQuery()
        ->getResult();

    // 🔥 traduction jours
    $joursFR = [
        'Monday'    => 'Lundi',
        'Tuesday'   => 'Mardi',
        'Wednesday' => 'Mercredi',
        'Thursday'  => 'Jeudi',
        'Friday'    => 'Vendredi',
        'Saturday'  => 'Samedi',
        'Sunday'    => 'Dimanche',
    ];

    $stats        = [];
    $totalRevenue = 0.0;

    // 🔥 calcul stats
    foreach ($reservations as $r) {
        $dayEN = $r->getDate()->format('l');
        $dayFR = $joursFR[$dayEN] ?? $dayEN;

        $stats[$dayFR] = ($stats[$dayFR] ?? 0) + (float) $r->getPrice();
        $totalRevenue  += (float) $r->getPrice();
    }

    // 🔥 calcul moyenne + prediction
    $joursCount = count($stats) ?: 1;
    $moyenne    = $totalRevenue / $joursCount;
    $prediction = $moyenne * 30;

    // 🤖 IA (sécurisée)
    $aiResult = "Analyse indisponible";

    try {
        $aiResult = $ai->analyseRevenue($stats);
    } catch (\Exception $e) {
        $aiResult = "Erreur IA : vérifiez votre API Key";
    }

    // 🔥 render
    return $this->render('prestataire/revenue.html.twig', [
        'stats'      => $stats,
        'moyenne'    => $moyenne,
        'prediction' => $prediction,
        'aiResult'   => $aiResult, // ✅ CORRECTION ICI
    ]);
}



    
}