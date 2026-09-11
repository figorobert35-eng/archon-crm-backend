<?php

namespace App\Entity;

use App\Repository\CampaignRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CampaignRepository::class)]
#[ORM\Table(name: 'campaigns')]
class Campaign
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $name = null;

    #[ORM\Column(type: 'text', options: ['default' => ''])]
    private ?string $description = '';

    #[ORM\Column(type: 'text', options: ['default' => ''])]
    private ?string $script = '';

    #[ORM\Column(length: 7, options: ['default' => '#8f1d14'])]
    private ?string $color = '#8f1d14';

    #[ORM\Column(name: 'codification_statuses', type: 'text', options: ['default' => ''])]
    private ?string $codificationStatuses = '';

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private ?bool $active = true;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private ?bool $archived = false;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'campaigns')]
    #[ORM\JoinTable(name: 'campaign_agents')]
    #[ORM\JoinColumn(name: 'campaign_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'user_id', referencedColumnName: 'id')]
    private Collection $agents;

    #[ORM\OneToMany(mappedBy: 'campaign', targetEntity: Lead::class)]
    private Collection $leads;

    #[ORM\OneToMany(mappedBy: 'campaign', targetEntity: InjectionBatch::class)]
    private Collection $injectionBatches;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->agents = new ArrayCollection();
        $this->leads = new ArrayCollection();
        $this->injectionBatches = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(string $description): self { $this->description = $description; return $this; }

    public function getScript(): ?string { return $this->script; }
    public function setScript(string $script): self { $this->script = $script; return $this; }

    public function getColor(): ?string { return $this->color; }
    public function setColor(string $color): self { $this->color = $color; return $this; }

    public function getCodificationStatuses(): ?string { return $this->codificationStatuses; }
    public function setCodificationStatuses(string $codificationStatuses): self { $this->codificationStatuses = $codificationStatuses; return $this; }

    public function isActive(): ?bool { return $this->active; }
    public function setActive(bool $active): self { $this->active = $active; return $this; }

    public function isArchived(): ?bool { return $this->archived; }
    public function setArchived(bool $archived): self { $this->archived = $archived; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): self { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeInterface $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }

    /** @return Collection<int, User> */
    public function getAgents(): Collection { return $this->agents; }
    public function addAgent(User $user): self
    {
        if (!$this->agents->contains($user)) {
            $this->agents->add($user);
        }
        return $this;
    }
    public function removeAgent(User $user): self
    {
        $this->agents->removeElement($user);
        return $this;
    }

    /** @return Collection<int, Lead> */
    public function getLeads(): Collection { return $this->leads; }

    /** @return Collection<int, InjectionBatch> */
    public function getInjectionBatches(): Collection { return $this->injectionBatches; }
}
