<?php

namespace App\Controller\Etudiant;

use App\Entity\Payment;
use App\Entity\Invoice;
use App\Entity\Reservation;
use App\Entity\Service;
use App\Entity\User;
use App\Repository\ReservationRepository;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PaymentController extends AbstractController
{
    private const DUMMY_AMOUNT        = 99.99;
    private const DUMMY_PROVIDER_NAME = "Marie Dubois";
    private const DUMMY_SERVICE_TITLE = "Cours de mathématiques";
    private const DUMMY_SERVICE_PRICE = 99.99;

    #[Route('/payment', name: 'payment_index')]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        ReservationRepository $reservationRepo,
        ServiceRepository $serviceRepo
    ): Response {
        $user    = $this->getUser();
        $message = null;

        // ─── STEP 1: get any service owned by a PRESTATAIRE ──────────────
        $service = $serviceRepo->createQueryBuilder('s')
            ->innerJoin('s.user', 'u')
            ->andWhere('u.userType = :type')
            ->setParameter('type', 'PRESTATAIRE')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$service) {
            // Find a prestataire to own the dummy service
            $prestataire = $em->getRepository(User::class)
                ->createQueryBuilder('u')
                ->andWhere('u.userType = :type')
                ->setParameter('type', 'PRESTATAIRE')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$prestataire) {
                throw $this->createNotFoundException('Aucun prestataire trouvé en base.');
            }

            $service = new Service();
            $service->setTitle(self::DUMMY_SERVICE_TITLE);
            $service->setDescription('Service de démonstration généré automatiquement.');
            $service->setPrice((string) self::DUMMY_SERVICE_PRICE);
            $service->setUser($prestataire); // ✅ PRESTATAIRE only
            $service->setStatus('EN_ATTENTE');

            $em->persist($service);
            $em->flush();
        }

        // ─── STEP 2: get or create a reservation for this student ────────
        $reservation = $reservationRepo->findOneBy(['user' => $user]);

        if (!$reservation) {
            $reservation = new Reservation();
            $reservation->setUser($user);           // ✅ ETUDIANT as student
            $reservation->setService($service);     // ✅ service owned by PRESTATAIRE
            $reservation->setDate(new \DateTime());
            $reservation->setPrice((string) self::DUMMY_SERVICE_PRICE);
            $reservation->setStatus('CONFIRMED');

            $em->persist($reservation);
            $em->flush();
        }

        // ─── STEP 3: handle form submission ──────────────────────────────
        if ($request->isMethod('POST')) {

            $lat     = $request->request->get('lat');
            $lng     = $request->request->get('lng');
            $address = $request->request->get('address');

            if (!$lat || !$lng || !$address) {
                return $this->render('etudiant/payment.html.twig', [
                    'message'      => "Veuillez choisir un lieu de rencontre ❌",
                    'providerName' => self::DUMMY_PROVIDER_NAME,
                    'amount'       => self::DUMMY_AMOUNT,
                ]);
            }

            $amount = $request->request->get('amount') ?: self::DUMMY_AMOUNT;
            $method = $request->request->get('payment_method') === 'd17' ? 'VIRTUAL' : 'PHYSICAL';

            $payment = new Payment();
            $payment->setReservation($reservation);
            $payment->setAmount($amount);
            $payment->setMethod($method);
            $payment->setMeetingLat((float) $lat);
            $payment->setMeetingLng((float) $lng);
            $payment->setMeetingAddress($address);

            $em->persist($payment);
            $em->flush();

            $studentName     = $user->getName();
            $serviceName     = $service->getTitle();
            $reservationDate = $reservation->getDate()->format('d/m/Y à H\hi');

            $details = "L'utilisateur " . $studentName
                . " a sollicité l'utilisateur " . self::DUMMY_PROVIDER_NAME
                . " pour le service \"" . $serviceName . "\""
                . " lors de la réservation du " . $reservationDate
                . " au lieu suivant : " . $address . ".";

            $invoice = new Invoice();
            $invoice->setPayment($payment);
            $invoice->setIssueDate(new \DateTime());
            $invoice->setDetails($details);

            $em->persist($invoice);
            $em->flush();

            $this->addFlash('success', "Engagement enregistré ✅ — Facture générée");

            return $this->redirectToRoute('invoice_index');
        }

        return $this->render('etudiant/payment.html.twig', [
            'providerName' => self::DUMMY_PROVIDER_NAME,
            'amount'       => self::DUMMY_AMOUNT,
            'message'      => $message,
        ]);
    }
}