<?php

namespace App\Controller\Admin;

use App\Entity\Reclamation;
use App\Repository\ReclamationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/reclamations')]
class ReclamationController extends AbstractController
{
    #[Route('/', name: 'admin_reclamations')]
    public function index(ReclamationRepository $repo): Response
    {
        $reclamations = $repo->findBy([], ['createdAt' => 'DESC']);

        return $this->render('admin/reclamations.html.twig', [
            'reclamations' => $reclamations
        ]);
    }

    #[Route('/{id}/traiter', name: 'admin_reclamation_traiter')]
    public function traiter(Reclamation $rec, EntityManagerInterface $em): Response
    {
        $rec->setStatut('TRAITEE');
        $em->flush();

        return $this->redirectToRoute('admin_reclamations');
    }

    #[Route('/{id}/delete', name: 'admin_reclamation_delete')]
    public function delete(Reclamation $rec, EntityManagerInterface $em): Response
    {
        $em->remove($rec);
        $em->flush();

        return $this->redirectToRoute('admin_reclamations');
    }
}