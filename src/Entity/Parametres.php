<?php

namespace App\Entity;

use App\Repository\ParametresRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ParametresRepository::class)]
class Parametres
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?bool $paramBool = null;

    #[ORM\Column(length: 255)]
    private ?string $paramString = null;

    #[ORM\Column(nullable: true)]
    private ?int $paramInt = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isParamBool(): ?bool
    {
        return $this->paramBool;
    }

    public function setParamBool(?bool $paramBool): static
    {
        $this->paramBool = $paramBool;

        return $this;
    }

    public function getParamString(): ?string
    {
        return $this->paramString;
    }

    public function setParamString(?string $paramString): static
    {
        $this->paramString = $paramString;

        return $this;
    }

    public function getParamInt(): ?int
    {
        return $this->paramInt;
    }

    public function setParamInt(?int $paramInt): static
    {
        $this->paramInt = $paramInt;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }
}
