<?php

namespace App\Entity;

use App\Repository\AnnouncementReadRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AnnouncementReadRepository::class)]
#[ORM\Table(name: 'announcement_reads')]
class AnnouncementRead
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Announcement::class, inversedBy: 'reads')]
    #[ORM\JoinColumn(name: 'announcement_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Announcement $announcement = null;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(name: 'acknowledged_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $acknowledgedAt = null;

    #[ORM\Column(name: 'reminder_count', type: 'integer', options: ['default' => 0])]
    private int $reminderCount = 0;

    #[ORM\Column(name: 'remind_after', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $remindAfter = null;

    #[ORM\Column(name: 'last_seen_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastSeenAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getAnnouncement(): ?Announcement { return $this->announcement; }
    public function setAnnouncement(?Announcement $announcement): self { $this->announcement = $announcement; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getAcknowledgedAt(): ?\DateTimeInterface { return $this->acknowledgedAt; }
    public function setAcknowledgedAt(?\DateTimeInterface $acknowledgedAt): self { $this->acknowledgedAt = $acknowledgedAt; return $this; }

    public function getReminderCount(): int { return $this->reminderCount; }
    public function setReminderCount(int $reminderCount): self { $this->reminderCount = $reminderCount; return $this; }

    public function getRemindAfter(): ?\DateTimeInterface { return $this->remindAfter; }
    public function setRemindAfter(?\DateTimeInterface $remindAfter): self { $this->remindAfter = $remindAfter; return $this; }

    public function getLastSeenAt(): ?\DateTimeInterface { return $this->lastSeenAt; }
    public function setLastSeenAt(?\DateTimeInterface $lastSeenAt): self { $this->lastSeenAt = $lastSeenAt; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): self { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeInterface $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }
}
