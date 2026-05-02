<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\ServiceRepository;
use App\Repository\UserRepository;
use App\Service\NotificationService;
use App\Service\TrendPredictionService;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Smoke / intégration : API notifications, tendances, parcours minimaux.
 * Nécessite une base accessible (DATABASE_URL) avec au moins un utilisateur par rôle testé.
 */
class CampusLinkSmokeTest extends WebTestCase
{
    public function testNotificationsApiForbiddenWhenAnonymous(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/notifications/count');

        self::assertTrue(
            $client->getResponse()->isRedirect() || $client->getResponse()->getStatusCode() === 401,
            'Les notifications API doivent exiger une authentification (302 login ou 401).'
        );
    }

    public function testNotificationsCountWithSession(): void
    {
        $client = static::createClient();
        $this->skipIfNotificationsTableMissing();
        $user = $this->findAnyUser(['ETUDIANT', 'PRESTATAIRE', 'ADMIN']);
        if ($user === null) {
            self::markTestSkipped('Aucun utilisateur en base pour tester la session.');

            return;
        }

        $client->loginUser($user);
        $client->request('GET', '/api/notifications/count');

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('count', $data);
        self::assertIsInt($data['count']);
    }

    public function testNotificationsListReadAndReadAll(): void
    {
        $client = static::createClient();
        $this->skipIfNotificationsTableMissing();
        $user = $this->findAnyUser(['ETUDIANT', 'PRESTATAIRE', 'ADMIN']);
        if ($user === null) {
            self::markTestSkipped('Aucun utilisateur en base.');

            return;
        }

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $notif = (new Notification())
            ->setUser($user)
            ->setTitle('Test smoke CampusLink')
            ->setMessage('Message de test pour marquer comme lu.')
            ->setStatus('UNREAD');
        $em->persist($notif);
        $em->flush();
        $id = (int) $notif->getId();

        $client->loginUser($user);
        $client->request('GET', '/api/notifications');
        self::assertResponseIsSuccessful();
        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('notifications', $payload);
        self::assertNotEmpty($payload['notifications']);

        $client->request('POST', '/api/notifications/'.$id.'/read');
        self::assertResponseIsSuccessful();

        $client->request('POST', '/api/notifications/read-all');
        self::assertResponseIsSuccessful();

        $toRemove = $em->find(Notification::class, $id);
        if ($toRemove instanceof Notification) {
            $em->remove($toRemove);
            $em->flush();
        }
    }

    public function testNotificationServiceInAppPersists(): void
    {
        self::bootKernel();
        $this->skipIfNotificationsTableMissing();
        $user = static::getContainer()->get(UserRepository::class)->findOneBy([]);
        if (!$user instanceof User) {
            self::markTestSkipped('Aucun utilisateur en base.');

            return;
        }

        /** @var NotificationService $svc */
        $svc = static::getContainer()->get(NotificationService::class);
        $before = $svc->getUnreadCount($user);
        $svc->notifyInApp($user, 'Smoke parcours', 'Vérification NotificationService->notifyInApp');
        $after = $svc->getUnreadCount($user);
        self::assertGreaterThan($before, $after);
    }

    public function testTrendPredictionWithoutApiKeyReturnsStructuredArray(): void
    {
        self::bootKernel();
        /** @var TrendPredictionService $trends */
        $trends = static::getContainer()->get(TrendPredictionService::class);
        $rows = $trends->predictTrendingCategories(true);
        self::assertIsArray($rows);
        foreach ($rows as $row) {
            self::assertArrayHasKey('category', $row);
            self::assertArrayHasKey('score', $row);
            self::assertArrayHasKey('trend', $row);
            self::assertArrayHasKey('emoji', $row);
        }
        $stats = $trends->getSimpleStats();
        self::assertArrayHasKey('byCategory', $stats);
    }

