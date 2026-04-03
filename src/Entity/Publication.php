<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\PublicationRepository;

#[ORM\Entity(repositoryClass: PublicationRepository::class)]
#[ORM\Table(name: 'publications')]
class Publication
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'publications')]
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

    // ✅ CORRECT — matches DB
    #[ORM\Column(name: 'type_publication', columnDefinition: "ENUM('OFFRE_SERVICE','DEMANDE_SERVICE','VENTE_OBJET')")]
    private string $typePublication = 'VENTE_OBJET';

    public function getTypePublication(): string
    {
        return $this->typePublication;
    }

    public function setTypePublication(string $typePublication): self
    {
        $this->typePublication = $typePublication;
        return $this;
    }

    #[ORM\Column(type: 'string', length: 200)]
    private string $titre;

    public function getTitre(): string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): self
    {
        $this->titre = $titre;
        return $this;
    }

    #[ORM\Column(type: 'text')]
    private string $message;

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;
        return $this;
    }

    #[ORM\Column(name: 'image_url', type: 'string', length: 500, nullable: true)]
    private ?string $imageUrl = null;

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): self
    {
        $this->imageUrl = $imageUrl;
        return $this;
    }

    #[ORM\Column(type: 'string', length: 200, nullable: true)]
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

    #[ORM\Column(name: 'prix_vente', type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $prixVente = null;

    public function getPrixVente(): ?string
    {
        return $this->prixVente;
    }

    public function setPrixVente(?string $prixVente): self
    {
        $this->prixVente = $prixVente;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: Service::class, inversedBy: 'publications')]
    #[ORM\JoinColumn(name: 'service_id', nullable: true, onDelete: "SET NULL")]
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

    // ✅ FIX: DB column type is timestamp, use datetime in Doctrine
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

    #[ORM\ManyToOne(targetEntity: Categorie::class, inversedBy: 'publications')]
    #[ORM\JoinColumn(name: 'category_id', nullable: true, onDelete: "SET NULL")]
    private ?Categorie $category = null;

    public function getCategory(): ?Categorie
    {
        return $this->category;
    }

    public function setCategory(?Categorie $category): self
    {
        $this->category = $category;
        return $this;
    }

    // ✅ FIX: DB uses EN_COURS/TERMINEE/ANNULEE — not CLOSED/ARCHIVED
    #[ORM\Column(columnDefinition: "ENUM('ACTIVE','EN_COURS','TERMINEE','ANNULEE')")]
    private string $status = 'ACTIVE';

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: true, options: ["default" => 0])]
    private ?int $vues = 0;

    public function getVues(): ?int
    {
        return $this->vues;
    }

    public function setVues(?int $vues): self
    {
        $this->vues = $vues;
        return $this;
    }

    #[ORM\Column(name: 'created_at', type: 'datetime', nullable: true, options: ["default" => "CURRENT_TIMESTAMP"])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime', nullable: true, options: ["default" => "CURRENT_TIMESTAMP"])]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}