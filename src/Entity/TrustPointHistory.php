<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\TrustPointHistoryRepository;

#[ORM\Entity(repositoryClass: TrustPointHistoryRepository::class)]
#[ORM\Table(name: 'trust_point_history')]
class TrustPointHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function getId(): ?int { return $this->id; }

    // ✅ FIX: DB has ON DELETE CASCADE and nullable: false
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'trustPointHistories')]
    #[ORM\JoinColumn(name: 'prestataire_id', nullable: false, onDelete: "CASCADE")]
    private ?User $user = null;

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    #[ORM\Column(name: 'points_added', type: 'integer')]
    private int $pointsAdded;

    public function getPointsAdded(): int { return $this->pointsAdded; }
    public function setPointsAdded(int $pointsAdded): self { $this->pointsAdded = $pointsAdded; return $this; }

    // ✅ FIX: DB has ENUM('RESERVATION_COMPLETED','REVIEW_RATING') not plain VARCHAR
    #[ORM\Column(columnDefinition: "ENUM('RESERVATION_COMPLETED','REVIEW_RATING')")]
    private string $reason;

    public function getReason(): string { return $this->reason; }
    public function setReason(string $reason): self { $this->reason = $reason; return $this; }

    #[ORM\Column(type: 'datetime', nullable: true, options: ["default" => "CURRENT_TIMESTAMP"])]
    private ?\DateTimeInterface $date = null;

    public function getDate(): ?\DateTimeInterface { return $this->date; }
    public function setDate(?\DateTimeInterface $date): self { $this->date = $date; return $this; }

    public function __construct()
    {
        $this->date = new \DateTime();
    }
}