<?php

namespace App\Controller;

use App\Entity\Categorie;
use App\Entity\Service;
use App\Entity\User;
use App\Repository\DemandeRepository;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/services')]
#[IsGranted('ROLE_USER')]
class ServiceController extends AbstractController
{
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private const MAX_SIZE_BYTES = 5 * 1024 * 1024;

    #[Route('/mes', name: 'service_mine', methods: ['GET'])]
    public function mine(Request $req, ServiceRepository $repo, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->getUserType() === 'ETUDIANT') {
            return $this->redirectToRoute('student_index');
        }
        if ($user->getUserType() === 'ADMIN') {
            return $this->redirectToRoute('admin_dashboard');
        }

        $kw = trim((string) $req->query->get('q', ''));
        $catId = $req->query->getInt('category') ?: null;
        $pMin = $req->query->get('priceMin') ? (float) $req->query->get('priceMin') : null;
        $pMax = $req->query->get('priceMax') ? (float) $req->query->get('priceMax') : null;
        $status = $req->query->get('status') ?: null;
        $allowed = ['EN_ATTENTE', 'CONFIRMEE', 'REFUSEE', 'TERMINEE'];
        if (!\in_array($status, $allowed, true)) {
            $status = null;
        }

        $services = ($kw !== '' || $catId || $pMin !== null || $pMax !== null || $status)
            ? $repo->search($kw, $catId, $pMin, $pMax, $status, $user)
            : $repo->findByUser($user);

