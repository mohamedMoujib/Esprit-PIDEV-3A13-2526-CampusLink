<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\ServiceRepository;

#[ORM\Entity(repositoryClass: ServiceRepository::class)]
#[ORM\Table(name: 'services')]
class Service
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function getId(): ?int { return $this->id; }

    #[ORM\Column]
    private string $title;

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $price;

    public function getPrice(): string { return $this->price; }
    public function setPrice(string $price): self { $this->price = $price; return $this; }

    #[ORM\Column(nullable: true)]
    private ?string $image = null;

    public function getImage(): ?string { return $this->image; }
    public function setImage(?string $image): self { $this->image = $image; return $this; }

    // ✅ FIX: DB has ON DELETE CASCADE and nullable: false
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'services')]
    #[ORM\JoinColumn(name: 'prestataire_id', nullable: false, onDelete: "CASCADE")]
    private ?User $user = null;

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    // ✅ FIX: DB has no onDelete rule for category (no ON DELETE clause = RESTRICT by default)
    #[ORM\ManyToOne(targetEntity: Categorie::class, inversedBy: 'services')]
    #[ORM\JoinColumn(name: 'category_id', nullable: true)]
    private ?Categorie $category = null;

    public function getCategory(): ?Categorie { return $this->category; }
    public function setCategory(?Categorie $category): self { $this->category = $category; return $this; }

    // ✅ FIX: DB uses EN_ATTENTE/CONFIRMEE/REFUSEE/TERMINEE — not AVAILABLE/UNAVAILABLE/ARCHIVED
    #[ORM\Column(columnDefinition: "ENUM('EN_ATTENTE','CONFIRMEE','REFUSEE','TERMINEE')")]
    private string $status = 'EN_ATTENTE';

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    #[ORM\OneToMany(mappedBy: 'service', targetEntity: Demande::class)]
    private Collection $demandes;

    #[ORM\OneToMany(mappedBy: 'service', targetEntity: Publication::class)]
    private Collection $publications;

    #[ORM\OneToMany(mappedBy: 'service', targetEntity: Reservation::class)]
    private Collection $reservations;

    public function __construct()
    {
        $this->demandes     = new ArrayCollection();
        $this->publications = new ArrayCollection();
        $this->reservations = new ArrayCollection();
    }

    public function getDemandes(): Collection { return $this->demandes; }
    public function getPublications(): Collection { return $this->publications; }
    public function getReservations(): Collection { return $this->reservations; }
}