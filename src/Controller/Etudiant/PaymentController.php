<?php

namespace App\Controller\Etudiant;

use App\Entity\Payment;
use App\Entity\Invoice;
use App\Entity\Reservation;
use App\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PaymentController extends AbstractController
{
    private const DUMMY_RESERVATION_ID = 1;
    private const DUMMY_AMOUNT = 99.99;

    private const DUMMY_USER1 = "Ahmed Ben Ali";
    private const DUMMY_USER2 = "Marie Dubois";
    private const DUMMY_SERVICE = "Cours de mathématiques";
    private const DUMMY_RESERVATION_DATE = "28 février 2026 à 14h00";

    #[Route('/payment', name: 'payment_index')]
    public function index(Request $request, EntityManagerInterface $em, PaymentRepository $paymentRepo): Response
    {
        $message = null;

        // Fetch the reservation from DB
        $reservation = $em->getRepository(Reservation::class)->find(self::DUMMY_RESERVATION_ID);
        if (!$reservation) {
            throw $this->createNotFoundException('Reservation not found');
        }

        if ($request->isMethod('POST')) {

            $lat = $request->request->get('lat');
            $lng = $request->request->get('lng');
            $address = $request->request->get('address');

            if (!$lat || !$lng || !$address) {
                $message = "Veuillez choisir un lieu de rencontre ❌";
                return $this->render('etudiant/payment.html.twig', [
                    'message' => $message,
                    'providerName' => self::DUMMY_USER2,
                    'amount' => self::DUMMY_AMOUNT
                ]);
            }

            $amount = $request->request->get('amount') ?: self::DUMMY_AMOUNT;
            $method = $request->request->get('payment_method') === 'd17' ? 'VIRTUAL' : 'PHYSICAL';

            // ✅ Create Payment
            $payment = new Payment();
            $payment->setReservation($reservation);
            $payment->setAmount($amount);
            $payment->setMethod($method);
            $payment->setMeetingLat((float)$lat);
            $payment->setMeetingLng((float)$lng);
            $payment->setMeetingAddress($address);

            $em->persist($payment);
            $em->flush();

            // ✅ Create Invoice
            $details = "L'utilisateur " . self::DUMMY_USER1
                . " a sollicité l'utilisateur " . self::DUMMY_USER2
                . " pour le service \"" . self::DUMMY_SERVICE . "\""
                . " lors de la réservation du " . self::DUMMY_RESERVATION_DATE
                . " au lieu suivant : " . $address . ".";

            $invoice = new Invoice();
            $invoice->setPayment($payment);
            $invoice->setIssueDate(new \DateTime()); 
            $invoice->setDetails($details);

            $em->persist($invoice);
            $em->flush();

            $this->addFlash('success', "Engagement enregistré ✅ — Facture générée");

            // Redirect to invoice list or details page
            return $this->redirectToRoute('invoice_index');
        }

        return $this->render('etudiant/payment.html.twig', [
            'providerName' => self::DUMMY_USER2,
            'amount' => self::DUMMY_AMOUNT,
            'message' => $message
        ]);
    }
}