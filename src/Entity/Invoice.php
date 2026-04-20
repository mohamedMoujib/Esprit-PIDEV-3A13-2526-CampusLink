<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\InvoiceRepository;

#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
#[ORM\Table(name: 'invoices')]
class Invoice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    #[ORM\ManyToOne(targetEntity: Payment::class, inversedBy: 'invoices')]
    #[ORM\JoinColumn(name: 'payment_id', nullable: false, onDelete: "CASCADE")]
    private ?Payment $payment = null;

    public function getPayment(): ?Payment
    {
        return $this->payment;
    }

    public function setPayment(?Payment $payment): self
    {
        $this->payment = $payment;
        return $this;
    }

    #[ORM\Column(name: 'issue_date', type: 'datetime', nullable: true, options: ["default" => "CURRENT_TIMESTAMP"])]
    private ?\DateTimeInterface $issueDate = null;

    public function __construct()
    {
        $this->issueDate = new \DateTime(); // ✅ sync with DB
    }

    public function getIssueDate(): ?\DateTimeInterface
    {
        return $this->issueDate;
    }

    public function setIssueDate(?\DateTimeInterface $issueDate): self
    {
        $this->issueDate = $issueDate;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $details = null;

    public function getDetails(): ?string
    {
        return $this->details;
    }

    public function setDetails(?string $details): self
    {
        $this->details = $details;
        return $this;
    }
}