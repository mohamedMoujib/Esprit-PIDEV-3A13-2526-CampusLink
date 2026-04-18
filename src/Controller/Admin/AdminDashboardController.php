<?php

namespace App\Controller\Admin;

use App\Controller\UserController;
use App\Entity\Categorie;
use App\Entity\Publication;
use App\Entity\Review;
use App\Entity\Service;
use App\Entity\User;
use App\Repository\PublicationRepository;
use App\Repository\UserRepository;
use App\Service\ModerationTrustService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Knp\Component\Pager\PaginatorInterface; 
use Symfony\Component\Mailer\MailerInterface;


#[Route('/admin')]
class AdminDashboardController extends AbstractController
{
    private const SERVICE_STATUSES = ['EN_ATTENTE', 'CONFIRMEE', 'REFUSEE', 'TERMINEE'];
    private const PUBLICATION_STATUSES = ['ACTIVE', 'EN_COURS', 'TERMINEE', 'ANNULEE'];
    private const PUBLICATION_TYPES = ['VENTE_OBJET', 'DEMANDE_SERVICE', 'OFFRE_SERVICE'];
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private const MAX_SIZE_BYTES = 5 * 1024 * 1024;

    public function __construct(
        private UserRepository $userRepository,
        private UserController $userController,
        private EntityManagerInterface $em,
        private ModerationTrustService $moderationTrust,
        private PublicationRepository $publicationRepository,
        private MailerInterface $mailer,
    ) {}

    #[Route('', name: 'admin_home', methods: ['GET'])]
    public function dashboard(): Response
    {
        $counts = $this->userRepository->countByType();
        $etudiants = $counts['ETUDIANT'] ?? 0;
        $prestataires = $counts['PRESTATAIRE'] ?? 0;
        $totalUsers = $etudiants + $prestataires + ($counts['ADMIN'] ?? 0);
        $activeUsers = \count($this->userRepository->findActiveByType('ETUDIANT'))
            + \count($this->userRepository->findActiveByType('PRESTATAIRE'));

        $serviceRepo = $this->em->getRepository(Service::class);
        $pubRepo = $this->em->getRepository(Publication::class);
        $catRepo = $this->em->getRepository(Categorie::class);
        $reviewRepo = $this->em->getRepository(Review::class);

        $servicesTotal = $serviceRepo->count([]);
        $servicesPending = $serviceRepo->count(['status' => 'EN_ATTENTE']);
        $pubsTotal = $pubRepo->count([]);
        $pubsActive = $pubRepo->count(['status' => 'ACTIVE']);
        $categoriesTotal = $catRepo->count([]);
        $reviewsTotal = $reviewRepo->count([]);

        $allServicesForFlag = $serviceRepo->createQueryBuilder('s')
            ->leftJoin('s.category', 'c')->addSelect('c')
            ->leftJoin('s.user', 'u')->addSelect('u')
            ->orderBy('s.id', 'DESC')
            ->getQuery()->getResult();
        $flaggedAll = $this->buildFlaggedServices($allServicesForFlag);

        $recentServices = $serviceRepo->createQueryBuilder('s')
            ->leftJoin('s.user', 'u')->addSelect('u')
            ->leftJoin('s.category', 'c')->addSelect('c')
            ->orderBy('s.id', 'DESC')
            ->setMaxResults(6)
            ->getQuery()->getResult();

        $recentPublications = $this->publicationRepository->createBaseQueryBuilder()
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults(6)
            ->getQuery()->getResult();

        $allCategories = $catRepo->findAll();

        return $this->render('admin/pages/dashboard.html.twig', [
            'stats' => [
                'total_users' => $totalUsers,
                'etudiants' => $etudiants,
                'prestataires' => $prestataires,
                'active_users' => $activeUsers,
                'services_total' => $servicesTotal,
                'services_pending' => $servicesPending,
                'publications_total' => $pubsTotal,
                'publications_active' => $pubsActive,
                'categories_total' => $categoriesTotal,
                'reviews_total' => $reviewsTotal,
                'flagged_count' => \count($flaggedAll),
            ],
            'recentServices' => $recentServices,
            'recentPublications' => $recentPublications,
            'flaggedPreview' => \array_slice($flaggedAll, 0, 5),
            'allCategories' => $allCategories,
        ]);
    }

