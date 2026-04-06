<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\UserRepository;
use App\Entity\Notification;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function getId(): ?int { return $this->id; }

    // ✅ FIX: DB uses ETUDIANT not STUDENT
    #[ORM\Column(name: 'user_type', columnDefinition: "ENUM('ETUDIANT','PRESTATAIRE','ADMIN')")]
    private string $userType;

    public function getUserType(): string { return $this->userType; }
    public function setUserType(string $userType): self { $this->userType = $userType; return $this; }

    #[ORM\Column]
    private string $name;

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    #[ORM\Column(unique: true)]
    private string $email;

    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }

    #[ORM\Column]
    private string $password;

    public function getPassword(): string { return $this->password; }
    public function setPassword(string $password): self { $this->password = $password; return $this; }

    #[ORM\Column(nullable: true)]
    private ?string $phone = null;

    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $phone): self { $this->phone = $phone; return $this; }

    #[ORM\Column(name: 'date_naissance', type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateNaissance = null;

    public function getDateNaissance(): ?\DateTimeInterface { return $this->dateNaissance; }
    public function setDateNaissance(?\DateTimeInterface $dateNaissance): self { $this->dateNaissance = $dateNaissance; return $this; }

    #[ORM\Column(nullable: true)]
    private ?string $gender = null;

    public function getGender(): ?string { return $this->gender; }
    public function setGender(?string $gender): self { $this->gender = $gender; return $this; }

    #[ORM\Column(name: 'profile_picture', nullable: true)]
    private ?string $profilePicture = null;

    public function getProfilePicture(): ?string { return $this->profilePicture; }
    public function setProfilePicture(?string $profilePicture): self { $this->profilePicture = $profilePicture; return $this; }

    #[ORM\Column(nullable: true)]
    private ?string $address = null;

    public function getAddress(): ?string { return $this->address; }
    public function setAddress(?string $address): self { $this->address = $address; return $this; }
    public function getRoles(): array
{
    return match($this->userType) {
        'ADMIN'       => ['ROLE_ADMIN',      'ROLE_USER'],
        'PRESTATAIRE' => ['ROLE_PRESTATAIRE', 'ROLE_USER'],
        'ETUDIANT'    => ['ROLE_ETUDIANT',    'ROLE_USER'],
        default       => ['ROLE_USER'],
    };
}
    public function getUserIdentifier(): string
    {
        return $this->email; // tells Symfony "login with email"
    }

    public function eraseCredentials(): void
    {
        // nothing to clear — you have no plaintext password stored
    }

    #[ORM\Column(name: 'created_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    // ✅ FIX: DB uses ENUM('ACTIVE','INACTIVE','BANNED') — keep as string but note DB constraint
    #[ORM\Column(nullable: true)]
    private ?string $status = null;

    #[ORM\Column(nullable: true)]
    private ?string $universite = null;

    #[ORM\Column(nullable: true)]
    private ?string $filiere = null;

    #[ORM\Column(nullable: true)]
    private ?string $specialization = null;

    #[ORM\Column(name: 'trust_points', nullable: true)]
    private ?int $trustPoints = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();

        $this->demandesAsStudent     = new ArrayCollection();
        $this->demandesAsPrestataire = new ArrayCollection();
        $this->sentMessages          = new ArrayCollection();
        $this->receivedMessages      = new ArrayCollection();
        $this->reviewsAsStudent      = new ArrayCollection();
        $this->reviewsAsPrestataire  = new ArrayCollection();
        $this->publications          = new ArrayCollection();
        $this->reservations          = new ArrayCollection();
        $this->services              = new ArrayCollection();
        $this->trustPointHistories   = new ArrayCollection();
        $this->notifications         = new ArrayCollection();
    }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(?string $status): self { $this->status = $status; return $this; }

    public function getUniversite(): ?string { return $this->universite; }
    public function setUniversite(?string $universite): self { $this->universite = $universite; return $this; }

    public function getFiliere(): ?string { return $this->filiere; }
    public function setFiliere(?string $filiere): self { $this->filiere = $filiere; return $this; }

    public function getSpecialization(): ?string { return $this->specialization; }
    public function setSpecialization(?string $specialization): self { $this->specialization = $specialization; return $this; }

    public function getTrustPoints(): ?int { return $this->trustPoints; }
    public function setTrustPoints(?int $trustPoints): self { $this->trustPoints = $trustPoints; return $this; }

    // ===== RELATIONS =====

    #[ORM\OneToMany(mappedBy: 'student', targetEntity: Demande::class)]
    private Collection $demandesAsStudent;

    #[ORM\OneToMany(mappedBy: 'prestataire', targetEntity: Demande::class)]
    private Collection $demandesAsPrestataire;

    #[ORM\OneToMany(mappedBy: 'sender', targetEntity: Message::class)]
    private Collection $sentMessages;

    #[ORM\OneToMany(mappedBy: 'receiver', targetEntity: Message::class)]
    private Collection $receivedMessages;

    #[ORM\OneToMany(mappedBy: 'student', targetEntity: Review::class)]
    private Collection $reviewsAsStudent;

    #[ORM\OneToMany(mappedBy: 'prestataire', targetEntity: Review::class)]
    private Collection $reviewsAsPrestataire;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Publication::class)]
    private Collection $publications;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Reservation::class)]
    private Collection $reservations;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Service::class)]
    private Collection $services;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: TrustPointHistory::class)]
    private Collection $trustPointHistories;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Notification::class, orphanRemoval: true)]
    private Collection $notifications;

    public function getPublications(): Collection { return $this->publications; }
    public function getReservations(): Collection { return $this->reservations; }
    public function getServices(): Collection { return $this->services; }
    public function getTrustPointHistories(): Collection { return $this->trustPointHistories; }

    public function getNotifications(): Collection { return $this->notifications; }
}