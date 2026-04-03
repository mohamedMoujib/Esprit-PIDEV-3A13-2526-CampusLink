<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\CategorieRepository;

#[ORM\Entity(repositoryClass: CategorieRepository::class)]
#[ORM\Table(name: 'categories')]
class Categorie
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

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $name = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    // ✅ FIX mappedBy (must match Publication entity field)
    #[ORM\OneToMany(mappedBy: 'category', targetEntity: Publication::class)]
    private Collection $publications;

    

    // ✅ FIX mappedBy (must match Service entity field)
    #[ORM\OneToMany(mappedBy: 'category', targetEntity: Service::class)]
    private Collection $services;

    public function __construct()
    {
        $this->publications = new ArrayCollection();
        $this->services = new ArrayCollection();
    }

    public function getPublications(): Collection
    {
        return $this->publications;
    }

    public function addPublication(Publication $publication): self
    {
        if (!$this->publications->contains($publication)) {
            $this->publications[] = $publication;
            $publication->setCategory($this); // 🔥 important
        }

        return $this;
    }

    public function removePublication(Publication $publication): self
    {
        if ($this->publications->removeElement($publication)) {
            if ($publication->getCategory() === $this) {
                $publication->setCategory(null);
            }
        }

        return $this;
    }

    public function getServices(): Collection
    {
        return $this->services;
    }

    public function addService(Service $service): self
    {
        if (!$this->services->contains($service)) {
            $this->services[] = $service;
            $service->setCategory($this); // 🔥 important
        }

        return $this;
    }

    public function removeService(Service $service): self
    {
        if ($this->services->removeElement($service)) {
            if ($service->getCategory() === $this) {
                $service->setCategory(null);
            }
        }

        return $this;
    }
}