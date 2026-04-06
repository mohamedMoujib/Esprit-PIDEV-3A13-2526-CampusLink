<?php

namespace App\Controller\Prestataire;

use App\Entity\Service;
use App\Form\ServiceType;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/prestataire/services')]
class ServiceController extends AbstractController
{
    #[Route('/', name: 'prestataire_services')]
    public function index(ServiceRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_PRESTATAIRE');
        
        $services = $repo->findBy([
            'user' => $this->getUser()
        ]);

        return $this->render('prestataire/services.html.twig', [
            'services' => $services
        ]);
    }

    #[Route('/new', name: 'prestataire_service_new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_PRESTATAIRE');
        
        $service = new Service();

        $form = $this->createForm(ServiceType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $service->setUser($this->getUser());
            $service->setStatus('EN_ATTENTE');

            $em->persist($service);
            $em->flush();

            $this->addFlash('success', 'Service créé avec succès!');
            return $this->redirectToRoute('prestataire_services');
        }

        return $this->render('prestataire/service_form.html.twig', [
            'form' => $form->createView(),
            'service' => $service,
            'title' => 'Ajouter un service'
        ]);
    }

    #[Route('/{id}/edit', name: 'prestataire_service_edit')]
    public function edit(
        Service $service,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_PRESTATAIRE');
        
        // Verify ownership
        if ($service->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier ce service');
        }

        $form = $this->createForm(ServiceType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Service mis à jour avec succès!');
            return $this->redirectToRoute('prestataire_services');
        }

        return $this->render('prestataire/service_form.html.twig', [
            'form' => $form->createView(),
            'service' => $service,
            'title' => 'Modifier le service'
        ]);
    }

    #[Route('/{id}/delete', name: 'prestataire_service_delete', methods: ['POST'])]
    public function delete(
        Service $service,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_PRESTATAIRE');
        
        // Verify ownership
        if ($service->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer ce service');
        }

        $em->remove($service);
        $em->flush();

        $this->addFlash('info', 'Service supprimé!');
        return $this->redirectToRoute('prestataire_services');
    }

    #[Route('/{id}', name: 'prestataire_service_details')]
    public function details(Service $service): Response
    {
        $this->denyAccessUnlessGranted('ROLE_PRESTATAIRE');
        
        // Verify ownership
        if ($service->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas voir ce service');
        }

        return $this->render('prestataire/service_details.html.twig', [
            'service' => $service
        ]);
    }
}