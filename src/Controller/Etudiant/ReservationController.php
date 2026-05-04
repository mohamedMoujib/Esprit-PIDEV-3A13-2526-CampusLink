<?php

namespace App\Controller\Etudiant;

use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use App\Repository\ServiceRepository;
use App\Service\SmsService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\User;

#[Route('/etudiant/reservations')]
class ReservationController extends AbstractController
{
    // ─────────────────────────────────────────────────────────────────────────
    // INDEX
    // ─────────────────────────────────────────────────────────────────────────
    #[Route('/', name: 'etudiant_reservations')]
    public function index(
        ReservationRepository $repo,
        ServiceRepository     $serviceRepo,
        Request               $request,
        PaginatorInterface    $paginator
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ETUDIANT');

        $user = $this->getUser();

        $query = $repo->createQueryBuilder('r')
            ->join('r.service', 's')
            ->addSelect('s')
            ->where('r.user = :user')
            ->setParameter('user', $user)
            ->orderBy('r.date', 'DESC')
            ->getQuery();

        $reservations = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            5
        );

        $allReservations = $repo->findBy(['user' => $user]);

        $total     = count($allReservations);
        $pending   = count(array_filter($allReservations, fn($r) => $r->getStatus() === 'PENDING'));
        $confirmed = count(array_filter($allReservations, fn($r) => $r->getStatus() === 'CONFIRMED'));
        $cancelled = count(array_filter($allReservations, fn($r) => $r->getStatus() === 'CANCELLED'));

        $coordinationStatus = [];
        foreach ($allReservations as $r) {
            $coordinationStatus[$r->getId()] = method_exists($r, 'isCoordinated')
                ? $r->isCoordinated()
                : false;
        }

        return $this->render('etudiant/reservations.html.twig', [
            'reservations'       => $reservations,
            'total'              => $total,
            'pending'            => $pending,
            'confirmed'          => $confirmed,
            'cancelled'          => $cancelled,
            'services'           => $serviceRepo->findAll(),
            'showForm'           => $request->query->get('new'),
            'editId'             => (int) $request->query->get('edit'),
            'coordinationStatus' => $coordinationStatus,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // NEW
    // ─────────────────────────────────────────────────────────────────────────
    #[Route('/new', name: 'etudiant_reservation_new', methods: ['POST'])]
    public function new(
        Request                $request,
        EntityManagerInterface $em,
        ServiceRepository      $serviceRepo,
        SmsService             $sms,
        NotificationService    $notif,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ETUDIANT');

        $serviceId = $request->request->get('service_id');
        $date      = $request->request->get('date');
        $service   = $serviceRepo->find((int) $serviceId); // ✅ cast int

        if (!$service || !$date) {
            $this->addFlash('error', 'Service ou date invalide.');
            return $this->redirectToRoute('etudiant_reservations');
        }

        /** @var User $etudiant */
        $etudiant = $this->getUser();

        $reservation = new Reservation();
        $reservation->setUser($etudiant);
        $reservation->setService($service);
        $reservation->setDate(new \DateTime((string) $date)); // ✅ cast string
        $reservation->setPrice($service->getPrice());
        $reservation->setStatus('PENDING');

        $em->persist($reservation);
        $em->flush();

        // ── SMS au prestataire ──
        $prestataire = $service->getUser();

        if ($prestataire && $prestataire->getPhone()) {
            $message = sprintf(
                "Nouvelle réservation\n%s a réservé votre service \"%s\".\nMerci de confirmer.",
                $etudiant->getName(),
                $service->getTitle()
            );

            try {
                $sms->sendSms($prestataire->getPhone(), $message);
            } catch (\Exception $e) {
                // Ne pas bloquer la réservation si le SMS échoue
            }
        }

        // ── Notifications in-app ──
        if ($prestataire) {
            $notif->notifyInApp(
                $prestataire,
                '📅 Nouvelle réservation',
                "{$etudiant->getName()} a réservé votre service \"{$service->getTitle()}\" pour le {$reservation->getDate()->format('d/m/Y à H:i')}"
            );
        }

        $notif->notifyInApp(
            $etudiant,
            '✅ Réservation confirmée',
            "Votre réservation pour \"{$service->getTitle()}\" a bien été enregistrée."
        );

        $this->addFlash('success', 'Réservation créée avec succès.');
        return $this->redirectToRoute('etudiant_reservations');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EDIT
    // ─────────────────────────────────────────────────────────────────────────
    #[Route('/{id}/edit', name: 'etudiant_reservation_edit', methods: ['POST'])]
    public function edit(
        Reservation            $reservation,
        Request                $request,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ETUDIANT');

        if ($reservation->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($reservation->getStatus() !== 'PENDING') {
            $this->addFlash('error', 'Impossible de modifier une réservation confirmée ou annulée.');
            return $this->redirectToRoute('etudiant_reservations');
        }

        $date = $request->request->get('date');

        if (!$date) {
            $this->addFlash('error', 'Date invalide.');
            return $this->redirectToRoute('etudiant_reservations', ['edit' => $reservation->getId()]);
        }

        $reservation->setDate(new \DateTime((string) $date)); // ✅ cast string
        $em->flush();

        $this->addFlash('success', 'Réservation modifiée.');
        return $this->redirectToRoute('etudiant_reservations');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CANCEL
    // ─────────────────────────────────────────────────────────────────────────
    #[Route('/{id}/cancel', name: 'etudiant_reservation_cancel', methods: ['POST'])]
    public function cancel(
    Reservation            $reservation,
    EntityManagerInterface $em,
    SmsService             $sms
): Response {
    $this->denyAccessUnlessGranted('ROLE_ETUDIANT');

    if ($reservation->getUser() !== $this->getUser()) {
        throw $this->createAccessDeniedException();
    }

    if ($reservation->getStatus() === 'CONFIRMED') {
        $this->addFlash('error', "Impossible d'annuler une réservation déjà confirmée.");
        return $this->redirectToRoute('etudiant_reservations');
    }

    /** @var User $etudiant */
    $etudiant    = $this->getUser();
    $nomEtudiant = $etudiant->getName();

    // ✅ نتحققو من service قبل ما نستعملوه
    $service = $reservation->getService();

    if ($reservation->getStatus() === 'PENDING' && $service !== null) {
        $prestataire = $service->getUser(); // ✅ آمن الآن

        if ($prestataire && $prestataire->getPhone()) {
            $message = "❌ Annulation\n"
                . $nomEtudiant
                . " a annulé la réservation \""
                . $service->getTitle() // ✅ آمن الآن
                . "\" avant votre confirmation.";

            try {
                $sms->sendSms($prestataire->getPhone(), $message);
            } catch (\Exception $e) {
                // ignore erreur SMS
            }
        }
    }

    $reservation->setStatus('CANCELLED');
    $em->flush();

    $this->addFlash('success', 'Réservation annulée.');
    return $this->redirectToRoute('etudiant_reservations');
}

    // ─────────────────────────────────────────────────────────────────────────
    // DELETE
    // ─────────────────────────────────────────────────────────────────────────
    #[Route('/{id}/delete', name: 'etudiant_reservation_delete', methods: ['POST'])]
    public function delete(
        Reservation            $reservation,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ETUDIANT');

        if ($reservation->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($reservation->getStatus() === 'CONFIRMED') {
            $this->addFlash('error', 'Impossible de supprimer une réservation confirmée.');
            return $this->redirectToRoute('etudiant_reservations');
        }

        $em->remove($reservation);
        $em->flush();

        $this->addFlash('success', 'Réservation supprimée.');
        return $this->redirectToRoute('etudiant_reservations');
    }
}