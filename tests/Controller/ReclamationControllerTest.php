<?php

namespace App\Tests\Controller;

use App\Controller\ReclamationController;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ReclamationControllerTest extends TestCase
{
    private function makeController(?User $loggedUser): ReclamationController
    {
        $ctrl = $this->getMockBuilder(ReclamationController::class)
            ->onlyMethods(['getUser', 'render', 'redirectToRoute', 'createAccessDeniedException'])
            ->getMock();

        $ctrl->method('getUser')->willReturn($loggedUser);
        $ctrl->method('render')->willReturn(new Response('<form></form>'));
        $ctrl->method('redirectToRoute')->willReturn(new RedirectResponse('/messages'));
        $ctrl->method('createAccessDeniedException')
             ->willReturn(new AccessDeniedException('Accès refusé'));

        return $ctrl;
    }

    public function testGetRequestShowsForm(): void
    {
        $user  = $this->createMock(User::class);
        $cible = $this->createMock(User::class);
        $ctrl  = $this->makeController($user);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $response = $ctrl->new($cible, new Request(), $em);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testNotLoggedInThrowsAccessDenied(): void
    {
        $ctrl  = $this->makeController(null);
        $cible = $this->createMock(User::class);
        $em    = $this->createMock(EntityManagerInterface::class);

        $this->expectException(AccessDeniedException::class);

        $ctrl->new($cible, new Request(), $em);
    }

    public function testPostSavesReclamationAndRedirects(): void
    {
        $user  = $this->createMock(User::class);
        $cible = $this->createMock(User::class);
        $ctrl  = $this->makeController($user);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $request = new Request(
            [],
            ['type' => 'SPAM', 'sujet' => 'Test sujet', 'description' => 'Description'],
            [], [], [],
            ['REQUEST_METHOD' => 'POST']
        );

        $response = $ctrl->new($cible, $request, $em);
        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    // ✅ TEST CORRIGÉ - (string) cast → null devient "" → pas de TypeError
    public function testPostWithMissingTypeSavesWithEmptyString(): void
    {
        $user  = $this->createMock(User::class);
        $cible = $this->createMock(User::class);
        $ctrl  = $this->makeController($user);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $request = new Request(
            [],
            ['sujet' => 'Test', 'description' => 'Desc'], // type manquant
            [], [], [],
            ['REQUEST_METHOD' => 'POST']
        );

        $response = $ctrl->new($cible, $request, $em);

        // ✅ Redirige normalement sans erreur
        $this->assertInstanceOf(RedirectResponse::class, $response);
    }
}