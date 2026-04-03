<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\DemandeRepository;

#[ORM\Entity(repositoryClass: DemandeRepository::class)]
#[ORM\Table(name: 'demandes')]
class Demande
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

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'demandesAsStudent')]
    #[ORM\JoinColumn(name: 'student_id', nullable: false, onDelete: "CASCADE")]
    private ?User $student = null;

    public function getStudent(): ?User
    {
        return $this->student;
    }

    public function setStudent(?User $student): self
    {
        $this->student = $student;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: Service::class, inversedBy: 'demandes')]
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

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'demandesAsPrestataire')]
    #[ORM\JoinColumn(name: 'prestataire_id', nullable: false, onDelete: "CASCADE")]
    private ?User $prestataire = null;

    public function getPrestataire(): ?User
    {
        return $this->prestataire;
    }

    public function setPrestataire(?User $prestataire): self
    {
        $this->prestataire = $prestataire;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message = null;

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;
        return $this;
    }

    #[ORM\Column(name: 'requested_date', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $requestedDate = null;

    public function getRequestedDate(): ?\DateTimeInterface
    {
        return $this->requestedDate;
    }

    public function setRequestedDate(?\DateTimeInterface $requestedDate): self
    {
        $this->requestedDate = $requestedDate;
        return $this;
    }

    #[ORM\Column(name: 'proposed_price', type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $proposedPrice = null;

    public function getProposedPrice(): ?string
    {
        return $this->proposedPrice;
    }

    public function setProposedPrice(?string $proposedPrice): self
    {
        $this->proposedPrice = $proposedPrice;
        return $this;
    }

    #[ORM\Column(columnDefinition: "ENUM('PENDING','ACCEPTED','REJECTED','CANCELLED')")]
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

    #[ORM\Column(name: 'created_at', type: 'datetime', nullable: true, options: ["default" => "CURRENT_TIMESTAMP"])]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime(); // ✅ sync with DB default
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }
}