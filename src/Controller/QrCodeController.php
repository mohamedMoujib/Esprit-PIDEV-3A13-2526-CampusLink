<?php

namespace App\Controller;

use App\Repository\PublicationRepository;
use App\Repository\ServiceRepository;
use Endroid\QrCode\Builder\BuilderInterface;
use Endroid\QrCodeBundle\Response\QrCodeResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * SVG QR codes (SvgWriter) — works without ext-gd. PNG would require GD for PngWriter.
 */
#[Route('/qr')]
class QrCodeController extends AbstractController
{
    #[Route('/service/{id}', name: 'app_qr_service', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function service(int $id, ServiceRepository $serviceRepo, BuilderInterface $svgCustomQrCodeBuilder): Response
    {
        if ($serviceRepo->find($id) === null) {
            throw $this->createNotFoundException('Service introuvable.');
        }

        $url = $this->generateUrl('service_show', ['id' => $id], UrlGeneratorInterface::ABSOLUTE_URL);
        $result = $svgCustomQrCodeBuilder->build(data: $url, size: 280, margin: 10);

        return new QrCodeResponse($result);
    }

    #[Route('/publication/{id}', name: 'app_qr_publication', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function publication(int $id, PublicationRepository $publicationRepo, BuilderInterface $svgCustomQrCodeBuilder): Response
    {
        if ($publicationRepo->find($id) === null) {
            throw $this->createNotFoundException('Publication introuvable.');
        }

        $url = $this->generateUrl('publication_show', ['id' => $id], UrlGeneratorInterface::ABSOLUTE_URL);
        $result = $svgCustomQrCodeBuilder->build(data: $url, size: 280, margin: 10);

        return new QrCodeResponse($result);
    }
}