        return $this->render('service/index.html.twig', [
            'services' => $services,
            'categories' => $em->getRepository(Categorie::class)->findAll(),
        ]);
    }

    #[Route('', name: 'service_catalog', methods: ['GET'])]
    public function catalog(Request $req, ServiceRepository $repo, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->getUserType() === 'ETUDIANT') {
            return $this->redirectToRoute('student_index');
        }
        if ($user->getUserType() === 'ADMIN') {
            return $this->redirectToRoute('admin_dashboard');
        }

        $kw = trim((string) $req->query->get('q', ''));
        $catId = $req->query->getInt('category') ?: null;
        $pMin = $req->query->get('priceMin') ? (float) $req->query->get('priceMin') : null;
        $pMax = $req->query->get('priceMax') ? (float) $req->query->get('priceMax') : null;

        $services = ($kw !== '' || $catId || $pMin !== null || $pMax !== null)
            ? $repo->searchCatalogServices($kw, $catId, $pMin, $pMax)
            : $repo->findAllServicesForListing();

        return $this->render('service/catalog.html.twig', [
            'services' => $services,
            'categories' => $em->getRepository(Categorie::class)->findAll(),
        ]);
    }

    #[Route('/create', name: 'service_create', methods: ['GET', 'POST'])]
    public function create(Request $req, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$this->canManageServices($user)) {
            throw $this->createAccessDeniedException('Seuls les prestataires peuvent créer des services.');
        }

        if ($req->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('service_create', $req->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('service_create');
            }

            $title = trim((string) $req->request->get('title', ''));
            $priceRaw = $req->request->get('price');
            if ($title === '' || $priceRaw === null || $priceRaw === '' || !is_numeric($priceRaw) || (float) $priceRaw <= 0) {
                $this->addFlash('error', 'Titre et prix valide (supérieur à 0) sont obligatoires.');
                return $this->redirectToRoute('service_create');
            }

            $service = new Service();
            $service->setTitle($title)
                ->setDescription($req->request->get('description') ? trim((string) $req->request->get('description')) : null)
                ->setPrice(number_format((float) $priceRaw, 2, '.', ''))
                ->setUser($user)
                ->setStatus('EN_ATTENTE');

            $catId = $req->request->getInt('category_id');
            if ($catId) {
                $cat = $em->find(Categorie::class, $catId);
                if ($cat) {
                    $service->setCategory($cat);
                }
            }

            $error = $this->handleImageUpload($req, $service);
            if ($error) {
                $this->addFlash('error', $error);
                return $this->redirectToRoute('service_create');
            }

            $em->persist($service);
            $em->flush();

            $this->addFlash('success', 'Service créé avec succès.');
            return $this->redirectToRoute('service_mine');
        }

        return $this->render('service/create.html.twig', [
            'categories' => $em->getRepository(Categorie::class)->findAll(),
        ]);
    }

    #[Route('/{id}/edit', name: 'service_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Service $service, Request $req, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$this->canManageServices($user)) {
            throw $this->createAccessDeniedException('Seuls les prestataires peuvent modifier des services.');
        }
        $this->denyAccessUnlessOwner($service);

        if ($req->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('service_edit_' . $service->getId(), $req->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('service_edit', ['id' => $service->getId()]);
            }

            $title = trim((string) $req->request->get('title', ''));
            $priceRaw = $req->request->get('price');
            if ($title === '' || $priceRaw === null || $priceRaw === '' || !is_numeric($priceRaw) || (float) $priceRaw <= 0) {
                $this->addFlash('error', 'Titre et prix valide (supérieur à 0) sont obligatoires.');
                return $this->redirectToRoute('service_edit', ['id' => $service->getId()]);
            }

            $service->setTitle($title)
                ->setDescription($req->request->get('description') ? trim((string) $req->request->get('description')) : null)
                ->setPrice(number_format((float) $priceRaw, 2, '.', ''));

            $catId = $req->request->getInt('category_id');
            $cat = $catId ? $em->find(Categorie::class, $catId) : null;
            $service->setCategory($cat);

            $error = $this->handleImageUpload($req, $service);
            if ($error) {
                $this->addFlash('error', $error);
                return $this->redirectToRoute('service_edit', ['id' => $service->getId()]);
            }

            $em->flush();
            $this->addFlash('success', 'Service modifié.');
            return $this->redirectToRoute('service_mine');
        }

        return $this->render('service/create.html.twig', [
            'service' => $service,
            'categories' => $em->getRepository(Categorie::class)->findAll(),
        ]);
    }

    #[Route('/{id}/delete', name: 'service_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Service $service, Request $req, EntityManagerInterface $em, DemandeRepository $dr): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$this->canManageServices($user)) {
            throw $this->createAccessDeniedException('Seuls les prestataires peuvent supprimer des services.');
        }

        if (!$this->isCsrfTokenValid('service_delete_' . $service->getId(), $req->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('service_mine');
        }

        $this->denyAccessUnlessOwner($service);

        if ($dr->countActiveForService($service) > 0) {
            $this->addFlash('error', 'Impossible de supprimer un service avec des réservations actives.');
            return $this->redirectToRoute('service_mine');
        }

        $em->remove($service);
        $em->flush();
        $this->addFlash('success', 'Service supprimé.');
        return $this->redirectToRoute('service_mine');
    }

    #[Route('/{id}', name: 'service_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Service $service): Response
    {
        return $this->render('service/show.html.twig', [
            'service' => $service,
        ]);
    }

    private function denyAccessUnlessOwner(Service $service): void
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($user->getUserType() === 'ADMIN') {
            return;
        }
        if ($service->getUser() !== $user) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas le propriétaire de ce service.');
        }
    }

    private function canManageServices(User $user): bool
    {
        return \in_array($user->getUserType(), ['PRESTATAIRE', 'ADMIN'], true);
    }

    private function handleImageUpload(Request $req, Service $service): ?string
    {
        $img = $req->files->get('image');
        if (!$img) {
            return null;
        }

        if (!in_array($img->getMimeType(), self::ALLOWED_MIME, true)) {
            return 'Format d\'image non autorisé (jpeg, png, webp, gif uniquement).';
        }
        if ($img->getSize() > self::MAX_SIZE_BYTES) {
            return 'L\'image ne doit pas dépasser 5 Mo.';
        }

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads';
        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            return 'Impossible de créer le dossier d\'envoi des fichiers.';
        }

        $name = bin2hex(random_bytes(16)) . '.' . $img->guessExtension();
        $img->move($uploadDir, $name);
        $service->setImage('uploads/' . $name);

        return null;
    }
}
