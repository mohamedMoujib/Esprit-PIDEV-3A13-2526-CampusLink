<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use OTPHP\TOTP;
use ParagonIE\ConstantTime\Base32;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter; 
use Endroid\QrCode\QrCode;       

class TwoFactorController extends AbstractController
{
      #[Route('/2fa/setup', name: 'app_2fa_setup')]
    public function setup(Request $request): Response
    {
        $session = $request->getSession();

        if ($session->get('2fa_secret')) {
            return $this->redirectToRoute('app_2fa_check');
        }

        $secret = Base32::encode(random_bytes(16));
        $session->set('2fa_secret', $secret);
        $session->set('2fa_verified', false);

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $totp = TOTP::create($secret);
        $totp->setLabel($user->getEmail());
        $totp->setIssuer('CampusLink');

        // ── v3 QR generation ──
        $qrCode = new QrCode($totp->getProvisioningUri());
        $qrCode->setSize(200);
        $qrCode->setMargin(10);

        $writer = new SvgWriter(); 
        $result = $writer->write($qrCode);

        $qrDataUri = 'data:image/svg+xml;base64,' . base64_encode($result->getString());

        return $this->render('User/2fa_setup.html.twig', [
            'qrDataUri' => $qrDataUri,
            'manualKey' => $secret,
        ]);
    }

    // ── Step 2: Verify code every login ──
    #[Route('/2fa/check', name: 'app_2fa_check', methods: ['GET', 'POST'])]
    public function check(Request $request): Response
    {
        $session = $request->getSession();
        $secret  = $session->get('2fa_secret');

        // No secret yet → back to setup
        if (!$secret) {
            return $this->redirectToRoute('app_2fa_setup');
        }

        if ($request->isMethod('POST')) {
            $code = trim($request->request->get('auth_code', ''));
            $totp = TOTP::create($secret);

            if ($totp->verify($code, null, 1)) {
                $session->set('2fa_verified', true);

                /** @var \App\Entity\User $user */
                $user = $this->getUser();

                return match($user->getUserType()) {
                    'ADMIN'       => $this->redirectToRoute('admin_dashboard'),
                    'PRESTATAIRE' => $this->redirectToRoute('prestataire_reservations'),
                    default       => $this->redirectToRoute('etudiant_reservations'),
                };
            }

            $this->addFlash('error', '❌ Code invalide. Réessayez.');
        }

        return $this->render('User/2fa_check.html.twig');
    }
    #[Route('/2fa/reset', name: 'app_2fa_reset')]
public function reset(Request $request): Response
{
    $request->getSession()->remove('2fa_secret');
    $request->getSession()->remove('2fa_verified');
    return $this->redirectToRoute('app_2fa_setup');
}
}