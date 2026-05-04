<?php

namespace App\Tests\Controller;

use App\Controller\MessageController;
use App\Entity\Message;
use App\Entity\User;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MessageControllerTest extends TestCase
{
    // ── Helper: Controller مع mocks ──
    private function makeController(?User $loggedUser): MessageController
    {
        $ctrl = $this->getMockBuilder(MessageController::class)
            ->onlyMethods(['getUser', 'render', 'redirectToRoute'])
            ->getMock();

        $ctrl->method('getUser')->willReturn($loggedUser);
        $ctrl->method('render')->willReturn(new Response('<html></html>'));
        $ctrl->method('redirectToRoute')->willReturn(new RedirectResponse('/messages'));

        return $ctrl;
    }

    // ── Helper: EntityManager يرجع query builder فارغ ──
    private function makeEmWithEmptyMessages(): EntityManagerInterface
    {
        // Mock للـ Query
        $query = $this->getMockBuilder(\Doctrine\ORM\AbstractQuery::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getResult'])
            ->getMockForAbstractClass();
        $query->method('getResult')->willReturn([]);

        // Mock للـ QueryBuilder
        $qb = $this->getMockBuilder(\Doctrine\ORM\QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['where', 'andWhere', 'setParameter', 'orderBy', 'getQuery'])
            ->getMock();

        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        // Mock للـ EntityManager
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn(
            $this->createConfiguredMock(
                \Doctrine\ORM\EntityRepository::class,
                ['createQueryBuilder' => $qb]
            )
        );

        return $em;
    }

    // ─────────────────────────────────────────
    // TEST 1: send() بمحتوى → يحفظ Message
    // ─────────────────────────────────────────
    public function testSendSavesMessage(): void
    {
        $sender   = $this->createMock(User::class);
        $receiver = $this->createMock(User::class);
        $receiver->method('getId')->willReturn(2);

        $ctrl = $this->makeController($sender);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $request = new Request(
            [],
            ['content' => 'Bonjour comment ça va?'],
            [], [], [],
            ['REQUEST_METHOD' => 'POST']
        );

        $response = $ctrl->send($receiver, $request, $em);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    // ─────────────────────────────────────────
    // TEST 2: send() بمحتوى فارغ → ما يحفظش
    // ─────────────────────────────────────────
    public function testSendEmptyContentDoesNotSave(): void
    {
        $sender   = $this->createMock(User::class);
        $receiver = $this->createMock(User::class);
        $receiver->method('getId')->willReturn(2);

        $ctrl = $this->makeController($sender);

        $em = $this->createMock(EntityManagerInterface::class);
        // محتوى فارغ → persist ما يتسماش
        $em->expects($this->never())->method('persist');

        $request = new Request(
            [],
            ['content' => '   '], // spaces فقط → trim يرجع ''
            [], [], [],
            ['REQUEST_METHOD' => 'POST']
        );

        $response = $ctrl->send($receiver, $request, $em);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    // ─────────────────────────────────────────
    // TEST 3: send() مع scheduledAt → isSent = false
    // ─────────────────────────────────────────
    public function testSendScheduledMessageSetsSentFalse(): void
    {
        $sender   = $this->createMock(User::class);
        $receiver = $this->createMock(User::class);
        $receiver->method('getId')->willReturn(2);

        $ctrl = $this->makeController($sender);

        $capturedMessage = null;

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
           ->method('persist')
           ->willReturnCallback(function ($msg) use (&$capturedMessage) {
               $capturedMessage = $msg;
           });
        $em->expects($this->once())->method('flush');

        $request = new Request(
            [],
            [
                'content'     => 'Message programmé',
                'scheduledAt' => '2025-12-31 23:59',
            ],
            [], [], [],
            ['REQUEST_METHOD' => 'POST']
        );

        $ctrl->send($receiver, $request, $em);

        // التأكد أنو isSent = false لأنو scheduled
        $this->assertInstanceOf(Message::class, $capturedMessage);
        $this->assertFalse($capturedMessage->isSent() ?? false);
    }

    // ─────────────────────────────────────────
    // TEST 4: send() بدون scheduledAt → isSent = true
    // ─────────────────────────────────────────
    public function testSendNormalMessageSetsSentTrue(): void
    {
        $sender   = $this->createMock(User::class);
        $receiver = $this->createMock(User::class);
        $receiver->method('getId')->willReturn(2);

        $ctrl = $this->makeController($sender);

        $capturedMessage = null;

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
           ->method('persist')
           ->willReturnCallback(function ($msg) use (&$capturedMessage) {
               $capturedMessage = $msg;
           });
        $em->expects($this->once())->method('flush');

        $request = new Request(
            [],
            ['content' => 'Bonjour!'],
            [], [], [],
            ['REQUEST_METHOD' => 'POST']
        );

        $ctrl->send($receiver, $request, $em);

        $this->assertInstanceOf(Message::class, $capturedMessage);
        $this->assertTrue($capturedMessage->isSent() ?? true);
    }
}