<?php

namespace App\Entity;

use App\Repository\TerminalRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TerminalRepository::class)]
class Terminal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $numeroIMEI = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $numeroSerie = null;

    #[ORM\ManyToOne(inversedBy: 'terminauxEnroles')]
    private ?Orders $orders = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Fabricant $fabricant = null;

    #[ORM\OneToMany(mappedBy: 'terminal', targetEntity: TerminalSuivi::class, orphanRemoval: true)]
    private Collection $terminalSuivis;

    #[ORM\OneToMany(mappedBy: 'terminal', targetEntity: Enrolement::class, orphanRemoval: true)]
    private Collection $enrolements;

    #[ORM\Column]
    private ?int $statut = null;

    #[ORM\ManyToOne(cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?PgmEnrolement $programme = null;

    public function __construct()
    {
        $this->terminalSuivis = new ArrayCollection();
        $this->enrolements = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumeroIMEI(): ?string
    {
        return $this->numeroIMEI;
    }

    public function setNumeroIMEI(?string $numeroIMEI): self
    {
        $this->numeroIMEI = $numeroIMEI;

        return $this;
    }

    public function getNumeroSerie(): ?string
    {
        return $this->numeroSerie;
    }

    public function setNumeroSerie(?string $numeroSerie): self
    {
        $this->numeroSerie = $numeroSerie;

        return $this;
    }

    public function getOrders(): ?Orders
    {
        return $this->orders;
    }

    public function setOrders(?Orders $orders): self
    {
        $this->orders = $orders;

        return $this;
    }

    public function getFabricant(): ?Fabricant
    {
        return $this->fabricant;
    }

    public function setFabricant(?Fabricant $fabricant): self
    {
        $this->fabricant = $fabricant;

        return $this;
    }

    /**
     * @return Collection<int, TerminalSuivi>
     */
    public function getTerminalSuivis(): Collection
    {
        return $this->terminalSuivis;
    }

    public function addTerminalSuivi(TerminalSuivi $terminalSuivi): self
    {
        if (!$this->terminalSuivis->contains($terminalSuivi)) {
            $this->terminalSuivis->add($terminalSuivi);
            $terminalSuivi->setTerminal($this);
        }

        return $this;
    }

    public function removeTerminalSuivi(TerminalSuivi $terminalSuivi): self
    {
        if ($this->terminalSuivis->removeElement($terminalSuivi)) {
            // set the owning side to null (unless already changed)
            if ($terminalSuivi->getTerminal() === $this) {
                $terminalSuivi->setTerminal(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Enrolement>
     */
    public function getEnrolements(): Collection
    {
        return $this->enrolements;
    }

    public function addEnrolement(Enrolement $enrolement): self
    {
        if (!$this->enrolements->contains($enrolement)) {
            $this->enrolements->add($enrolement);
            $enrolement->setTerminal($this);
        }

        return $this;
    }

    public function removeEnrolement(Enrolement $enrolement): self
    {
        if ($this->enrolements->removeElement($enrolement)) {
            // set the owning side to null (unless already changed)
            if ($enrolement->getTerminal() === $this) {
                $enrolement->setTerminal(null);
            }
        }

        return $this;
    }

    public function getStatut(): ?int
    {
        return $this->statut;
    }

    public function setStatut(int $statut): self
    {
        $this->statut = $statut;

        return $this;
    }

    public function getProgramme(): ?PgmEnrolement
    {
        return $this->programme;
    }

    public function setProgramme(?PgmEnrolement $programme): self
    {
        $this->programme = $programme;

        return $this;
    }
}
