<?php

namespace App\Controller\Etudiant;

use App\Entity\Payment;
use App\Entity\Invoice;
use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PaymentController extends AbstractController
{
    #[Route('/payment/{id}', name: 'payment_index')]
    public function index(
        Reservation $reservation,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();

        if ($reservation->getUser() !== $user) {
            throw $this->createAccessDeniedException();
        }

        if ($request->isMethod('POST')) {

            $lat = $request->request->get('lat');
            $lng = $request->request->get('lng');
            $address = $request->request->get('address');

            if (!$lat || !$lng || !$address) {
                return $this->render('etudiant/payment.html.twig', [
                    'message' => "Veuillez choisir un lieu de rencontre ❌",
                    'reservation' => $reservation
                ]);
            }

            $amount = $reservation->getPrice();
            $method = $request->request->get('payment_method') === 'd17' ? 'VIRTUAL' : 'PHYSICAL';

            $payment = new Payment();
            $payment->setReservation($reservation);
            $payment->setAmount($amount);
            $payment->setMethod($method);
            $payment->setMeetingLat((float) $lat);
            $payment->setMeetingLng((float) $lng);
            $payment->setMeetingAddress($address);

            $em->persist($payment);

            $studentName = $user->getName();
            $serviceName = $reservation->getService()->getTitle();
            $reservationDate = $reservation->getDate()->format('d/m/Y à H\hi');

            $detailsStudent = "Vous avez réservé le service \"" . $serviceName . "\""
                . " le " . $reservationDate
                . " au lieu : " . $address . ".";

            $invoiceStudent = new Invoice();
            $invoiceStudent->setPayment($payment);
            $invoiceStudent->setUser($user);
            $invoiceStudent->setIssueDate(new \DateTime());
            $invoiceStudent->setDetails($detailsStudent);

            $em->persist($invoiceStudent);

            $provider = $reservation->getService()->getUser();

            $detailsProvider = "Votre service \"" . $serviceName . "\" a été réservé par "
                . $studentName
                . " le " . $reservationDate
                . " au lieu : " . $address . ".";

            $invoiceProvider = new Invoice();
            $invoiceProvider->setPayment($payment);
            $invoiceProvider->setUser($provider);
            $invoiceProvider->setIssueDate(new \DateTime());
            $invoiceProvider->setDetails($detailsProvider);

            $em->persist($invoiceProvider);

            $em->flush();

            $this->addFlash('success', "Paiement effectué ✅ — Factures générées");

            return $this->redirectToRoute('invoice_index');
        }

        return $this->render('etudiant/payment.html.twig', [
            'reservation' => $reservation
        ]);
    }
}