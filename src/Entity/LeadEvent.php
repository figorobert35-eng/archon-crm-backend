<?php

namespace App\Entity;

use App\Repository\LeadEventRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LeadEventRepository::class)]
#[ORM\Table(name: 'lead_events')]
class LeadEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Lead::class, inversedBy: 'events')]
    #[ORM\JoinColumn(name: 'lead_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Lead $lead = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(name: 'event_type', length: 50)]
    private ?string $eventType = null;

    #[ORM\Column(name: 'old_status', length: 50, nullable: true)]
    private ?string $oldStatus = null;

    #[ORM\Column(name: 'new_status', length: 50, nullable: true)]
    private ?string $newStatus = null;

    #[ORM\Column(type: 'text', options: ['default' => ''])]
    private ?string $note = '';

    #[ORM\Column(name: 'callback_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $callbackAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getLead(): ?Lead { return $this->lead; }
    public function setLead(?Lead $lead): self { $this->lead = $lead; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getEventType(): ?string { return $this->eventType; }
    public function setEventType(string $eventType): self { $this->eventType = $eventType; return $this; }

    public function getOldStatus(): ?string { return $this->oldStatus; }
    public function setOldStatus(?string $oldStatus): self { $this->oldStatus = $oldStatus; return $this; }

    public function getNewStatus(): ?string { return $this->newStatus; }
    public function setNewStatus(?string $newStatus): self { $this->newStatus = $newStatus; return $this; }

    public function getNote(): ?string { return $this->note; }
    public function setNote(string $note): self { $this->note = $note; return $this; }

    public function getCallbackAt(): ?\DateTimeInterface { return $this->callbackAt; }
    public function setCallbackAt(?\DateTimeInterface $callbackAt): self { $this->callbackAt = $callbackAt; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): self { $this->createdAt = $createdAt; return $this; }
}
