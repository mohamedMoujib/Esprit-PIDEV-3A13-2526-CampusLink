<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\ServiceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class StudentController extends AbstractController
{
    #[Route('/rechercher', name: 'student_index')]
    public function index(Request $req, ServiceRepository $repo): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($user->getUserType() === 'PRESTATAIRE') {
            return $this->redirectToRoute('service_catalog');
        }
        if ($user->getUserType() === 'ADMIN') {
            return $this->redirectToRoute('admin_home');
        }

        $kw = trim((string) $req->query->get('q', ''));
        $pMin = $req->query->get('priceMin') ? (float) $req->query->get('priceMin') : null;
        $pMax = $req->query->get('priceMax') ? (float) $req->query->get('priceMax') : null;

        $services = ($kw !== '' || $pMin !== null || $pMax !== null)
            ? $repo->searchAllServicesForListing($kw, $pMin, $pMax)
            : $repo->findAllServicesForListing();

        return $this->render('student/index.html.twig', [
            'services' => $services,
        ]);
    }
}
