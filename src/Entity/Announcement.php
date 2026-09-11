<?php

namespace App\Entity;

use App\Repository\AnnouncementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AnnouncementRepository::class)]
#[ORM\Table(name: 'announcements')]
class Announcement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: 'text')]
    private ?string $message = null;

    #[ORM\Column(length: 20, options: ['default' => 'info'])]
    private ?string $priority = 'info';

    #[ORM\Column(name: 'target_role', length: 20, options: ['default' => 'all'])]
    private ?string $targetRole = 'all';

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private ?bool $active = true;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'archived_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $archivedAt = null;

    #[ORM\OneToMany(mappedBy: 'announcement', targetEntity: AnnouncementRead::class, cascade: ['persist', 'remove'])]
    private Collection $reads;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->reads = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }

    public function getMessage(): ?string { return $this->message; }
    public function setMessage(string $message): self { $this->message = $message; return $this; }

    public function getPriority(): ?string { return $this->priority; }
    public function setPriority(string $priority): self { $this->priority = $priority; return $this; }

    public function getTargetRole(): ?string { return $this->targetRole; }
    public function setTargetRole(string $targetRole): self { $this->targetRole = $targetRole; return $this; }

    public function isActive(): ?bool { return $this->active; }
    public function setActive(bool $active): self { $this->active = $active; return $this; }

    public function getCreatedBy(): ?User { return $this->createdBy; }
    public function setCreatedBy(?User $createdBy): self { $this->createdBy = $createdBy; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): self { $this->createdAt = $createdAt; return $this; }

    public function getArchivedAt(): ?\DateTimeInterface { return $this->archivedAt; }
    public function setArchivedAt(?\DateTimeInterface $archivedAt): self { $this->archivedAt = $archivedAt; return $this; }

    /** @return Collection<int, AnnouncementRead> */
    public function getReads(): Collection { return $this->reads; }
}
