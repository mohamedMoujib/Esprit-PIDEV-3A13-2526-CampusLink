<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\CalendarEventRepository;

#[ORM\Entity(repositoryClass: CalendarEventRepository::class)]
#[ORM\Table(name: 'calendar_events')]
class CalendarEvent
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

    #[ORM\ManyToOne(targetEntity: Reservation::class, inversedBy: 'calendarEvents')]
    #[ORM\JoinColumn(name: 'reservation_id', referencedColumnName: 'id', nullable: false, onDelete: "CASCADE")]
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

    #[ORM\Column(name: 'event_date', type: 'datetime')]
    private ?\DateTimeInterface $eventDate = null;

    public function getEventDate(): ?\DateTimeInterface
    {
        return $this->eventDate;
    }

    public function setEventDate(\DateTimeInterface $eventDate): self
    {
        $this->eventDate = $eventDate;
        return $this;
    }

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $note = null;

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): self
    {
        $this->note = $note;
        return $this;
    }
}