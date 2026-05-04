<?php

namespace App\Tests\Controller\Etudiant;

use App\Controller\Etudiant\InvoiceController;
use App\Entity\Invoice;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class InvoiceControllerTest extends TestCase
{
    // ── build a fully wired container mock ────────────────────────────────────

    private function buildContainer(User $loggedInUser, array $extras = []): ContainerInterface
    {
        // Token / token storage
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($loggedInUser);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        // Authorization checker — always returns true (we test ownership logic, not roles)
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(true);

        // Router
        $router = $extras['router'] ?? $this->createMock(RouterInterface::class);
        /** @var RouterInterface $router */
        $router->method('generate')->willReturn('/invoices');

        // Flash bag + session + request stack
        $flashBag = $this->createMock(FlashBagInterface::class);
        $session  = $this->createMock(Session::class);
        $session->method('getFlashBag')->willReturn($flashBag);

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getSession')->willReturn($session);

        $services = [
            'security.token_storage'        => $tokenStorage,
            'security.authorization_checker' => $authChecker,
            'router'                         => $router,
            'request_stack'                  => $requestStack,
        ];

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn($id) => isset($services[$id]));
        $container->method('get')->willReturnCallback(fn($id) => $services[$id] ?? null);

        return $container;
    }

    private function injectContainer(InvoiceController $controller, ContainerInterface $container): void
    {
        $prop = (new \ReflectionClass($controller))->getProperty('container');
        $prop->setAccessible(true);
        $prop->setValue($controller, $container);
    }

    private function makeController(User $user): InvoiceController
    {
        $controller = new InvoiceController();
        $this->injectContainer($controller, $this->buildContainer($user));
        return $controller;
    }

    private function makeUser(): User
    {
        $user = $this->createMock(User::class);
        $user->method('getUserIdentifier')->willReturn(uniqid('user_') . '@test.com');
        return $user;
    }

    private function makeInvoice(User $owner): Invoice
    {
        $invoice = $this->createMock(Invoice::class);
        $invoice->method('getId')->willReturn(1);
        $invoice->method('getUser')->willReturn($owner);
        return $invoice;
    }

    // ── tests ─────────────────────────────────────────────────────────────────

    public function testDeleteThrowsAccessDeniedIfNotOwner(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();

        $invoice = $this->makeInvoice($owner);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('remove');
        $em->expects($this->never())->method('flush');

        $controller = $this->makeController($other); // logged in as someone else

        $this->expectException(AccessDeniedException::class);

        $controller->delete($invoice, $em);
    }

    public function testDeleteSucceedsIfOwner(): void
    {
        $owner   = $this->makeUser();
        $invoice = $this->makeInvoice($owner);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('remove')->with($invoice);
        $em->expects($this->once())->method('flush');

        $controller = $this->makeController($owner);

        $response = $controller->delete($invoice, $em);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(302, $response->getStatusCode());
    }

    public function testPreviewThrowsAccessDeniedIfNotOwner(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();

        $invoice = $this->makeInvoice($owner);

        $controller = $this->makeController($other);

        $this->expectException(AccessDeniedException::class);

        $controller->preview($invoice);
    }
}