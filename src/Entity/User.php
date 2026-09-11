<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $username = null;

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $salt = null;

    #[ORM\Column(length: 20, options: ['default' => 'agent'])]
    private ?string $role = 'agent';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => 4523.52])]
    private ?string $monthlySalary = '4523.52';

    #[ORM\Column(length: 20, options: ['default' => 'active'])]
    private ?string $accountStatus = 'active';

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $inactiveFrom = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'supervisor_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?self $supervisor = null;

    #[ORM\Column(length: 20, options: ['default' => ''])]
    private ?string $employeeCode = '';

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    // Leads assignés à cet utilisateur
    #[ORM\OneToMany(mappedBy: 'assignedTo', targetEntity: Lead::class)]
    private Collection $assignedLeads;

    // Campagnes auxquelles cet agent est rattaché (côté inverse du ManyToMany)
    #[ORM\ManyToMany(targetEntity: Campaign::class, mappedBy: 'agents')]
    private Collection $campaigns;

    public function __construct()
    {
        $this->createdAt    = new \DateTime();
        $this->assignedLeads = new ArrayCollection();
        $this->campaigns    = new ArrayCollection();
    }

    // --- Getters / Setters ---

    public function getId(): ?int { return $this->id; }

    public function getUsername(): ?string { return $this->username; }
    public function setUsername(string $username): self { $this->username = $username; return $this; }

    public function getPassword(): ?string { return $this->password; }
    public function setPassword(string $password): self { $this->password = $password; return $this; }

    public function getSalt(): ?string { return $this->salt; }
    public function setSalt(?string $salt): self { $this->salt = $salt; return $this; }

    public function getRole(): ?string { return $this->role; }
    public function setRole(string $role): self { $this->role = $role; return $this; }

    public function getMonthlySalary(): ?string { return $this->monthlySalary; }
    public function setMonthlySalary(string $monthlySalary): self { $this->monthlySalary = $monthlySalary; return $this; }

    public function getAccountStatus(): ?string { return $this->accountStatus; }
    public function setAccountStatus(string $accountStatus): self { $this->accountStatus = $accountStatus; return $this; }

    public function getInactiveFrom(): ?\DateTimeInterface { return $this->inactiveFrom; }
    public function setInactiveFrom(?\DateTimeInterface $inactiveFrom): self { $this->inactiveFrom = $inactiveFrom; return $this; }

    public function getSupervisor(): ?self { return $this->supervisor; }
    public function setSupervisor(?self $supervisor): self { $this->supervisor = $supervisor; return $this; }

    public function getEmployeeCode(): ?string { return $this->employeeCode; }
    public function setEmployeeCode(string $employeeCode): self { $this->employeeCode = $employeeCode; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): self { $this->createdAt = $createdAt; return $this; }

    /** @return Collection<int, Lead> */
    public function getAssignedLeads(): Collection { return $this->assignedLeads; }

    /** @return Collection<int, Campaign> */
    public function getCampaigns(): Collection { return $this->campaigns; }

    // --- Symfony Security ---

    public function getRoles(): array
    {
        return ['ROLE_' . strtoupper($this->role ?? 'AGENT')];
    }

    public function eraseCredentials(): void {}

    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }
}
