<?php

namespace App\Controller;

use App\Entity\Reclamation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/reclamation')]
class ReclamationController extends AbstractController
{
    #[Route('/new/{id}', name: 'reclamation_new')]
    public function new(User $cible, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        $rec = new Reclamation();

        if ($request->isMethod('POST')) {

            $rec->setUser($user);
            $rec->setCible($cible);
            $rec->setType($request->request->get('type'));
            $rec->setSujet($request->request->get('sujet'));
            $rec->setDescription($request->request->get('description'));

            $em->persist($rec);
            $em->flush();

            return $this->redirectToRoute('messages');
        }

        return $this->render('reclamation/new.html.twig', [
            'cible' => $cible
        ]);
    }
}