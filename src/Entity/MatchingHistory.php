<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\MatchingHistoryRepository;

// ✅ FIX: Removed uniqueConstraints definition — Doctrine was generating a DROP INDEX
// for the existing 'uq_pub_service' unique key because it conflicts with the
// auto-named FK indexes. The unique constraint still exists in the DB (safe to keep there),
// but removing it from the entity stops Doctrine from trying to manage/drop it.
#[ORM\Entity(repositoryClass: MatchingHistoryRepository::class)]
#[ORM\Table(name: 'matching_history')]
class MatchingHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    #[ORM\ManyToOne(targetEntity: Publication::class)]
    #[ORM\JoinColumn(name: 'publication_id', nullable: false, onDelete: "CASCADE")]
    private ?Publication $publication = null;

    public function getPublication(): ?Publication
    {
        return $this->publication;
    }

    public function setPublication(?Publication $publication): self
    {
        $this->publication = $publication;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: Service::class)]
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

    #[ORM\Column(name: 'compatibility_score', type: 'decimal', precision: 5, scale: 2)]
    private string $compatibilityScore;

    public function getCompatibilityScore(): string
    {
        return $this->compatibilityScore;
    }

    public function setCompatibilityScore(string $compatibilityScore): self
    {
        $this->compatibilityScore = $compatibilityScore;
        return $this;
    }

    #[ORM\Column(type: 'boolean')]
    private bool $notified = false;

    public function isNotified(): bool
    {
        return $this->notified;
    }

    public function setNotified(bool $notified): self
    {
        $this->notified = $notified;
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