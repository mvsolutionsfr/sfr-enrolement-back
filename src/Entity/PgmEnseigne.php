<?php

namespace App\Entity;

use App\Repository\PgmEnseigneRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PgmEnseigneRepository::class)]
class PgmEnseigne
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $resellerId = null;

    #[ORM\ManyToOne(inversedBy: 'pgmEnrolements',cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Enseigne $enseignes = null;

    #[ORM\ManyToOne(cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?PgmEnrolement $pgmEnrolement = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getResellerId(): ?string
    {
        return $this->resellerId;
    }

    public function setResellerId(?string $resellerId): self
    {
        $this->resellerId = $resellerId;

        return $this;
    }

    public function getEnseignes(): ?Enseigne
    {
        return $this->enseignes;
    }

    public function setEnseignes(?Enseigne $enseignes): self
    {
        $this->enseignes = $enseignes;

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
}
