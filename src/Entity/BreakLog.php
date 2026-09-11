<?php

namespace App\Entity;

use App\Repository\BreakLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BreakLogRepository::class)]
#[ORM\Table(name: 'break_logs')]
class BreakLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(name: 'break_type', length: 20)]
    private ?string $breakType = null;

    #[ORM\Column(name: 'started_at', type: 'datetime')]
    private ?\DateTimeInterface $startedAt = null;

    #[ORM\Column(name: 'ended_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $endedAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getBreakType(): ?string { return $this->breakType; }
    public function setBreakType(string $breakType): self { $this->breakType = $breakType; return $this; }

    public function getStartedAt(): ?\DateTimeInterface { return $this->startedAt; }
    public function setStartedAt(\DateTimeInterface $startedAt): self { $this->startedAt = $startedAt; return $this; }

    public function getEndedAt(): ?\DateTimeInterface { return $this->endedAt; }
    public function setEndedAt(?\DateTimeInterface $endedAt): self { $this->endedAt = $endedAt; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): self { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeInterface $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }
}
