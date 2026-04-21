<?php

namespace App\Controller\Prestataire;

use App\Entity\Service;
use App\Form\ServiceType;
use App\Repository\ReviewRepository;
use App\Repository\ServiceRepository;
use App\Repository\UserRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/prestataire/services')]
class ServiceController extends AbstractController
{
    public function __construct(
        private readonly NotificationService $notif,
        private readonly UserRepository $userRepository,
    ) {}

    #[Route('/', name: 'prestataire_services')]
    public function index(ServiceRepository $repo, ReviewRepository $reviewRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_PRESTATAIRE');

        $services = $repo->findBy([
            'user' => $this->getUser(),
        ]);

        $serviceRatings = [];
        foreach ($services as $service) {
            $id = $service->getId();
            if ($id === null) {
                continue;
            }
            $serviceRatings[$id] = [
                'avg' => $reviewRepo->getAverageRatingByService($id),
                'count' => $reviewRepo->countByService($id),
            ];
        }

        return $this->render('prestataire/services.html.twig', [
            'services' => $services,
            'serviceRatings' => $serviceRatings,
            'reviews_index_route' => 'tutor_reviews_index',
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

            $admins = $this->userRepository->findBy(['userType' => 'ADMIN']);
            foreach ($admins as $admin) {
                $owner = $service->getUser();
                $ownerName = $owner ? $owner->getName() : 'Prestataire';
                $this->notif->notifyInApp($admin, '🆕 Nouveau service en attente',
                    "Le prestataire {$ownerName} a soumis le service \"{$service->getTitle()}\" — en attente de validation.");
            }

            $this->addFlash('success', 'Service créé avec succès!');

            return $this->redirectToRoute('prestataire_services');
        }

        return $this->render('prestataire/service_form.html.twig', [
            'form' => $form->createView(),
            'service' => $service,
            'title' => 'Ajouter un service',
        ]);
    }

    #[Route('/{id}/edit', name: 'prestataire_service_edit')]
    public function edit(
        Service $service,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_PRESTATAIRE');

        if ($service->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier ce service');
        }

        $form = $this->createForm(ServiceType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $prestataire = $service->getUser();
            if ($prestataire) {
                $this->notif->notifyInApp($prestataire, '✏️ Service mis à jour',
                    "Votre service \"{$service->getTitle()}\" a été modifié et enregistré.");
            }

            $this->addFlash('success', 'Service mis à jour avec succès!');

            return $this->redirectToRoute('prestataire_services');
        }

        return $this->render('prestataire/service_form.html.twig', [
            'form' => $form->createView(),
            'service' => $service,
            'title' => 'Modifier le service',
        ]);
    }

    #[Route('/{id}/delete', name: 'prestataire_service_delete', methods: ['POST'])]
    public function delete(
        Service $service,
        EntityManagerInterface $em,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_PRESTATAIRE');

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

        if ($service->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas voir ce service');
        }

        return $this->render('prestataire/service_details.html.twig', [
            'service' => $service,
        ]);
    }
}
