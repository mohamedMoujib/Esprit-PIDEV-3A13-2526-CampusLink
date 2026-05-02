<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Reclamation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // qui fait la réclamation
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $user;

    // contre qui
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $cible;

    // optionnel
    #[ORM\ManyToOne(targetEntity: Reservation::class)]
    private $reservation;

    #[ORM\Column(length: 50)]
    private string $type; // TECHNIQUE / RESERVATION / COMPORTEMENT

    #[ORM\Column(length: 255)]
    private string $sujet;

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(length: 50)]
    private string $statut = 'EN_ATTENTE';

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reponseAdmin = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    // getters setters (important)
    public function getId(): ?int { return $this->id; }

    public function getUser(){ return $this->user; }
    public function setUser($u){ $this->user=$u; return $this; }

    public function getCible(){ return $this->cible; }
    public function setCible($c){ $this->cible=$c; return $this; }

    public function getReservation(){ return $this->reservation; }
    public function setReservation($r){ $this->reservation=$r; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $t){ $this->type=$t; return $this; }

    public function getSujet(): string { return $this->sujet; }
    public function setSujet(string $s){ $this->sujet=$s; return $this; }

    public function getDescription(): string { return $this->description; }
    public function setDescription(string $d){ $this->description=$d; return $this; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $s){ $this->statut=$s; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }

    public function getReponseAdmin(): ?string { return $this->reponseAdmin; }
    public function setReponseAdmin(?string $r){ $this->reponseAdmin=$r; return $this; }
}