    #[Route('/dashboard', name: 'admin_dashboard')]
    public function index(Request $request, PaginatorInterface $paginator): Response
    {
        // ── Filtres ──
        $filter = strtoupper($request->query->get('filter', 'ALL'));
        $search = trim($request->query->get('q', ''));
       
        // ── Requête Doctrine ──
        $qb = $this->userRepository->createQueryBuilder('u')
            ->where('u.userType IN (:types)')
            ->setParameter('types', ['ETUDIANT', 'PRESTATAIRE'])
            ->orderBy('u.createdAt', 'DESC');

        if ($filter === 'ETUDIANT') {
            $qb->andWhere('u.userType = :type')->setParameter('type', 'ETUDIANT');
        } elseif ($filter === 'PRESTATAIRE') {
            $qb->andWhere('u.userType = :type')->setParameter('type', 'PRESTATAIRE');
        }

        if ($search !== '') {
            $qb->andWhere('LOWER(u.name) LIKE :search OR LOWER(u.email) LIKE :search')
            ->setParameter('search', '%' . strtolower($search) . '%');
        }

        // ── Pagination ──
        $pagination = $paginator->paginate($qb, $request->query->getInt('page', 1), 5);

        // ── Statistiques ──
        $allUsers = $this->userRepository->findBy(['userType' => ['ETUDIANT', 'PRESTATAIRE']]);
        $stats = [
            'total'        => count($allUsers),
            'etudiants'    => count($this->userRepository->findBy(['userType' => 'ETUDIANT'])),
            'prestataires' => count($this->userRepository->findBy(['userType' => 'PRESTATAIRE'])),
            'active'       => count($this->userRepository->findBy(['userType' => ['ETUDIANT', 'PRESTATAIRE'], 'status' => 'ACTIVE'])),
            'inactive'     => count($this->userRepository->findBy(['userType' => ['ETUDIANT', 'PRESTATAIRE'], 'status' => 'INACTIVE'])),
        ];

        // ── 🔥 HTMX : Si requête AJAX, retourne seulement le fragment #}
        if ($request->headers->get('HX-Request')) {
    return $this->render('admin/partials/_users_tbody.html.twig', [
        'pagination' => $pagination,
        'filter'     => $filter,
        'search'     => $search,
    ]);
}

        // ── Requête normale : page complète ──
        return $this->render('admin/pages/users.html.twig', [
            'pagination' => $pagination,
            'stats'      => $stats,
            'filter'     => $filter,
            'search'     => $search,
        ]);
    }

    // â”€â”€ Activate â”€â”€
    #[Route('/user/{id}/activate', name: 'admin_user_activate', methods: ['POST'])]
    public function activate(int $id): Response
    {
        $jsonRequest = Request::create('/api/users/' . $id, 'PUT',
            content: json_encode(['status' => 'ACTIVE'])
        );
        $jsonRequest->headers->set('Content-Type', 'application/json');
        $this->userController->update($id, $jsonRequest);
        $this->addFlash('success', 'Utilisateur activÃ© avec succÃ¨s !');
        return $this->redirectToRoute('admin_dashboard');
    }

    // â”€â”€ Deactivate â”€â”€
    #[Route('/user/{id}/deactivate', name: 'admin_user_deactivate', methods: ['POST'])]
    public function deactivate(int $id): Response
    {
        $jsonRequest = Request::create('/api/users/' . $id, 'PUT',
            content: json_encode(['status' => 'INACTIVE'])
        );
        $jsonRequest->headers->set('Content-Type', 'application/json');
        $this->userController->update($id, $jsonRequest);
        $this->addFlash('success', 'Utilisateur dÃ©sactivÃ© !');
        return $this->redirectToRoute('admin_dashboard');
    }

