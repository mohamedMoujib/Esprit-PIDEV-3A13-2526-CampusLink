<?php

namespace App\Controller\Prestataire;

use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/prestataire/reservations')]
class ReservationController extends AbstractController
{
    #[Route('/', name: 'prestataire_reservations')]
    public function index(ReservationRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_PRESTATAIRE');

        $user = $this->getUser();

        $reservations = $repo->createQueryBuilder('r')
            ->join('r.service', 's')
            ->addSelect('s')
            ->where('s.user = :user')
            ->setParameter('user', $user)
            ->orderBy('r.date', 'DESC')
            ->getQuery()
            ->getResult();

        // stats
        $total = count($reservations);

        $pending = array_filter($reservations, fn($r) => $r->getStatus() === 'PENDING');
        $confirmed = array_filter($reservations, fn($r) => $r->getStatus() === 'CONFIRMED');

        $revenue = 0;
        foreach ($confirmed as $r) {
            $revenue += (float) $r->getPrice();
        }

        return $this->render('prestataire/reservations.html.twig', [
            'reservations' => $reservations,
            'total' => $total,
            'pending' => count($pending),
            'confirmed' => count($confirmed),
            'revenue' => $revenue,
        ]);
    }

    #[Route('/{id}/confirm', name: 'reservation_confirm', methods: ['POST'])]
    public function confirm(Reservation $reservation, EntityManagerInterface $em): Response
    {
        // sécurité
        if ($reservation->getService()->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $reservation->setStatus('CONFIRMED');
        $em->flush();

        return $this->redirectToRoute('prestataire_reservations');
    }

    #[Route('/{id}/cancel', name: 'reservation_cancel', methods: ['POST'])]
    public function cancel(Reservation $reservation, EntityManagerInterface $em): Response
    {
        if ($reservation->getService()->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $reservation->setStatus('CANCELLED');
        $em->flush();

        return $this->redirectToRoute('prestataire_reservations');
    }

    #[Route('/{id}/delete', name: 'reservation_delete', methods: ['POST'])]
    public function delete(Reservation $reservation, EntityManagerInterface $em): Response
    {
        if ($reservation->getService()->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($reservation);
        $em->flush();

        return $this->redirectToRoute('prestataire_reservations');
    }
}