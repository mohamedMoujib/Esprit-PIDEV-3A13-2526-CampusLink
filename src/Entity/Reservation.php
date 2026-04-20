<?php

    namespace App\Entity;

    use Doctrine\ORM\Mapping as ORM;
    use Doctrine\Common\Collections\ArrayCollection;
    use Doctrine\Common\Collections\Collection;
    use App\Repository\ReservationRepository;

    #[ORM\Entity(repositoryClass: ReservationRepository::class)]
    #[ORM\Table(name: 'reservations')]
    class Reservation
    {
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column]
        private ?int $id = null;

        public function getId(): ?int
        {
            return $this->id;
        }

        #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'reservations')]
        #[ORM\JoinColumn(name: 'student_id', nullable: false, onDelete: "CASCADE")]
        private ?User $user = null;

        public function getUser(): ?User
        {
            return $this->user;
        }
        public function setUser(?User $user): self
        {
            $this->user = $user;
            return $this;
        }

        #[ORM\ManyToOne(targetEntity: Service::class, inversedBy: 'reservations')]
        #[ORM\JoinColumn(name: 'service_id', nullable: false, onDelete: "CASCADE")]
        private ?Service $service = null;

        public function getService(): ?Service
        {
            return $this->service;
        }
        public function setService(?Service $service): self
        {
            $this->service = $service;
            return $this;
        }

        #[ORM\Column(type: 'datetime')]
        private \DateTimeInterface $date;

        public function getDate(): \DateTimeInterface
        {
            return $this->date;
        }
        public function setDate(\DateTimeInterface $date): self
        {
            $this->date = $date;
            return $this;
        }
        // #[ORM\Column(name: 'created_at', type: 'datetime', nullable: true, options: ["default" => "CURRENT_TIMESTAMP"])]
        // private ?\DateTimeInterface $createdAt = null;

        // public function getCreatedAt(): ?\DateTimeInterface
        // {
        //     return $this->createdAt;
        // }

        // public function setCreatedAt(?\DateTimeInterface $createdAt): self
        // {
        //     $this->createdAt = $createdAt;
        //     return $this;
        // }
        #[ORM\Column(columnDefinition: "ENUM('PENDING','CONFIRMED','CANCELLED')")]
        private string $status = 'PENDING';

        public function getStatus(): string
        {
            return $this->status;
        }
        public function setStatus(string $status): self
        {
            $this->status = $status;
            return $this;
        }

        #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
        private string $price;

        public function getPrice(): string
        {
            return $this->price;
        }
        public function setPrice(string $price): self
        {
            $this->price = $price;
            return $this;
        }

        #[ORM\Column(type: 'string', nullable: true)]
        private ?string $localisation = null;

        public function getLocalisation(): ?string
        {
            return $this->localisation;
        }
        public function setLocalisation(?string $localisation): self
        {
            $this->localisation = $localisation;
            return $this;
        }

        #[ORM\OneToMany(mappedBy: 'reservation', targetEntity: CalendarEvent::class)]
        private Collection $calendarEvents;

        #[ORM\OneToMany(mappedBy: 'reservation', targetEntity: Payment::class)]
        private Collection $payments;

        #[ORM\OneToMany(mappedBy: 'reservation', targetEntity: Review::class)]
        private Collection $reviews;

        public function __construct()
        {
            $this->calendarEvents = new ArrayCollection();
            $this->payments = new ArrayCollection();
            $this->reviews = new ArrayCollection();
            // $this->createdAt = new \DateTime();
        }

        public function getCalendarEvents(): Collection
        {
            return $this->calendarEvents;
        }

        public function addCalendarEvent(CalendarEvent $event): self
        {
            if (!$this->calendarEvents->contains($event)) {
                $this->calendarEvents[] = $event;
                $event->setReservation($this);
            }
            return $this;
        }

        public function removeCalendarEvent(CalendarEvent $event): self
        {
            if ($this->calendarEvents->removeElement($event)) {
                if ($event->getReservation() === $this) {
                    $event->setReservation(null);
                }
            }
            return $this;
        }

        public function getPayments(): Collection
        {
            return $this->payments;
        }

        public function addPayment(Payment $payment): self
        {
            if (!$this->payments->contains($payment)) {
                $this->payments[] = $payment;
                $payment->setReservation($this);
            }
            return $this;
        }

        public function removePayment(Payment $payment): self
        {
            if ($this->payments->removeElement($payment)) {
                if ($payment->getReservation() === $this) {
                    $payment->setReservation(null);
                }
            }
            return $this;
        }

        public function getReviews(): Collection
        {
            return $this->reviews;
        }

        public function addReview(Review $review): self
        {
            if (!$this->reviews->contains($review)) {
                $this->reviews[] = $review;
                $review->setReservation($this);
            }
            return $this;
        }

        public function removeReview(Review $review): self
        {
            if ($this->reviews->removeElement($review)) {
                if ($review->getReservation() === $this) {
                    $review->setReservation(null);
                }
            }
            return $this;
        }
    }