    // â”€â”€ Ban â”€â”€
    #[Route('/user/{id}/ban', name: 'admin_user_ban', methods: ['POST'])]
public function ban(int $id): Response
{
    $user = $this->userRepository->find($id);

    $jsonRequest = Request::create('/api/users/' . $id, 'PUT',
        content: json_encode(['status' => 'BANNED'])
    );
    $jsonRequest->headers->set('Content-Type', 'application/json');
    $this->userController->update($id, $jsonRequest);

    if ($user) {
        $emailMessage = (new \Symfony\Component\Mime\Email())
            ->from('no-reply@campuslink.tn')
            ->to($user->getEmail())
            ->subject('Compte banni — CampusLink')
            ->html("
                <div style='font-family:sans-serif; max-width:400px; margin:auto;'>
                    <h2>🎓 CampusLink</h2>
                    <p>Votre compte a été banni de la plateforme.</p>
                </div>
            ");

        $this->mailer->send($emailMessage);
    }

    $this->addFlash('success', 'Utilisateur banni !');
    return $this->redirectToRoute('admin_dashboard');
}

    #[Route('/categories', name: 'admin_categories', methods: ['GET', 'POST'])]
    public function categories(Request $req): Response
    {
        if ($req->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_category_create', $req->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('admin_categories');
            }

            $name = trim((string) $req->request->get('name', ''));
            if ($name === '') {
                $this->addFlash('error', 'Le nom de la catÃ©gorie est obligatoire.');
                return $this->redirectToRoute('admin_categories');
            }

            $category = new Categorie();
            $category->setName($name)
                ->setDescription($req->request->get('description') ? trim((string) $req->request->get('description')) : null);

            $this->em->persist($category);
            $this->em->flush();
            $this->addFlash('success', 'CatÃ©gorie crÃ©Ã©e avec succÃ¨s.');

            return $this->redirectToRoute('admin_categories');
        }

        $categories = $this->em->getRepository(Categorie::class)->createQueryBuilder('c')
            ->leftJoin('c.services', 's')->addSelect('s')
            ->leftJoin('c.publications', 'p')->addSelect('p')
            ->orderBy('c.name', 'ASC')
            ->getQuery()->getResult();

        return $this->render('admin/pages/categories.html.twig', [
            'categories' => $categories,
        ]);
    }

