<?php

namespace App\Entity;

use App\Repository\AppSettingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AppSettingRepository::class)]
#[ORM\Table(name: 'app_settings')]
class AppSetting
{
    #[ORM\Id]
    #[ORM\Column(length: 255)]
    private ?string $key = null;

    #[ORM\Column(type: 'text')]
    private ?string $value = null;

    public function getKey(): ?string { return $this->key; }
    public function setKey(string $key): self { $this->key = $key; return $this; }

    public function getValue(): ?string { return $this->value; }
    public function setValue(string $value): self { $this->value = $value; return $this; }
}
