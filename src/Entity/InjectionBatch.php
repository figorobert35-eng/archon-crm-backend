<?php

namespace App\Entity;

use App\Repository\InjectionBatchRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InjectionBatchRepository::class)]
#[ORM\Table(name: 'injection_batches')]
class InjectionBatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'injection_date', type: 'date')]
    private ?\DateTimeInterface $injectionDate = null;

    #[ORM\ManyToOne(targetEntity: Campaign::class, inversedBy: 'injectionBatches')]
    #[ORM\JoinColumn(name: 'campaign_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Campaign $campaign = null;

    #[ORM\Column(length: 100, options: ['default' => ''])]
    private ?string $source = '';

    #[ORM\Column(name: 'created_by', length: 100, options: ['default' => ''])]
    private ?string $createdBy = '';

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\OneToMany(mappedBy: 'batch', targetEntity: Lead::class)]
    private Collection $leads;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->leads = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getInjectionDate(): ?\DateTimeInterface { return $this->injectionDate; }
    public function setInjectionDate(\DateTimeInterface $injectionDate): self { $this->injectionDate = $injectionDate; return $this; }

    public function getCampaign(): ?Campaign { return $this->campaign; }
    public function setCampaign(?Campaign $campaign): self { $this->campaign = $campaign; return $this; }

    public function getSource(): ?string { return $this->source; }
    public function setSource(string $source): self { $this->source = $source; return $this; }

    public function getCreatedBy(): ?string { return $this->createdBy; }
    public function setCreatedBy(string $createdBy): self { $this->createdBy = $createdBy; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): self { $this->createdAt = $createdAt; return $this; }

    /** @return Collection<int, Lead> */
    public function getLeads(): Collection { return $this->leads; }
}