    #[Route('/categories/{id}/edit', name: 'admin_category_edit', methods: ['POST'])]
    public function categoryEdit(Categorie $category, Request $req): Response
    {
        if (!$this->isCsrfTokenValid('admin_category_edit_' . $category->getId(), $req->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_categories');
        }

        $name = trim((string) $req->request->get('name', ''));
        if ($name === '') {
            $this->addFlash('error', 'Le nom de la catÃ©gorie est obligatoire.');
            return $this->redirectToRoute('admin_categories');
        }

        $category->setName($name)
            ->setDescription($req->request->get('description') ? trim((string) $req->request->get('description')) : null);
        $this->em->flush();

        $this->addFlash('success', 'CatÃ©gorie mise Ã  jour.');
        return $this->redirectToRoute('admin_categories');
    }

    #[Route('/categories/{id}/delete', name: 'admin_category_delete', methods: ['POST'])]
    public function categoryDelete(Categorie $category, Request $req): Response
    {
        if (!$this->isCsrfTokenValid('admin_category_delete_' . $category->getId(), $req->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_categories');
        }

        if ($category->getServices()->count() > 0 || $category->getPublications()->count() > 0) {
            $this->addFlash('error', 'Impossible de supprimer une catÃ©gorie dÃ©jÃ  utilisÃ©e par des services ou des publications.');
            return $this->redirectToRoute('admin_categories');
        }

        $this->em->remove($category);
        $this->em->flush();
        $this->addFlash('success', 'CatÃ©gorie supprimÃ©e.');
        return $this->redirectToRoute('admin_categories');
    }

    #[Route('/service-audit', name: 'admin_service_audit')]
    public function serviceAudit(): Response
    {
        $services = $this->em->getRepository(Service::class)->createQueryBuilder('s')
            ->leftJoin('s.user', 'u')->addSelect('u')
            ->leftJoin('s.category', 'c')->addSelect('c')
            ->orderBy('s.id', 'DESC')
            ->getQuery()->getResult();

        return $this->render('admin/pages/service_audit.html.twig', [
            'flaggedServices' => $this->buildFlaggedServices($services),
        ]);
    }

    #[Route('/services', name: 'admin_services')]
    public function services(Request $req): Response
    {
        $status = $req->query->get('status');
        if (!\is_string($status) || !\in_array($status, self::SERVICE_STATUSES, true)) {
            $status = null;
        }

        $qb = $this->em->getRepository(Service::class)->createQueryBuilder('s')
            ->leftJoin('s.user', 'u')->addSelect('u')
            ->leftJoin('s.category', 'c')->addSelect('c')
            ->leftJoin('s.reservations', 'r')->addSelect('r')
            ->orderBy('s.id', 'DESC');

        if ($status) {
            $qb->andWhere('s.status = :st')->setParameter('st', $status);
        }

        $services = $qb->getQuery()->getResult();

        return $this->render('admin/pages/services.html.twig', [
            'services' => $services,
            'serviceTrustScores' => $this->moderationTrust->evaluateServices($services),
            'filterStatus' => $status,
            'statusChoices' => self::SERVICE_STATUSES,
            'categories' => $this->em->getRepository(Categorie::class)->findAll(),
        ]);
    }

    #[Route('/services/{id}/status', name: 'admin_service_status', methods: ['POST'])]
    public function serviceStatus(Service $service, Request $req): Response
    {
        if (!$this->isCsrfTokenValid('admin_service_' . $service->getId(), $req->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToFilteredAdminServices($req);
        }

        $new = $req->request->get('status');
        if (!\is_string($new) || !\in_array($new, self::SERVICE_STATUSES, true)) {
            $this->addFlash('error', 'Statut invalide.');
            return $this->redirectToFilteredAdminServices($req);
        }

        $service->setStatus($new);
        $this->em->flush();
        $this->addFlash('success', 'Service Â« ' . $service->getTitle() . ' Â» : statut mis Ã  jour.');

        return $this->redirectToFilteredAdminServices($req);
    }

    #[Route('/services/create', name: 'admin_service_create', methods: ['POST'])]
    public function serviceCreate(Request $req): Response
    {
        if (!$this->isCsrfTokenValid('admin_service_create', $req->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_services');
        }

        $title = trim((string) $req->request->get('title', ''));
        $price = $req->request->get('price');

        if ($title === '' || $price === null || $price === '') {
            $this->addFlash('error', 'Le titre et le prix sont obligatoires.');
            return $this->redirectToRoute('admin_services', ['create' => 1]);
        }

        /** @var User $admin */
        $admin = $this->getUser();

        $service = new Service();
        $service->setTitle($title)
            ->setDescription($req->request->get('description') ? trim((string) $req->request->get('description')) : null)
            ->setPrice(number_format((float) $price, 2, '.', ''))
            ->setUser($admin);

        $rawStatus = $req->request->get('status', 'EN_ATTENTE');
        $service->setStatus(\in_array($rawStatus, self::SERVICE_STATUSES, true) ? $rawStatus : 'EN_ATTENTE');

        $catId = $req->request->getInt('category_id');
        if ($catId) {
            $category = $this->em->find(Categorie::class, $catId);
            if ($category) {
                $service->setCategory($category);
            }
        }

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads';
        $imageErr = $this->handleServiceImage($req->files->get('image'), $service, $uploadDir);
        if ($imageErr !== null) {
            $this->addFlash('error', $imageErr);
            return $this->redirectToRoute('admin_services', ['create' => 1]);
        }

        $this->em->persist($service);
        $this->em->flush();

        $this->addFlash('success', 'Service Â« ' . $service->getTitle() . ' Â» crÃ©Ã© avec succÃ¨s.');
        return $this->redirectToRoute('admin_services');
    }

    #[Route('/services/{id}/delete', name: 'admin_service_delete', methods: ['POST'])]
    public function serviceDelete(Service $service, Request $req): Response
    {
        if (!$this->isCsrfTokenValid('admin_svc_delete_' . $service->getId(), $req->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_services');
        }

        $title = $service->getTitle();
        $this->em->remove($service);
        $this->em->flush();

        $this->addFlash('success', 'Service Â« ' . $title . ' Â» supprimÃ©.');
        return $this->redirectToRoute('admin_services');
    }

    #[Route('/publications', name: 'admin_publications')]
    public function publications(Request $req): Response
    {
        $status = $req->query->get('status');
        if (!\is_string($status) || !\in_array($status, self::PUBLICATION_STATUSES, true)) {
            $status = null;
        }

        $qb = $this->publicationRepository->createBaseQueryBuilder()
            ->orderBy('p.createdAt', 'DESC');

        if ($status) {
            $qb->andWhere('p.status = :st')->setParameter('st', $status);
        }

        $publications = $qb->getQuery()->getResult();

        return $this->render('admin/pages/publications.html.twig', [
            'publications' => $publications,
            'publicationTrustScores' => $this->moderationTrust->evaluatePublications($publications),
            'filterStatus' => $status,
            'statusChoices' => self::PUBLICATION_STATUSES,
            'categories' => $this->em->getRepository(Categorie::class)->findAll(),
        ]);
    }

    #[Route('/publications/{id}/status', name: 'admin_publication_status', methods: ['POST'])]
    public function publicationStatus(Publication $pub, Request $req): Response
    {
        if (!$this->isCsrfTokenValid('admin_publication_' . $pub->getId(), $req->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToFilteredAdminPublications($req);
        }

        $new = $req->request->get('status');
        if (!\is_string($new) || !\in_array($new, self::PUBLICATION_STATUSES, true)) {
            $this->addFlash('error', 'Statut invalide.');
            return $this->redirectToFilteredAdminPublications($req);
        }

        $pub->setStatus($new);
        $pub->setUpdatedAt(new \DateTime());
        $this->em->flush();
        $this->addFlash('success', 'Publication Â« ' . $pub->getTitre() . ' Â» : statut mis Ã  jour.');

        return $this->redirectToFilteredAdminPublications($req);
    }

    #[Route('/publications/create', name: 'admin_publication_create', methods: ['POST'])]
    public function publicationCreate(Request $req): Response
    {
        if (!$this->isCsrfTokenValid('admin_publication_create', $req->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_publications');
        }

        $titre = trim((string) $req->request->get('titre', ''));
        $message = trim((string) $req->request->get('message', ''));

        if ($titre === '' || $message === '') {
            $this->addFlash('error', 'Le titre et la description sont obligatoires.');
            return $this->redirectToRoute('admin_publications', ['create' => 1]);
        }

        $type = $req->request->get('type_publication', 'VENTE_OBJET');
        if (!\in_array($type, self::PUBLICATION_TYPES, true)) {
            $type = 'VENTE_OBJET';
        }

        /** @var User $admin */
        $admin = $this->getUser();

        $pub = new Publication();
        $pub->setUser($admin)
            ->setTypePublication($type)
            ->setTitre($titre)
            ->setMessage($message)
            ->setLocalisation($req->request->get('localisation') ? trim((string) $req->request->get('localisation')) : null)
            ->setStatus('ACTIVE');

        $prix = $req->request->get('prix');
        if ($prix !== null && $prix !== '') {
            $prixFloat = (float) $prix;
            if ($prixFloat < 0) {
                $this->addFlash('error', 'Le prix doit Ãªtre positif.');
                return $this->redirectToRoute('admin_publications', ['create' => 1]);
            }
            if ($type === 'VENTE_OBJET') {
                $pub->setPrixVente(number_format($prixFloat, 2, '.', ''));
            } else {
                $pub->setProposedPrice(number_format($prixFloat, 2, '.', ''));
            }
        }

        $catId = $req->request->getInt('category_id');
        if ($catId) {
            $category = $this->em->find(Categorie::class, $catId);
            if ($category) {
                $pub->setCategory($category);
            }
        }

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads';
        $imageErr = $this->handlePublicationImage($req->files->get('image'), $pub, $uploadDir);
        if ($imageErr !== null) {
            $this->addFlash('error', $imageErr);
            return $this->redirectToRoute('admin_publications', ['create' => 1]);
        }

        $this->em->persist($pub);
        $this->em->flush();

        $this->addFlash('success', 'Publication Â« ' . $pub->getTitre() . ' Â» crÃ©Ã©e avec succÃ¨s.');
        return $this->redirectToRoute('admin_publications');
    }

    #[Route('/publications/{id}/delete', name: 'admin_publication_delete', methods: ['POST'])]
    public function publicationDelete(Publication $pub, Request $req): Response
    {
        if (!$this->isCsrfTokenValid('admin_pub_delete_' . $pub->getId(), $req->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_publications');
        }

        $titre = $pub->getTitre();
        $this->em->remove($pub);
        $this->em->flush();

        $this->addFlash('success', 'Publication Â« ' . $titre . ' Â» supprimÃ©e.');
        return $this->redirectToRoute('admin_publications');
    }


    #[Route('/home/service/{id}/delete', name: 'admin_home_service_delete', methods: ['POST'])]
    public function homeServiceDelete(Service $service, Request $req): Response
    {
        if (!$this->isCsrfTokenValid('admin_home_svc_delete_' . $service->getId(), $req->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_home');
        }

        $title = $service->getTitle();
        $this->em->remove($service);
        $this->em->flush();
        $this->addFlash('success', 'Service « ' . $title . ' » supprimÃ© avec succÃ¨s.');
        return $this->redirectToRoute('admin_home');
    }

    #[Route('/home/publication/{id}/delete', name: 'admin_home_publication_delete', methods: ['POST'])]
    public function homePublicationDelete(Publication $pub, Request $req): Response
    {
        if (!$this->isCsrfTokenValid('admin_home_pub_delete_' . $pub->getId(), $req->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_home');
        }

        $titre = $pub->getTitre();
        $this->em->remove($pub);
        $this->em->flush();
        $this->addFlash('success', 'Publication « ' . $titre . ' » supprimÃ©e avec succÃ¨s.');
        return $this->redirectToRoute('admin_home');
    }

    private function redirectToFilteredAdminServices(Request $req): Response
    {
        $f = $req->request->get('filter_status');
        return $this->redirectToRoute('admin_services', \is_string($f) && $f !== '' ? ['status' => $f] : []);
    }

    private function redirectToFilteredAdminPublications(Request $req): Response
    {
        $f = $req->request->get('filter_status');
        return $this->redirectToRoute('admin_publications', \is_string($f) && $f !== '' ? ['status' => $f] : []);
    }

    private function handleServiceImage($file, Service $service, string $uploadDir): ?string
    {
        if ($file === null) {
            return null;
        }
        if (!\in_array($file->getMimeType(), self::ALLOWED_MIME, true)) {
            return 'Format image invalide (JPEG, PNG, WebP, GIF uniquement).';
        }
        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            return "L'image dÃ©passe 5 Mo.";
        }
        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            return 'Impossible de crÃ©er le dossier uploads.';
        }
        $filename = bin2hex(random_bytes(16)) . '.' . $file->guessExtension();
        $file->move($uploadDir, $filename);
        $service->setImage('uploads/' . $filename);
        return null;
    }

    private function handlePublicationImage($file, Publication $pub, string $uploadDir): ?string
    {
        if ($file === null) {
            return null;
        }
        if (!\in_array($file->getMimeType(), self::ALLOWED_MIME, true)) {
            return 'Format image invalide (JPEG, PNG, WebP, GIF uniquement).';
        }
        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            return "L'image dÃ©passe 5 Mo.";
        }
        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            return 'Impossible de crÃ©er le dossier uploads.';
        }
        $filename = bin2hex(random_bytes(16)) . '.' . $file->guessExtension();
        $file->move($uploadDir, $filename);
        $pub->setImageUrl('uploads/' . $filename);
        return null;
    }

    /**
     * @param Service[] $services
     * @return array<int, array{service: Service, issues: array<int, string>}>
     */
    private function buildFlaggedServices(array $services): array
    {
        $rows = [];

        foreach ($services as $service) {
            $issues = [];
            $categoryName = mb_strtolower((string) ($service->getCategory()?->getName() ?? ''));
            $haystack = mb_strtolower(trim($service->getTitle() . ' ' . ($service->getDescription() ?? '')));
            $price = (float) $service->getPrice();

            if (!$service->getCategory()) {
                $issues[] = 'CatÃ©gorie manquante.';
            }
            if ($price <= 0) {
                $issues[] = 'Prix nul ou nÃ©gatif.';
            }
            if ($price > 500) {
                $issues[] = 'Prix trÃ¨s Ã©levÃ© pour un service Ã©tudiant (> 500 â‚¬).';
            }
            if ($price < 5) {
                $issues[] = 'Prix trÃ¨s faible, Ã  vÃ©rifier.';
            }
            if (mb_strlen(trim((string) $service->getDescription())) < 20) {
                $issues[] = 'Description trop courte ou incomplÃ¨te.';
            }
            if ($categoryName !== '') {
                $keywordMap = [
                    'langues' => ['franÃ§ais', 'anglais', 'espagnol', 'langue', 'conversation'],
                    'informatique' => ['python', 'java', 'programmation', 'code', 'web', 'symfony', 'javascript'],
                    'cours particuliers' => ['cours', 'tuteur', 'math', 'algÃ¨bre', 'physique', 'soutien'],
                ];

                foreach ($keywordMap as $categoryKeyword => $keywords) {
                    if (str_contains($categoryName, $categoryKeyword)) {
                        $matched = false;
                        foreach ($keywords as $keyword) {
                            if (str_contains($haystack, $keyword)) {
                                $matched = true;
                                break;
                            }
                        }
                        if (!$matched) {
                            $issues[] = 'Contenu potentiellement incohÃ©rent avec la catÃ©gorie sÃ©lectionnÃ©e.';
                        }
                        break;
                    }
                }
            }

            if ($issues !== []) {
                $rows[] = [
                    'service' => $service,
                    'issues' => array_values(array_unique($issues)),
                ];
            }
        }

        return $rows;
    }
}