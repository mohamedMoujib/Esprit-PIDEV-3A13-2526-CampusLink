<?php

namespace App\Controller;

use App\Entity\Categorie;
use App\Entity\Publication;
use App\Entity\User;
use App\Repository\PublicationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/publications')]
#[IsGranted('ROLE_USER')]
class PublicationController extends AbstractController
{
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private const MAX_SIZE_BYTES = 5 * 1024 * 1024;
    private const PUBLICATION_TYPES = ['VENTE_OBJET', 'DEMANDE_SERVICE', 'OFFRE_SERVICE'];
    private const PUBLICATION_STATUSES = ['ACTIVE', 'EN_COURS', 'TERMINEE', 'ANNULEE'];

    #[Route('', name: 'publication_index')]
    public function index(Request $req, PublicationRepository $repo): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($user->getUserType() === 'ADMIN') {
            return $this->redirectToRoute('admin_publications');
        }
        if ($user->getUserType() === 'PRESTATAIRE') {
            return $this->redirectToRoute('service_catalog');
        }

        $tab = $req->query->get('tab', 'all');
        $q = trim((string) $req->query->get('q', ''));
        $type = $req->query->get('type');
        $status = $req->query->get('status');

        $isPrestataire = $user->getUserType() === 'PRESTATAIRE';
        $allowedTypes = $isPrestataire
            ? ['VENTE_OBJET', 'DEMANDE_SERVICE', 'OFFRE_SERVICE']
            : ['VENTE_OBJET', 'DEMANDE_SERVICE'];

        if (!\is_string($type) || !\in_array($type, $allowedTypes, true)) {
            $type = null;
        }
        if (!$isPrestataire || !\is_string($status) || !\in_array($status, self::PUBLICATION_STATUSES, true)) {
            $status = null;
        }

        $publications = $tab === 'mine'
            ? $repo->findByUser($user)
            : $repo->findAllOrderedByDate();

        $filtered = array_values(array_filter($publications, function (Publication $publication) use ($q, $type, $status): bool {
            if ($q !== '') {
                $needle = mb_strtolower($q);
                $haystack = mb_strtolower($publication->getTitre() . ' ' . $publication->getMessage());
                if (!str_contains($haystack, $needle)) {
                    return false;
                }
            }
            if ($type !== null && $publication->getTypePublication() !== $type) {
                return false;
            }
            if ($status !== null && $publication->getStatus() !== $status) {
                return false;
            }
            return true;
        }));

