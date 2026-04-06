<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\PaymentRepository;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\Table(name: 'payments')]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    // ❌ REMOVE setId()

    #[ORM\ManyToOne(targetEntity: Reservation::class, inversedBy: 'payments')]
    #[ORM\JoinColumn(name: 'reservation_id', nullable: false, onDelete: "CASCADE")]
    private ?Reservation $reservation = null;

    public function getReservation(): ?Reservation
    {
        return $this->reservation;
    }

    public function setReservation(?Reservation $reservation): self
    {
        $this->reservation = $reservation;
        return $this;
    }

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $amount;

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    // 🔥 ENUM FIX
    #[ORM\Column(columnDefinition: "ENUM('PHYSICAL','VIRTUAL')")]
    private string $method;

    public function getMethod(): string
    {
        return $this->method;
    }

    public function setMethod(string $method): self
    {
        $this->method = $method;
        return $this;
    }

    #[ORM\Column(name: 'meeting_lat', type: 'float')]
    private float $meetingLat;

    public function getMeetingLat(): float
    {
        return $this->meetingLat;
    }

    public function setMeetingLat(float $meetingLat): self
    {
        $this->meetingLat = $meetingLat;
        return $this;
    }

    #[ORM\Column(name: 'meeting_lng', type: 'float')]
    private float $meetingLng;

    public function getMeetingLng(): float
    {
        return $this->meetingLng;
    }

    public function setMeetingLng(float $meetingLng): self
    {
        $this->meetingLng = $meetingLng;
        return $this;
    }

    #[ORM\Column(name: 'meeting_address', type: 'string', length: 255)]
    private string $meetingAddress;

    public function getMeetingAddress(): string
    {
        return $this->meetingAddress;
    }

    public function setMeetingAddress(string $meetingAddress): self
    {
        $this->meetingAddress = $meetingAddress;
        return $this;
    }

    #[ORM\OneToMany(mappedBy: 'payment', targetEntity: Invoice::class)]
    private Collection $invoices;

    public function __construct()
    {
        $this->invoices = new ArrayCollection();
    }

    public function getInvoices(): Collection
    {
        return $this->invoices;
    }

    public function addInvoice(Invoice $invoice): self
    {
        if (!$this->invoices->contains($invoice)) {
            $this->invoices[] = $invoice;
            $invoice->setPayment($this); // 🔥 important
        }

        return $this;
    }

    public function removeInvoice(Invoice $invoice): self
    {
        if ($this->invoices->removeElement($invoice)) {
            if ($invoice->getPayment() === $this) {
                $invoice->setPayment(null);
            }
        }

        return $this;
    }
}