<?php

namespace App\Controller\Etudiant;

use App\Entity\Payment;
use App\Entity\Invoice;
use App\Entity\Reservation;
use App\Repository\ReservationRepository;
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

        // 🔐 SECURITY
        if ($reservation->getUser() !== $user) {
            throw $this->createAccessDeniedException();
        }

        if ($request->isMethod('POST')) {

            $lat     = $request->request->get('lat');
            $lng     = $request->request->get('lng');
            $address = $request->request->get('address');

            if (!$lat || !$lng || !$address) {
                return $this->render('etudiant/payment.html.twig', [
                    'message' => "Veuillez choisir un lieu de rencontre ❌",
                    'reservation' => $reservation
                ]);
            }

            $amount = $reservation->getPrice();
            $method = $request->request->get('payment_method') === 'd17' ? 'VIRTUAL' : 'PHYSICAL';

            // ✅ CREATE PAYMENT
            $payment = new Payment();
            $payment->setReservation($reservation);
            $payment->setAmount($amount);
            $payment->setMethod($method);
            $payment->setMeetingLat((float) $lat);
            $payment->setMeetingLng((float) $lng);
            $payment->setMeetingAddress($address);

            $em->persist($payment);

            // ✅ CREATE INVOICE
            $studentName     = $user->getName();
            $serviceName     = $reservation->getService()->getTitle();
            $reservationDate = $reservation->getDate()->format('d/m/Y à H\hi');

            $details = "L'utilisateur " . $studentName
                . " a réservé le service \"" . $serviceName . "\""
                . " le " . $reservationDate
                . " au lieu : " . $address . ".";

            $invoice = new Invoice();
            $invoice->setPayment($payment);
            $invoice->setIssueDate(new \DateTime());
            $invoice->setDetails($details);

            $em->persist($invoice);

            $em->flush();

            $this->addFlash('success', "Paiement effectué ✅ — Facture générée");

            return $this->redirectToRoute('invoice_index');
        }

        return $this->render('etudiant/payment.html.twig', [
            'reservation' => $reservation
        ]);
    }
}