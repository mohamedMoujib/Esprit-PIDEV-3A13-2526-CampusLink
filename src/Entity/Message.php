<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\MessageRepository;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Table(name: 'messages')]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $sender = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $receiver = null;

    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $timestamp;

    #[ORM\Column(type: 'boolean')]
    private bool $isRead = false;

    // 🔥 NOUVEAU (programmation)
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $scheduledAt = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isSent = true;

    public function __construct()
    {
        $this->timestamp = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getSender(): ?User { return $this->sender; }
    public function setSender(User $sender): self { $this->sender = $sender; return $this; }

    public function getReceiver(): ?User { return $this->receiver; }
    public function setReceiver(User $receiver): self { $this->receiver = $receiver; return $this; }

    public function getContent(): string { return $this->content; }
    public function setContent(string $content): self { $this->content = $content; return $this; }

    public function getTimestamp(): \DateTimeInterface { return $this->timestamp; }

    public function getCreatedAt(): \DateTimeInterface { return $this->timestamp; }

    public function isRead(): bool { return $this->isRead; }
    public function setIsRead(bool $isRead): self { $this->isRead = $isRead; return $this; }

    // 🔥 PROGRAMMATION
    public function getScheduledAt(): ?\DateTimeInterface { return $this->scheduledAt; }
    public function setScheduledAt(?\DateTimeInterface $date): self { $this->scheduledAt = $date; return $this; }

    public function isSent(): bool { return $this->isSent; }
    public function setIsSent(bool $sent): self { $this->isSent = $sent; return $this; }
}