    public function testHuggingFaceLiveWhenApiKeyPresent(): void
    {
        $key = trim((string) ($_ENV['HUGGINGFACE_API_KEY'] ?? getenv('HUGGINGFACE_API_KEY') ?: ''));
        if ($key === '') {
            self::markTestSkipped('HUGGINGFACE_API_KEY non défini : skip appel réel Hugging Face.');

            return;
        }

        self::bootKernel();
        /** @var TrendPredictionService $trends */
        $trends = static::getContainer()->get(TrendPredictionService::class);
        $rows = $trends->predictTrendingCategories(true);
        self::assertIsArray($rows);
    }

    public function testHttpLoginAndNotificationsWithEnvCredentials(): void
    {
        $email = trim((string) ($_ENV['SMOKE_LOGIN_EMAIL'] ?? getenv('SMOKE_LOGIN_EMAIL') ?: ''));
        $password = (string) ($_ENV['SMOKE_LOGIN_PASSWORD'] ?? getenv('SMOKE_LOGIN_PASSWORD') ?: '');
        $userType = strtoupper((string) ($_ENV['SMOKE_LOGIN_USER_TYPE'] ?? getenv('SMOKE_LOGIN_USER_TYPE') ?: 'ETUDIANT'));

        if ($email === '' || $password === '') {
            self::markTestSkipped('Définissez SMOKE_LOGIN_EMAIL et SMOKE_LOGIN_PASSWORD pour ce test HTTP.');

            return;
        }

        $client = static::createClient();
        $this->skipIfNotificationsTableMissing();
        $crawler = $client->request('GET', '/login');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => $email,
            '_password' => $password,
            'userType' => $userType,
        ]);
        $client->submit($form);

        if (!$client->getResponse()->isRedirect()) {
            self::fail('Connexion HTTP échouée (pas de redirection). Vérifiez SMOKE_* et le type de compte.');
        }

        $client->followRedirect();
        $client->request('GET', '/api/notifications/count');
        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('count', $data);
    }

    private function skipIfNotificationsTableMissing(): void
    {
        try {
            $conn = static::getContainer()->get('doctrine.dbal.default_connection');
            $conn->executeQuery('SELECT 1 FROM notifications LIMIT 1');
        } catch (DbalException | \Throwable $e) {
            self::markTestSkipped(
                'Table `notifications` absente : importez migrations/manual/notifications_table.sql puis relancez les tests.'
            );
        }
    }

    public function testPublicationDemandeServiceCreatesInAppNotification(): void
    {
        $client = static::createClient();
        $this->skipIfNotificationsTableMissing();
        $user = $this->findAnyUser(['ETUDIANT']);
        if ($user === null) {
            self::markTestSkipped('Aucun utilisateur ETUDIANT en base.');

            return;
        }

        $before = static::getContainer()->get(NotificationService::class)->getUnreadCount($user);

        $userId = $user->getId();
        $client->loginUser($user);
        $crawler = $client->request('GET', '/publications/create');
        if ($client->getResponse()->getStatusCode() >= 400) {
            self::markTestSkipped('Impossible d’accéder à /publications/create.');

            return;
        }

        $tokenNode = $crawler->filter('input[name="_token"]')->first();
        if ($tokenNode->count() === 0) {
            self::markTestSkipped('Jeton CSRF introuvable sur le formulaire publication.');

            return;
        }
        $token = $tokenNode->attr('value');
        $client->request('POST', '/publications/create', [
            '_token' => $token,
            'titre' => 'Smoke test demande '.uniqid(),
            'message' => 'Description smoke test publication demande service.',
            'type_publication' => 'DEMANDE_SERVICE',
            'localisation' => '',
        ]);

        self::assertResponseRedirects();
        static::getContainer()->get(EntityManagerInterface::class)->clear();
        $userFresh = static::getContainer()->get(UserRepository::class)->find($userId);
        self::assertInstanceOf(User::class, $userFresh);
        $after = static::getContainer()->get(NotificationService::class)->getUnreadCount($userFresh);
        self::assertGreaterThan($before, $after, 'Une notification in-app devrait être créée pour une DEMANDE_SERVICE.');
    }

    public function testReservationNewNotifiesPrestataireAndStudent(): void
    {
        $client = static::createClient();
        $this->skipIfNotificationsTableMissing();

        $etudiant = $this->findAnyUser(['ETUDIANT']);
        $service = static::getContainer()->get(ServiceRepository::class)->findOneBy(['status' => 'CONFIRMEE']);
        if (!$etudiant instanceof User || $service === null) {
            self::markTestSkipped('Besoin d’un ETUDIANT et d’au moins un service CONFIRMEE.');

            return;
        }

        $prestataire = $service->getUser();
        if (!$prestataire instanceof User) {
            self::markTestSkipped('Service sans prestataire.');

            return;
        }

        $beforeP = static::getContainer()->get(NotificationService::class)->getUnreadCount($prestataire);
        $beforeE = static::getContainer()->get(NotificationService::class)->getUnreadCount($etudiant);

        $client->loginUser($etudiant);
        $client->request('POST', '/etudiant/reservations/new', [
            'service_id' => (string) $service->getId(),
            'date' => (new \DateTime('+7 days'))->format('Y-m-d H:i:s'),
        ]);

        self::assertResponseRedirects();
        static::getContainer()->get(EntityManagerInterface::class)->clear();

        $prestataireFresh = static::getContainer()->get(UserRepository::class)->find($prestataire->getId());
        $etudiantFresh = static::getContainer()->get(UserRepository::class)->find($etudiant->getId());
        self::assertInstanceOf(User::class, $prestataireFresh);
        self::assertInstanceOf(User::class, $etudiantFresh);

        $svc = static::getContainer()->get(NotificationService::class);
        self::assertGreaterThan($beforeP, $svc->getUnreadCount($prestataireFresh), 'Le prestataire devrait recevoir une notification.');
        self::assertGreaterThan($beforeE, $svc->getUnreadCount($etudiantFresh), 'L’étudiant devrait recevoir une notification.');
    }

    public function testPrestataireNewServiceNotifiesEachAdmin(): void
    {
        $client = static::createClient();
        $this->skipIfNotificationsTableMissing();

        $prestataire = $this->findAnyUser(['PRESTATAIRE']);
        $admins = static::getContainer()->get(UserRepository::class)->findBy(['userType' => 'ADMIN']);
        if (!$prestataire instanceof User || $admins === []) {
            self::markTestSkipped('Besoin d’un PRESTATAIRE et d’au moins un ADMIN.');

            return;
        }

        $beforeByAdmin = [];
        $svc = static::getContainer()->get(NotificationService::class);
        foreach ($admins as $admin) {
            $beforeByAdmin[$admin->getId()] = $svc->getUnreadCount($admin);
        }

        $client->loginUser($prestataire);
        $crawler = $client->request('GET', '/prestataire/services/new');
        self::assertResponseIsSuccessful();

        $uniq = uniqid('SmokeSvc');
        $form = $crawler->selectButton('Enregistrer le service')->form([
            'service[title]' => 'Service smoke '.$uniq,
            'service[description]' => 'Description smoke test',
            'service[price]' => '10.00',
        ]);
        $client->submit($form);

        self::assertResponseRedirects();
        static::getContainer()->get(EntityManagerInterface::class)->clear();

        foreach ($admins as $admin) {
            $fresh = static::getContainer()->get(UserRepository::class)->find($admin->getId());
            self::assertInstanceOf(User::class, $fresh);
            self::assertGreaterThan(
                $beforeByAdmin[$admin->getId()],
                static::getContainer()->get(NotificationService::class)->getUnreadCount($fresh),
                'Chaque admin devrait recevoir une notification de nouveau service.'
            );
        }
    }

    private function findAnyUser(array $types): ?User
    {
        /** @var UserRepository $repo */
        $repo = static::getContainer()->get(UserRepository::class);
        foreach ($types as $type) {
            $u = $repo->findOneBy(['userType' => $type]);
            if ($u instanceof User) {
                return $u;
            }
        }

        return null;
    }
}