        return $this->render('publication/index.html.twig', [
            'publications' => $filtered,
            'activeTab' => $tab,
            'isPrestataireView' => $isPrestataire,
            'canCreatePublication' => $user->getUserType() === 'ETUDIANT',
        ]);
    }

    #[Route('/create', name: 'publication_create', methods: ['GET', 'POST'])]
    public function create(Request $req, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($user->getUserType() === 'PRESTATAIRE') {
            return $this->redirectToRoute('service_catalog');
        }
        if (!$this->canCreatePublications($user)) {
            throw $this->createAccessDeniedException('Seuls les étudiants peuvent créer des publications.');
        }

        if ($req->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('publication_create', $req->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('publication_create');
            }

            $titre = trim((string) $req->request->get('titre', ''));
            $message = trim((string) $req->request->get('message', ''));
            if ($titre === '' || $message === '') {
                $this->addFlash('error', 'Le titre et la description sont obligatoires.');
                return $this->redirectToRoute('publication_create');
            }

            $type = $req->request->get('type_publication', 'DEMANDE_SERVICE');
            if (!\in_array($type, ['DEMANDE_SERVICE', 'VENTE_OBJET'], true)) {
                $type = 'DEMANDE_SERVICE';
            }

            $pub = new Publication();
            $pub->setUser($user)
                ->setTypePublication($type)
                ->setTitre($titre)
                ->setMessage($message)
                ->setLocalisation($req->request->get('localisation') ? trim((string) $req->request->get('localisation')) : null)
                ->setStatus('ACTIVE');

            $prixErr = $this->applyPublicationPrice($pub, $req->request->get('prix'));
            if ($prixErr !== null) {
                $this->addFlash('error', $prixErr);
                return $this->redirectToRoute('publication_create');
            }

            $catId = $req->request->getInt('category_id');
            if ($catId) {
                $cat = $em->find(Categorie::class, $catId);
                if ($cat) {
                    $pub->setCategory($cat);
                }
            }

            $error = $this->handleImageUpload($req, $pub);
            if ($error) {
                $this->addFlash('error', $error);
                return $this->redirectToRoute('publication_create');
            }

            $em->persist($pub);
            $em->flush();

            $this->addFlash('success', 'Publication créée avec succès.');
            return $this->redirectToRoute('publication_index');
        }

        return $this->render('publication/create.html.twig', [
            'categories' => $em->getRepository(Categorie::class)->findAll(),
        ]);
    }

    #[Route('/{id}/edit', name: 'publication_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Publication $pub, Request $req, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($user->getUserType() === 'PRESTATAIRE') {
            return $this->redirectToRoute('service_catalog');
        }
        if (!$this->canEditPublication($user, $pub)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier cette publication.');
        }

        if ($req->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('publication_edit_' . $pub->getId(), $req->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('publication_edit', ['id' => $pub->getId()]);
            }

            $titre = trim((string) $req->request->get('titre', ''));
            $message = trim((string) $req->request->get('message', ''));
            if ($titre === '' || $message === '') {
                $this->addFlash('error', 'Le titre et la description sont obligatoires.');
                return $this->redirectToRoute('publication_edit', ['id' => $pub->getId()]);
            }

            if ($user->getUserType() === 'ETUDIANT') {
                $pub->setTypePublication('DEMANDE_SERVICE');
            } else {
                $type = $req->request->get('type_publication', $pub->getTypePublication());
                if (!\in_array($type, self::PUBLICATION_TYPES, true)) {
                    $type = $pub->getTypePublication();
                }
                $pub->setTypePublication($type);
            }

            $pub->setTitre($titre)
                ->setMessage($message)
                ->setLocalisation($req->request->get('localisation') ? trim((string) $req->request->get('localisation')) : null);

            $prixErr = $this->applyPublicationPrice($pub, $req->request->get('prix'));
            if ($prixErr !== null) {
                $this->addFlash('error', $prixErr);
                return $this->redirectToRoute('publication_edit', ['id' => $pub->getId()]);
            }

            $catId = $req->request->getInt('category_id');
            $cat = $catId ? $em->find(Categorie::class, $catId) : null;
            $pub->setCategory($cat);

            $error = $this->handleImageUpload($req, $pub);
            if ($error) {
                $this->addFlash('error', $error);
                return $this->redirectToRoute('publication_edit', ['id' => $pub->getId()]);
            }

            $pub->setUpdatedAt(new \DateTime());
            $em->flush();

            $this->addFlash('success', 'Publication modifiée.');
            return $this->redirectToRoute('publication_index', ['tab' => 'mine']);
        }

        return $this->render('publication/create.html.twig', [
            'publication' => $pub,
            'categories' => $em->getRepository(Categorie::class)->findAll(),
        ]);
    }

    #[Route('/{id}/delete', name: 'publication_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Publication $pub, Request $req, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($user->getUserType() === 'PRESTATAIRE') {
            return $this->redirectToRoute('service_catalog');
        }
        if (!$this->canEditPublication($user, $pub)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer de publications.');
        }

        if (!$this->isCsrfTokenValid('publication_delete_' . $pub->getId(), $req->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('publication_index');
        }

        $em->remove($pub);
        $em->flush();
        $this->addFlash('success', 'Publication supprimée.');
        return $this->redirectToRoute('publication_index', ['tab' => 'mine']);
    }

    #[Route('/{id}/status', name: 'publication_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function status(Publication $pub, Request $req, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($user->getUserType() === 'PRESTATAIRE') {
            return $this->redirectToRoute('service_catalog');
        }
        if (!$this->canModerateStatus($user)) {
            throw $this->createAccessDeniedException('Seul un prestataire ou l\'admin peut modifier le statut.');
        }

        if (!$this->isCsrfTokenValid('publication_status_' . $pub->getId(), $req->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('publication_index');
        }

        $status = $req->request->get('status');
        if (!\is_string($status) || !\in_array($status, self::PUBLICATION_STATUSES, true)) {
            $this->addFlash('error', 'Statut invalide.');
            return $this->redirectToRoute('publication_index');
        }

        $pub->setStatus($status);
        $pub->setUpdatedAt(new \DateTime());
        $em->flush();

        $this->addFlash('success', 'Statut de la publication mis à jour.');
        return $this->redirectToRoute('publication_index', ['tab' => $req->request->get('active_tab', 'all')]);
    }

    #[Route('/{id}', name: 'publication_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Publication $pub, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($user->getUserType() === 'PRESTATAIRE') {
            return $this->redirectToRoute('service_catalog');
        }

        $pub->setVues(($pub->getVues() ?? 0) + 1);
        $pub->setUpdatedAt(new \DateTime());
        $em->flush();

        return $this->render('publication/show.html.twig', [
            'publication' => $pub,
        ]);
    }

    private function canCreatePublications(User $user): bool
    {
        return \in_array($user->getUserType(), ['ETUDIANT', 'ADMIN'], true);
    }

    private function canEditPublication(User $user, Publication $pub): bool
    {
        if ($user->getUserType() === 'ADMIN') {
            return true;
        }

        return $pub->getUser() === $user;
    }

    private function canModerateStatus(User $user): bool
    {
        return \in_array($user->getUserType(), ['PRESTATAIRE', 'ADMIN'], true);
    }

    private function applyPublicationPrice(Publication $pub, mixed $prixRaw): ?string
    {
        $isEmpty = ($prixRaw === null || $prixRaw === '');
        $type = $pub->getTypePublication();

        if ($isEmpty) {
            if ($type === 'VENTE_OBJET') {
                return 'Le prix de vente est obligatoire pour une vente d\'objet.';
            }

            $pub->setProposedPrice(null);
            $pub->setPrixVente(null);
            return null;
        }

        if (!is_numeric($prixRaw) || (float) $prixRaw < 0) {
            return 'Le prix doit être un nombre positif ou zéro.';
        }

        $formatted = number_format((float) $prixRaw, 2, '.', '');
        if ($type === 'VENTE_OBJET') {
            $pub->setPrixVente($formatted);
            $pub->setProposedPrice(null);
        } else {
            $pub->setProposedPrice($formatted);
            $pub->setPrixVente(null);
        }

        return null;
    }

    private function handleImageUpload(Request $req, Publication $pub): ?string
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
        $pub->setImageUrl('uploads/' . $name);

        return null;
    }
}