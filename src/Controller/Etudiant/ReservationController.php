<?php

namespace App\Controller\Etudiant;

use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/etudiant/reservations')]
class ReservationController extends AbstractController
{
    #[Route('/', name: 'etudiant_reservations')]
    public function index(
        ReservationRepository $repo,
        ServiceRepository $serviceRepo,
        Request $request
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ETUDIANT');

        $user = $this->getUser();

        $reservations = $repo->findBy(['user' => $user], ['date' => 'DESC']);

        $total = count($reservations);
        $pending = count(array_filter($reservations, fn($r) => $r->getStatus() === 'PENDING'));
        $confirmed = count(array_filter($reservations, fn($r) => $r->getStatus() === 'CONFIRMED'));
        $cancelled = count(array_filter($reservations, fn($r) => $r->getStatus() === 'CANCELLED'));

        $services = $serviceRepo->findAll();

        $showForm = $request->query->get('new');
        $editId = $request->query->get('edit');

        return $this->render('etudiant/reservations.html.twig', [
            'reservations' => $reservations,
            'total' => $total,
            'pending' => $pending,
            'confirmed' => $confirmed,
            'cancelled' => $cancelled,
            'services' => $services,
            'showForm' => $showForm,
            'editId' => $editId
        ]);
    }

    #[Route('/new', name: 'etudiant_reservation_new', methods: ['POST'])]
    public function new(Request $request, EntityManagerInterface $em, ServiceRepository $serviceRepo): Response
    {
        $service = $serviceRepo->find($request->request->get('service_id'));

        $reservation = new Reservation();
        $reservation->setUser($this->getUser());
        $reservation->setService($service);
        $reservation->setDate(new \DateTime($request->request->get('date')));
        $reservation->setPrice($service->getPrice());
        $reservation->setStatus('PENDING');

        $em->persist($reservation);
        $em->flush();

        return $this->redirectToRoute('etudiant_reservations');
    }

    #[Route('/{id}/edit', name: 'etudiant_reservation_edit', methods: ['POST'])]
    public function edit(Reservation $reservation, Request $request, EntityManagerInterface $em): Response
    {
        if ($reservation->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $reservation->setDate(new \DateTime($request->request->get('date')));
        $em->flush();

        return $this->redirectToRoute('etudiant_reservations');
    }

    #[Route('/{id}/cancel', name: 'etudiant_reservation_cancel', methods: ['POST'])]
    public function cancel(Reservation $reservation, EntityManagerInterface $em): Response
    {
        if ($reservation->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $reservation->setStatus('CANCELLED');
        $em->flush();

        return $this->redirectToRoute('etudiant_reservations');
    }


    #[Route('/{id}/delete', name: 'etudiant_reservation_delete', methods: ['POST'])]
public function delete(Reservation $reservation, EntityManagerInterface $em): Response
{
    if ($reservation->getUser() !== $this->getUser()) {
        throw $this->createAccessDeniedException();
    }

    $em->remove($reservation);
    $em->flush();

    return $this->redirectToRoute('etudiant_reservations');
}
}