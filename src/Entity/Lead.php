<?php

namespace App\Entity;

use App\Repository\LeadRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LeadRepository::class)]
#[ORM\Table(name: 'leads')]
#[ORM\Index(columns: ['status'], name: 'idx_lead_status')]
#[ORM\Index(columns: ['callback_at'], name: 'idx_lead_callback')]
#[ORM\Index(columns: ['injection_date'], name: 'idx_lead_injection')]
class Lead
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'injection_date', type: 'date')]
    private ?\DateTimeInterface $injectionDate = null;

    #[ORM\Column(name: 'client_name', length: 255, options: ['default' => ''])]
    private ?string $clientName = '';

    #[ORM\Column(length: 50)]
    private ?string $phone = null;

    #[ORM\Column(name: 'phone_2', length: 50, options: ['default' => ''])]
    private ?string $phone2 = '';

    #[ORM\Column(length: 255, options: ['default' => ''])]
    private ?string $email = '';

    #[ORM\Column(type: 'text', options: ['default' => ''])]
    private ?string $address = '';

    #[ORM\Column(length: 100, options: ['default' => ''])]
    private ?string $city = '';

    #[ORM\Column(length: 100, options: ['default' => ''])]
    private ?string $source = '';

    #[ORM\Column(name: 'request_details', type: 'text', options: ['default' => ''])]
    private ?string $requestDetails = '';

    #[ORM\Column(length: 50, options: ['default' => 'Trotinette'])]
    private ?string $product = 'Trotinette';

    #[ORM\Column(length: 50, options: ['default' => 'Nouveau'])]
    private ?string $status = 'Nouveau';

    #[ORM\Column(name: 'quote_number', length: 50, options: ['default' => ''])]
    private ?string $quoteNumber = '';

    #[ORM\Column(name: 'last_note', type: 'text', options: ['default' => ''])]
    private ?string $lastNote = '';

    #[ORM\Column(name: 'created_by', length: 100, options: ['default' => ''])]
    private ?string $createdBy = '';

    #[ORM\Column(name: 'callback_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $callbackAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: Campaign::class, inversedBy: 'leads')]
    #[ORM\JoinColumn(name: 'campaign_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Campaign $campaign = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'assignedLeads')]
    #[ORM\JoinColumn(name: 'assigned_to', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $assignedTo = null;

    #[ORM\ManyToOne(targetEntity: InjectionBatch::class, inversedBy: 'leads')]
    #[ORM\JoinColumn(name: 'batch_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?InjectionBatch $batch = null;

    #[ORM\OneToMany(mappedBy: 'lead', targetEntity: LeadEvent::class, cascade: ['persist', 'remove'])]
    private Collection $events;

    #[ORM\OneToMany(mappedBy: 'lead', targetEntity: LeadAttachment::class, cascade: ['persist', 'remove'])]
    private Collection $attachments;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->events = new ArrayCollection();
        $this->attachments = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getInjectionDate(): ?\DateTimeInterface { return $this->injectionDate; }
    public function setInjectionDate(\DateTimeInterface $injectionDate): self { $this->injectionDate = $injectionDate; return $this; }

    public function getClientName(): ?string { return $this->clientName; }
    public function setClientName(string $clientName): self { $this->clientName = $clientName; return $this; }

    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(string $phone): self { $this->phone = $phone; return $this; }

    public function getPhone2(): ?string { return $this->phone2; }
    public function setPhone2(string $phone2): self { $this->phone2 = $phone2; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }

    public function getAddress(): ?string { return $this->address; }
    public function setAddress(string $address): self { $this->address = $address; return $this; }

    public function getCity(): ?string { return $this->city; }
    public function setCity(string $city): self { $this->city = $city; return $this; }

    public function getSource(): ?string { return $this->source; }
    public function setSource(string $source): self { $this->source = $source; return $this; }

    public function getRequestDetails(): ?string { return $this->requestDetails; }
    public function setRequestDetails(string $requestDetails): self { $this->requestDetails = $requestDetails; return $this; }

    public function getProduct(): ?string { return $this->product; }
    public function setProduct(string $product): self { $this->product = $product; return $this; }

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function getQuoteNumber(): ?string { return $this->quoteNumber; }
    public function setQuoteNumber(string $quoteNumber): self { $this->quoteNumber = $quoteNumber; return $this; }

    public function getLastNote(): ?string { return $this->lastNote; }
    public function setLastNote(string $lastNote): self { $this->lastNote = $lastNote; return $this; }

    public function getCreatedBy(): ?string { return $this->createdBy; }
    public function setCreatedBy(string $createdBy): self { $this->createdBy = $createdBy; return $this; }

    public function getCallbackAt(): ?\DateTimeInterface { return $this->callbackAt; }
    public function setCallbackAt(?\DateTimeInterface $callbackAt): self { $this->callbackAt = $callbackAt; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): self { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeInterface $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }

    public function getCampaign(): ?Campaign { return $this->campaign; }
    public function setCampaign(?Campaign $campaign): self { $this->campaign = $campaign; return $this; }

    public function getAssignedTo(): ?User { return $this->assignedTo; }
    public function setAssignedTo(?User $assignedTo): self { $this->assignedTo = $assignedTo; return $this; }

    public function getBatch(): ?InjectionBatch { return $this->batch; }
    public function setBatch(?InjectionBatch $batch): self { $this->batch = $batch; return $this; }

    /** @return Collection<int, LeadEvent> */
    public function getEvents(): Collection { return $this->events; }

    /** @return Collection<int, LeadAttachment> */
    public function getAttachments(): Collection { return $this->attachments; }
}
