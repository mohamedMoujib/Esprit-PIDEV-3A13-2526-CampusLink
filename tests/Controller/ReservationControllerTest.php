<?php

namespace App\Tests\Controller;

use App\Controller\Etudiant\ReservationController;
use App\Entity\Reservation;
use App\Entity\Service;
use App\Entity\User;
use App\Repository\ServiceRepository;
use App\Service\NotificationService;
use App\Service\SmsService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Twilio\Rest\Client;

class ReservationControllerTest extends TestCase
{
    // ── Helper: يبني controller مع كل الـ mocks ──
private function makeController(?User $loggedUser): ReservationController
{
    $ctrl = $this->getMockBuilder(ReservationController::class)
        ->onlyMethods([
            'getUser',
            'redirectToRoute',
            'addFlash',              // ← نضيفوها باش نمنعوها من الـ flash session
            'denyAccessUnlessGranted',
            'createAccessDeniedException',
        ])
        ->getMock();

    $ctrl->method('getUser')->willReturn($loggedUser);
    $ctrl->method('redirectToRoute')->willReturn(new RedirectResponse('/etudiant/reservations'));

    // ✅ addFlash هي void → ما تستعملش willReturn
    $ctrl->method('addFlash'); // بس هكا، بدون willReturn

    $ctrl->method('denyAccessUnlessGranted'); // void كذلك

    $ctrl->method('createAccessDeniedException')
         ->willReturn(new \Symfony\Component\Security\Core\Exception\AccessDeniedException());

    return $ctrl;
}

    // ── Helper: يبني SmsService مع mock Twilio ──
    private function makeSms(): SmsService
    {
        $mockMessageInstance = $this->createMock(\Twilio\Rest\Api\V2010\Account\MessageInstance::class);
        
        $mockMessages = $this->createMock(\Twilio\Rest\Api\V2010\Account\MessageList::class);
        $mockMessages->method('create')->willReturn($mockMessageInstance);
        
        $mockClient = $this->createMock(Client::class);
        $mockClient->messages = $mockMessages;
        
        return new SmsService('fake', 'fake', '+123', $mockClient);
    }

    // ── Helper: يبني Service entity جاهز للاختبار ──
    private function makeService(string $price = '50.00'): Service
    {
        $prestataire = $this->createMock(User::class);
        $prestataire->method('getPhone')->willReturn('+21655000000');
        $prestataire->method('getName')->willReturn('Prestataire Test');

        $service = $this->createMock(Service::class);
        $service->method('getTitle')->willReturn('Coiffure');
        $service->method('getPrice')->willReturn($price);
        $service->method('getUser')->willReturn($prestataire);

        return $service;
    }

    // ─────────────────────────────────────────
    // TEST 1: new() بـ service_id و date صحيحين → يحفظ Reservation
    // ─────────────────────────────────────────
   public function testNewReservationSavesAndRedirects(): void
{
    $etudiant = $this->createMock(User::class);
    $etudiant->method('getName')->willReturn('Ali');

    $ctrl    = $this->makeController($etudiant);
    $service = $this->makeService();

    $serviceRepo = $this->createMock(ServiceRepository::class);
    $serviceRepo->method('find')->willReturn($service);

    $em = $this->createMock(EntityManagerInterface::class);
    $em->expects($this->once())->method('persist');
    $em->expects($this->once())->method('flush');

    $notif = $this->createMock(NotificationService::class);
    // ✅ بدون willReturn لأنها void
    $notif->method('notifyInApp');

    $request = new Request(
        [],
        ['service_id' => '1', 'date' => '2025-12-01 10:00'],
        [], [], [],
        ['REQUEST_METHOD' => 'POST']
    );

    $response = $ctrl->new($request, $em, $serviceRepo, $this->makeSms(), $notif);

    $this->assertInstanceOf(RedirectResponse::class, $response);
}

