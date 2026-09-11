<?php

namespace App\Entity;

use App\Repository\AttendanceOverrideRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AttendanceOverrideRepository::class)]
#[ORM\Table(name: 'attendance_overrides')]
class AttendanceOverride
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Id]
    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $day = null;

    #[ORM\Column(length: 50)]
    private ?string $status = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'updated_by', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $updatedBy = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->updatedAt = new \DateTime();
    }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getDay(): ?\DateTimeInterface { return $this->day; }
    public function setDay(\DateTimeInterface $day): self { $this->day = $day; return $this; }

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function getUpdatedBy(): ?User { return $this->updatedBy; }
    public function setUpdatedBy(?User $updatedBy): self { $this->updatedBy = $updatedBy; return $this; }

    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeInterface $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }
}
