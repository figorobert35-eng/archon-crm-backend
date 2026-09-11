<?php

namespace App\Entity;

use App\Repository\LeadAttachmentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LeadAttachmentRepository::class)]
#[ORM\Table(name: 'lead_attachments')]
class LeadAttachment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Lead::class, inversedBy: 'attachments')]
    #[ORM\JoinColumn(name: 'lead_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Lead $lead = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(name: 'attachment_type', length: 50, options: ['default' => 'document'])]
    private ?string $attachmentType = 'document';

    #[ORM\Column(name: 'original_name', length: 255)]
    private ?string $originalName = null;

    #[ORM\Column(name: 'stored_name', length: 255, unique: true)]
    private ?string $storedName = null;

    #[ORM\Column(name: 'mime_type', length: 100, options: ['default' => 'application/octet-stream'])]
    private ?string $mimeType = 'application/octet-stream';

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private ?int $size = 0;

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

    public function getAttachmentType(): ?string { return $this->attachmentType; }
    public function setAttachmentType(string $attachmentType): self { $this->attachmentType = $attachmentType; return $this; }

    public function getOriginalName(): ?string { return $this->originalName; }
    public function setOriginalName(string $originalName): self { $this->originalName = $originalName; return $this; }

    public function getStoredName(): ?string { return $this->storedName; }
    public function setStoredName(string $storedName): self { $this->storedName = $storedName; return $this; }

    public function getMimeType(): ?string { return $this->mimeType; }
    public function setMimeType(string $mimeType): self { $this->mimeType = $mimeType; return $this; }

    public function getSize(): ?int { return $this->size; }
    public function setSize(int $size): self { $this->size = $size; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): self { $this->createdAt = $createdAt; return $this; }
}
