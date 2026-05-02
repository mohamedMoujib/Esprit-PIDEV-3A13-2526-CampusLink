<?php

namespace App\Controller\Admin;

use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/reservations')]
class ReservationController extends AbstractController
{
    #[Route('/', name: 'admin_reservations')]
    public function index(ReservationRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $reservations = $repo->findBy([], ['date' => 'DESC']);

        $total = count($reservations);
        $pending = count(array_filter($reservations, fn($r) => $r->getStatus() === 'PENDING'));
        $confirmed = count(array_filter($reservations, fn($r) => $r->getStatus() === 'CONFIRMED'));
        $cancelled = count(array_filter($reservations, fn($r) => $r->getStatus() === 'CANCELLED'));

        $revenue = 0;
        foreach ($reservations as $r) {
            if ($r->getStatus() === 'CONFIRMED') {
                $revenue += (float)$r->getPrice();
            }
        }

        return $this->render('admin/reservations.html.twig', [
            'reservations' => $reservations,
            'total' => $total,
            'pending' => $pending,
            'confirmed' => $confirmed,
            'cancelled' => $cancelled,
            'revenue' => $revenue,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_reservation_delete', methods: ['POST'])]
    public function delete(Reservation $reservation, EntityManagerInterface $em): Response
    {
        $em->remove($reservation);
        $em->flush();

        return $this->redirectToRoute('admin_reservations');
    }
}