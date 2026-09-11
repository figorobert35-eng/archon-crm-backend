<?php

namespace App\Entity;

use App\Repository\WorkLogRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorkLogRepository::class)]
#[ORM\Table(name: 'work_logs')]
class WorkLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(name: 'logged_in_at', type: 'datetime')]
    private ?\DateTimeInterface $loggedInAt = null;

    #[ORM\Column(name: 'logged_out_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $loggedOutAt = null;

    #[ORM\Column(type: 'text', options: ['default' => ''])]
    private ?string $note = '';

    #[ORM\Column(name: 'approval_status', length: 20, options: ['default' => 'pending'])]
    private ?string $approvalStatus = 'pending';

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'validated_by', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $validatedBy = null;

    #[ORM\Column(name: 'validated_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $validatedAt = null;

    #[ORM\Column(name: 'supervisor_note', type: 'text', options: ['default' => ''])]
    private ?string $supervisorNote = '';

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'workLog', targetEntity: WorkLogCorrection::class, cascade: ['persist', 'remove'])]
    private Collection $corrections;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->corrections = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getLoggedInAt(): ?\DateTimeInterface { return $this->loggedInAt; }
    public function setLoggedInAt(\DateTimeInterface $loggedInAt): self { $this->loggedInAt = $loggedInAt; return $this; }

    public function getLoggedOutAt(): ?\DateTimeInterface { return $this->loggedOutAt; }
    public function setLoggedOutAt(?\DateTimeInterface $loggedOutAt): self { $this->loggedOutAt = $loggedOutAt; return $this; }

    public function getNote(): ?string { return $this->note; }
    public function setNote(string $note): self { $this->note = $note; return $this; }

    public function getApprovalStatus(): ?string { return $this->approvalStatus; }
    public function setApprovalStatus(string $approvalStatus): self { $this->approvalStatus = $approvalStatus; return $this; }

    public function getValidatedBy(): ?User { return $this->validatedBy; }
    public function setValidatedBy(?User $validatedBy): self { $this->validatedBy = $validatedBy; return $this; }

    public function getValidatedAt(): ?\DateTimeInterface { return $this->validatedAt; }
    public function setValidatedAt(?\DateTimeInterface $validatedAt): self { $this->validatedAt = $validatedAt; return $this; }

    public function getSupervisorNote(): ?string { return $this->supervisorNote; }
    public function setSupervisorNote(string $supervisorNote): self { $this->supervisorNote = $supervisorNote; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): self { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeInterface $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }

    /** @return Collection<int, WorkLogCorrection> */
    public function getCorrections(): Collection { return $this->corrections; }
}
