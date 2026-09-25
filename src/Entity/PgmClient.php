<?php

namespace App\Entity;

use App\Repository\PgmClientRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PgmClientRepository::class)]
class PgmClient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $customerId = null;

    #[ORM\Column]
    private ?bool $actif = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(nullable: true)]
    private ?bool $gestionSfr = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?PgmEnrolement $pgmEnrolement = null;

    #[ORM\ManyToOne(inversedBy: 'pgmEnrolements')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomerId(): ?string
    {
        return $this->customerId;
    }

    public function setCustomerId(string $customerId): self
    {
        $this->customerId = $customerId;

        return $this;
    }

    public function isActif(): ?bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): self
    {
        $this->actif = $actif;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function isGestionSfr(): ?bool
    {
        return $this->gestionSfr;
    }

    public function setGestionSfr(?bool $gestionSfr): self
    {
        $this->gestionSfr = $gestionSfr;

        return $this;
    }

    public function getPgmEnrolement(): ?PgmEnrolement
    {
        return $this->pgmEnrolement;
    }

    public function setPgmEnrolement(?PgmEnrolement $pgmEnrolement): self
    {
        $this->pgmEnrolement = $pgmEnrolement;

        return $this;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): self
    {
        $this->client = $client;

        return $this;
    }
}
