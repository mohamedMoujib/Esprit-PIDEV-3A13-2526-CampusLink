<?php

namespace App\Controller;

use App\Entity\Demande;
use App\Entity\User;
use App\Repository\DemandeRepository;
use App\Repository\ServiceRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/demandes')]
#[IsGranted('ROLE_USER')]
class DemandeController extends AbstractController
{
    #[Route('', name: 'demande_index')]
    public function index(DemandeRepository $repo): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $demandes = $user->getUserType() === 'PRESTATAIRE'
            ? $repo->findByPrestataire($user)
            : $repo->findByStudent($user);

        return $this->render('demande/index.html.twig', [
            'demandes' => $demandes,
        ]);
    }

    #[Route('/create/{serviceId}', name: 'demande_create', requirements: ['serviceId' => '\d+'], methods: ['GET', 'POST'])]
    public function create(
        int $serviceId,
        Request $req,
        ServiceRepository $sr,
        DemandeRepository $dr,
        EntityManagerInterface $em,
        NotificationService $notif,
    ): Response {
        $service = $sr->find($serviceId);
        if (!$service) {
            throw $this->createNotFoundException('Service introuvable.');
        }

        /** @var User $student */
        $student = $this->getUser();

        if ($dr->hasPendingDemande($student, $service)) {
            $this->addFlash('error', 'Vous avez déjà une demande active pour ce service.');
            return $this->redirectToRoute('student_index');
        }

        if ($req->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('demande_create_' . $serviceId, $req->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('demande_create', ['serviceId' => $serviceId]);
            }

            $demande = new Demande();
            $demande->setStudent($student)
                    ->setService($service)
                    ->setPrestataire($service->getUser())
                    ->setMessage($req->request->get('message'));

            $rawPrice = $req->request->get('proposed_price');
            $demande->setProposedPrice(($rawPrice !== null && $rawPrice !== '') ? $rawPrice : null);

            $dateStr = $req->request->get('requested_date');
            if ($dateStr) {
                $demande->setRequestedDate(new \DateTime($dateStr));
            }

            $em->persist($demande);
            $em->flush();

            $notif->notifyInApp(
                $service->getUser(),
                '📩 Nouvelle demande reçue',
                "Un étudiant a demandé votre service : \"{$service->getTitle()}\""
            );

            $this->addFlash('success', 'Demande envoyée avec succès.');
            return $this->redirectToRoute('demande_index');
        }

        return $this->render('demande/create.html.twig', ['service' => $service]);
    }

    #[Route('/{id}/status', name: 'demande_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function status(Demande $demande, Request $req, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('demande_status_' . $demande->getId(), $req->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('demande_index');
        }

        /** @var User $user */
        $user          = $this->getUser();
        $isPrestataire = $demande->getPrestataire() === $user;
        $isStudent     = $demande->getStudent()     === $user;

        if (!$isPrestataire && !$isStudent) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette demande.');
        }

        $new = $req->request->get('status');
        if (!\is_string($new)) {
            $this->addFlash('error', 'Statut invalide.');
            return $this->redirectToRoute('demande_index');
        }

        if (!$this->canTransitionTo($demande->getStatus(), $new)) {
            $this->addFlash('error', "Transition impossible : {$demande->getStatus()} → $new");
        } else {
            $demande->setStatus($new);
            $em->flush();
            $this->addFlash('success', 'Statut mis à jour.');
        }

        return $this->redirectToRoute('demande_index');
    }

    #[Route('/{id}/delete', name: 'demande_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Demande $demande, Request $req, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('demande_delete_' . $demande->getId(), $req->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('demande_index');
        }

        /** @var User $user */
        $user = $this->getUser();

        if ($demande->getStudent() !== $user) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas le propriétaire de cette demande.');
        }

        if ($demande->getStatus() !== 'PENDING') {
            $this->addFlash('error', 'Seules les demandes en attente peuvent être supprimées.');
            return $this->redirectToRoute('demande_index');
        }

        $em->remove($demande);
        $em->flush();
        $this->addFlash('success', 'Demande supprimée.');
        return $this->redirectToRoute('demande_index');
    }

    private function canTransitionTo(string $current, string $new): bool
    {
        return match ($current) {
            'PENDING'  => in_array($new, ['ACCEPTED', 'REJECTED', 'CANCELLED'], true),
            'ACCEPTED' => $new === 'CANCELLED',
            default    => false,
        };
    }
}
