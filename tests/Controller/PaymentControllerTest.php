<?php

namespace App\Tests\Entity;

use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\Reservation;
use PHPUnit\Framework\TestCase;

class PaymentControllerTest extends TestCase
{
    private function makePayment(): Payment
    {
        $payment = new Payment();
        $payment->setAmount('100.00');
        $payment->setMethod('VIRTUAL');
        $payment->setMeetingLat(36.8);
        $payment->setMeetingLng(10.1);
        $payment->setMeetingAddress('123 Test Street');
        return $payment;
    }

    // ── constructor ───────────────────────────────────────────────────────────

    public function testConstructorInitializesInvoicesCollection(): void
    {
        $payment = new Payment();
        $this->assertInstanceOf(\Doctrine\Common\Collections\Collection::class, $payment->getInvoices());
        $this->assertCount(0, $payment->getInvoices());
    }

    // ── getters / setters ─────────────────────────────────────────────────────

    public function testSetAndGetAmount(): void
    {
        $payment = new Payment();
        $payment->setAmount('250.99');
        $this->assertSame('250.99', $payment->getAmount());
    }

    public function testSetAndGetMethod(): void
    {
        $payment = new Payment();
        $payment->setMethod('PHYSICAL');
        $this->assertSame('PHYSICAL', $payment->getMethod());
    }

    public function testSetAndGetMeetingLat(): void
    {
        $payment = new Payment();
        $payment->setMeetingLat(48.8566);
        $this->assertSame(48.8566, $payment->getMeetingLat());
    }

    public function testSetAndGetMeetingLng(): void
    {
        $payment = new Payment();
        $payment->setMeetingLng(2.3522);
        $this->assertSame(2.3522, $payment->getMeetingLng());
    }

    public function testSetAndGetMeetingAddress(): void
    {
        $payment = new Payment();
        $payment->setMeetingAddress('456 Main Ave');
        $this->assertSame('456 Main Ave', $payment->getMeetingAddress());
    }

    public function testSetAndGetReservation(): void
    {
        $payment     = new Payment();
        $reservation = $this->createMock(Reservation::class);
        $payment->setReservation($reservation);
        $this->assertSame($reservation, $payment->getReservation());
    }

    public function testSetReservationToNull(): void
    {
        $payment     = new Payment();
        $reservation = $this->createMock(Reservation::class);
        $payment->setReservation($reservation);
        $payment->setReservation(null);
        $this->assertNull($payment->getReservation());
    }

    public function testGetIdIsNullByDefault(): void
    {
        $payment = new Payment();
        $this->assertNull($payment->getId());
    }

    // ── addInvoice ────────────────────────────────────────────────────────────

    public function testAddInvoiceAddsToCollection(): void
    {
        $payment = $this->makePayment();
        $invoice = $this->createMock(Invoice::class);
        $invoice->expects($this->once())->method('setPayment')->with($payment);

        $payment->addInvoice($invoice);

        $this->assertCount(1, $payment->getInvoices());
        $this->assertTrue($payment->getInvoices()->contains($invoice));
    }

    public function testAddInvoiceDoesNotAddDuplicate(): void
    {
        $payment = $this->makePayment();
        $invoice = $this->createMock(Invoice::class);

        // setPayment should only be called once even if addInvoice is called twice
        $invoice->expects($this->once())->method('setPayment')->with($payment);

        $payment->addInvoice($invoice);
        $payment->addInvoice($invoice); // duplicate — should be ignored

        $this->assertCount(1, $payment->getInvoices());
    }

    public function testAddMultipleInvoices(): void
    {
        $payment  = $this->makePayment();
        $invoice1 = $this->createMock(Invoice::class);
        $invoice2 = $this->createMock(Invoice::class);

        $invoice1->expects($this->once())->method('setPayment')->with($payment);
        $invoice2->expects($this->once())->method('setPayment')->with($payment);

        $payment->addInvoice($invoice1);
        $payment->addInvoice($invoice2);

        $this->assertCount(2, $payment->getInvoices());
    }

    // ── removeInvoice ─────────────────────────────────────────────────────────

    public function testRemoveInvoiceUnlinksPayment(): void
    {
        $payment = $this->makePayment();
        $invoice = $this->createMock(Invoice::class);

        // Needed so addInvoice doesn't blow up
        $invoice->method('setPayment')->with($this->logicalOr(
            $this->equalTo($payment),
            $this->isNull()
        ));

        // getPayment() must return $payment so the unlink branch is triggered
        $invoice->method('getPayment')->willReturn($payment);

        $payment->addInvoice($invoice);
        $payment->removeInvoice($invoice);

        $this->assertCount(0, $payment->getInvoices());
    }

    public function testRemoveInvoiceNotInCollectionDoesNothing(): void
    {
        $payment = $this->makePayment();
        $invoice = $this->createMock(Invoice::class);

        // invoice was never added — removing it should be a no-op
        $invoice->expects($this->never())->method('setPayment');

        $payment->removeInvoice($invoice);

        $this->assertCount(0, $payment->getInvoices());
    }

    public function testRemoveInvoiceDoesNotUnlinkIfPaymentDiffers(): void
    {
        $payment      = $this->makePayment();
        $otherPayment = $this->makePayment();
        $invoice      = $this->createMock(Invoice::class);

        $invoice->method('setPayment')->with($payment); // only from addInvoice
        // getPayment returns a DIFFERENT payment → unlink branch should NOT fire
        $invoice->method('getPayment')->willReturn($otherPayment);

        $payment->addInvoice($invoice);

        // setPayment(null) must NOT be called
        $invoice->expects($this->never())->method('setPayment')->with(null);

        $payment->removeInvoice($invoice);
    }

    // ── method values ─────────────────────────────────────────────────────────

    public function testMethodCanBePhysical(): void
    {
        $payment = new Payment();
        $payment->setMethod('PHYSICAL');
        $this->assertSame('PHYSICAL', $payment->getMethod());
    }

    public function testMethodCanBeVirtual(): void
    {
        $payment = new Payment();
        $payment->setMethod('VIRTUAL');
        $this->assertSame('VIRTUAL', $payment->getMethod());
    }

    // ── fluent interface ──────────────────────────────────────────────────────

    public function testSettersReturnSelf(): void
    {
        $payment = new Payment();
        $this->assertSame($payment, $payment->setAmount('10.00'));
        $this->assertSame($payment, $payment->setMethod('VIRTUAL'));
        $this->assertSame($payment, $payment->setMeetingLat(0.0));
        $this->assertSame($payment, $payment->setMeetingLng(0.0));
        $this->assertSame($payment, $payment->setMeetingAddress('test'));
        $this->assertSame($payment, $payment->setReservation(null));
    }
}