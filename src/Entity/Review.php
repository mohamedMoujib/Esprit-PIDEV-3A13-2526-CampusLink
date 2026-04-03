<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\ReviewRepository;

#[ORM\Entity(repositoryClass: ReviewRepository::class)]
#[ORM\Table(name: 'reviews')]
class Review
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function getId(): ?int { return $this->id; }

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'reviewsAsStudent')]
    #[ORM\JoinColumn(name: 'student_id', nullable: false, onDelete: "CASCADE")]
    private ?User $student = null;

    public function getStudent(): ?User { return $this->student; }
    public function setStudent(?User $student): self { $this->student = $student; return $this; }

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'reviewsAsPrestataire')]
    #[ORM\JoinColumn(name: 'prestataire_id', nullable: false, onDelete: "CASCADE")]
    private ?User $prestataire = null;

    public function getPrestataire(): ?User { return $this->prestataire; }
    public function setPrestataire(?User $prestataire): self { $this->prestataire = $prestataire; return $this; }

    #[ORM\ManyToOne(targetEntity: Reservation::class, inversedBy: 'reviews')]
    #[ORM\JoinColumn(name: 'reservation_id', nullable: false, onDelete: "CASCADE")]
    private ?Reservation $reservation = null;

    public function getReservation(): ?Reservation { return $this->reservation; }
    public function setReservation(?Reservation $reservation): self { $this->reservation = $reservation; return $this; }

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $rating = null;

    public function getRating(): ?int { return $this->rating; }
    public function setRating(?int $rating): self { $this->rating = $rating; return $this; }

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    public function getComment(): ?string { return $this->comment; }
    public function setComment(?string $comment): self { $this->comment = $comment; return $this; }

    #[ORM\Column(name: 'is_reported', type: 'boolean', nullable: true)]
    private ?bool $isReported = null;

    public function isReported(): ?bool { return $this->isReported; }
    public function setIsReported(?bool $isReported): self { $this->isReported = $isReported; return $this; }

    #[ORM\Column(name: 'report_reason', type: 'text', nullable: true)]
    private ?string $reportReason = null;

    public function getReportReason(): ?string { return $this->reportReason; }
    public function setReportReason(?string $reportReason): self { $this->reportReason = $reportReason; return $this; }

    #[ORM\Column(name: 'reported_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $reportedAt = null;

    public function getReportedAt(): ?\DateTimeInterface { return $this->reportedAt; }
    public function setReportedAt(?\DateTimeInterface $reportedAt): self { $this->reportedAt = $reportedAt; return $this; }
}