<?php

namespace App\Entity;

use App\Repository\WorkLogCorrectionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorkLogCorrectionRepository::class)]
#[ORM\Table(name: 'work_log_corrections')]
class WorkLogCorrection
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: WorkLog::class, inversedBy: 'corrections')]
    #[ORM\JoinColumn(name: 'work_log_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?WorkLog $workLog = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(name: 'original_logged_in_at', type: 'datetime')]
    private ?\DateTimeInterface $originalLoggedInAt = null;

    #[ORM\Column(name: 'original_logged_out_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $originalLoggedOutAt = null;

    #[ORM\Column(name: 'corrected_logged_in_at', type: 'datetime')]
    private ?\DateTimeInterface $correctedLoggedInAt = null;

    #[ORM\Column(name: 'corrected_logged_out_at', type: 'datetime')]
    private ?\DateTimeInterface $correctedLoggedOutAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'corrected_by', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $correctedBy = null;

    #[ORM\Column(name: 'correction_note', type: 'text', options: ['default' => ''])]
    private ?string $correctionNote = '';

    #[ORM\Column(name: 'corrected_at', type: 'datetime')]
    private ?\DateTimeInterface $correctedAt = null;

    public function __construct()
    {
        $this->correctedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getWorkLog(): ?WorkLog { return $this->workLog; }
    public function setWorkLog(?WorkLog $workLog): self { $this->workLog = $workLog; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getOriginalLoggedInAt(): ?\DateTimeInterface { return $this->originalLoggedInAt; }
    public function setOriginalLoggedInAt(\DateTimeInterface $originalLoggedInAt): self { $this->originalLoggedInAt = $originalLoggedInAt; return $this; }

    public function getOriginalLoggedOutAt(): ?\DateTimeInterface { return $this->originalLoggedOutAt; }
    public function setOriginalLoggedOutAt(?\DateTimeInterface $originalLoggedOutAt): self { $this->originalLoggedOutAt = $originalLoggedOutAt; return $this; }

    public function getCorrectedLoggedInAt(): ?\DateTimeInterface { return $this->correctedLoggedInAt; }
    public function setCorrectedLoggedInAt(\DateTimeInterface $correctedLoggedInAt): self { $this->correctedLoggedInAt = $correctedLoggedInAt; return $this; }

    public function getCorrectedLoggedOutAt(): ?\DateTimeInterface { return $this->correctedLoggedOutAt; }
    public function setCorrectedLoggedOutAt(\DateTimeInterface $correctedLoggedOutAt): self { $this->correctedLoggedOutAt = $correctedLoggedOutAt; return $this; }

    public function getCorrectedBy(): ?User { return $this->correctedBy; }
    public function setCorrectedBy(?User $correctedBy): self { $this->correctedBy = $correctedBy; return $this; }

    public function getCorrectionNote(): ?string { return $this->correctionNote; }
    public function setCorrectionNote(string $correctionNote): self { $this->correctionNote = $correctionNote; return $this; }

    public function getCorrectedAt(): ?\DateTimeInterface { return $this->correctedAt; }
    public function setCorrectedAt(\DateTimeInterface $correctedAt): self { $this->correctedAt = $correctedAt; return $this; }
}