    // ─────────────────────────────────────────
    // TEST 2: new() بدون service → ما يحفظش ويرجع redirect
    // ─────────────────────────────────────────
    public function testNewWithInvalidServiceRedirects(): void
    {
        $etudiant = $this->createMock(User::class);
        $ctrl     = $this->makeController($etudiant);

        // ServiceRepository يرجع null → service مش موجود
        $serviceRepo = $this->createMock(ServiceRepository::class);
        $serviceRepo->method('find')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        // persist ما يتسماش لأن service مش موجود
        $em->expects($this->never())->method('persist');

        $notif   = $this->createMock(NotificationService::class);
        $request = new Request(
            [],
            ['service_id' => '999', 'date' => '2025-12-01'],
            [], [], [],
            ['REQUEST_METHOD' => 'POST']
        );

        $response = $ctrl->new($request, $em, $serviceRepo, $this->makeSms(), $notif);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    // ─────────────────────────────────────────
    // TEST 3: cancel() على CONFIRMED → ما يلغيش
    // ─────────────────────────────────────────
    public function testCancelConfirmedReservationRedirects(): void
    {
        $etudiant = $this->createMock(User::class);
        $ctrl     = $this->makeController($etudiant);

        $reservation = $this->createMock(Reservation::class);
        $reservation->method('getUser')->willReturn($etudiant);
        $reservation->method('getStatus')->willReturn('CONFIRMED'); // ← مؤكدة

        $em = $this->createMock(EntityManagerInterface::class);
        // flush ما يتسماش لأن ما قدرناش نلغيوها
        $em->expects($this->never())->method('flush');

        $response = $ctrl->cancel($reservation, $em, $this->makeSms());

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    // ─────────────────────────────────────────
    // TEST 4: cancel() على PENDING → يلغي ويحفظ
    // ─────────────────────────────────────────
    public function testCancelPendingReservationSetsStatusCancelled(): void
    {
        $etudiant = $this->createMock(User::class);
        $etudiant->method('getName')->willReturn('Ali');
        $ctrl = $this->makeController($etudiant);

        $service = $this->makeService();

        $reservation = $this->createMock(Reservation::class);
        $reservation->method('getUser')->willReturn($etudiant);
        $reservation->method('getStatus')->willReturn('PENDING');
        $reservation->method('getService')->willReturn($service);

        // التأكد أنو setStatus('CANCELLED') يتسمى
        $reservation->expects($this->once())
                    ->method('setStatus')
                    ->with('CANCELLED');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $response = $ctrl->cancel($reservation, $em, $this->makeSms());

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    // ─────────────────────────────────────────
    // TEST 5: edit() على PENDING → يغير التاريخ
    // ─────────────────────────────────────────
    public function testEditPendingReservationUpdatesDate(): void
    {
        $etudiant = $this->createMock(User::class);
        $ctrl     = $this->makeController($etudiant);

        $reservation = $this->createMock(Reservation::class);
        $reservation->method('getUser')->willReturn($etudiant);
        $reservation->method('getStatus')->willReturn('PENDING');

        // setDate() لازم يتسمى مرة وحدة
        $reservation->expects($this->once())->method('setDate');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $request = new Request(
            [],
            ['date' => '2025-12-15 14:00'],
            [], [], [],
            ['REQUEST_METHOD' => 'POST']
        );

        $response = $ctrl->edit($reservation, $request, $em);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    // ─────────────────────────────────────────
    // TEST 6: delete() على CONFIRMED → ما يمسحش
    // ─────────────────────────────────────────
    public function testDeleteConfirmedReservationIsBlocked(): void
    {
        $etudiant = $this->createMock(User::class);
        $ctrl     = $this->makeController($etudiant);

        $reservation = $this->createMock(Reservation::class);
        $reservation->method('getUser')->willReturn($etudiant);
        $reservation->method('getStatus')->willReturn('CONFIRMED');

        $em = $this->createMock(EntityManagerInterface::class);
        // remove ما يتسماش
        $em->expects($this->never())->method('remove');

        $response = $ctrl->delete($reservation, $em);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    // ─────────────────────────────────────────
    // TEST 7: delete() على PENDING → يمسح
    // ─────────────────────────────────────────
    public function testDeletePendingReservationRemovesIt(): void
    {
        $etudiant = $this->createMock(User::class);
        $ctrl     = $this->makeController($etudiant);

        $reservation = $this->createMock(Reservation::class);
        $reservation->method('getUser')->willReturn($etudiant);
        $reservation->method('getStatus')->willReturn('PENDING');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('remove')->with($reservation);
        $em->expects($this->once())->method('flush');

        $response = $ctrl->delete($reservation, $em);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }
}