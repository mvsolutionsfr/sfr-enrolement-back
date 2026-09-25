<?php

namespace App\Entity;

use App\Repository\OrdersRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrdersRepository::class)]
class Orders
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $dernierStatut = null;

    #[ORM\Column(length: 50)]
    private ?string $resellerId = null;

    #[ORM\Column(length: 50)]
    private ?string $customerId = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\OneToMany(mappedBy: 'orders', targetEntity: OrderTransaction::class, orphanRemoval: true)]
    private Collection $transactions;

    #[ORM\ManyToOne(cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $estCreePar = null;

    #[ORM\ManyToOne(cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Fabricant $fabricant = null;

    #[ORM\OneToMany(mappedBy: 'orders', targetEntity: Terminal::class)]
    private Collection $terminauxEnroles;

    #[ORM\ManyToOne(inversedBy: 'orders',cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\ManyToOne(cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?PgmEnrolement $pgmEnrolement = null;

    #[ORM\ManyToOne(inversedBy: 'orders')]
    private ?Enseigne $enseigne = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ref = null;

    #[ORM\Column(length: 10)]
    private ?string $lastTypeTransaction = null;

    #[ORM\OneToMany(mappedBy: 'orders', targetEntity: Log::class, orphanRemoval: true)]
    private Collection $logs;


    public function __construct()
    {
        $this->transactions = new ArrayCollection();
        $this->terminauxEnroles = new ArrayCollection();
        $this->metrics = new ArrayCollection();
        $this->logs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDernierStatut(): ?int
    {
        return $this->dernierStatut;
    }

    public function setDernierStatut(int $dernierStatut): self
    {
        $this->dernierStatut = $dernierStatut;

        return $this;
    }

    public function getResellerId(): ?string
    {
        return $this->resellerId;
    }

    public function setResellerId(string $resellerId): self
    {
        $this->resellerId = $resellerId;

        return $this;
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

    public function getDateCreation(): ?\DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeInterface $dateCreation): self
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    /**
     * @return Collection<int, OrderTransaction>
     */
    public function getTransactions(): Collection
    {
        return $this->transactions;
    }

    public function addTransaction(OrderTransaction $transaction): self
    {
        if (!$this->transactions->contains($transaction)) {
            $this->transactions->add($transaction);
            $transaction->setOrders($this);
        }

        return $this;
    }

    public function removeTransaction(OrderTransaction $transaction): self
    {
        if ($this->transactions->removeElement($transaction)) {
            // set the owning side to null (unless already changed)
            if ($transaction->getOrders() === $this) {
                $transaction->setOrders(null);
            }
        }

        return $this;
    }

    public function getEstCreePar(): ?Utilisateur
    {
        return $this->estCreePar;
    }

    public function setEstCreePar(?Utilisateur $estCreePar): self
    {
        $this->estCreePar = $estCreePar;

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
     * @return Collection<int, Terminal>
     */
    public function getTerminauxEnroles(): Collection
    {
        return $this->terminauxEnroles;
    }

    public function addTerminauxEnrole(Terminal $terminauxEnrole): self
    {
        if (!$this->terminauxEnroles->contains($terminauxEnrole)) {
            $this->terminauxEnroles->add($terminauxEnrole);
            $terminauxEnrole->setOrders($this);
        }

        return $this;
    }

    public function removeTerminauxEnrole(Terminal $terminauxEnrole): self
    {
        if ($this->terminauxEnroles->removeElement($terminauxEnrole)) {
            // set the owning side to null (unless already changed)
            if ($terminauxEnrole->getOrders() === $this) {
                $terminauxEnrole->setOrders(null);
            }
        }

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

    public function getPgmEnrolement(): ?PgmEnrolement
    {
        return $this->pgmEnrolement;
    }

    public function setPgmEnrolement(?PgmEnrolement $pgmEnrolement): self
    {
        $this->pgmEnrolement = $pgmEnrolement;

        return $this;
    }

    public function getEnseigne(): ?Enseigne
    {
        return $this->enseigne;
    }

    public function setEnseigne(?Enseigne $enseigne): self
    {
        $this->enseigne = $enseigne;

        return $this;
    }

    public function getRef(): ?string
    {
        return $this->ref;
    }

    public function setRef(?string $ref): self
    {
        $this->ref = $ref;

        return $this;
    }

    public function getLastTypeTransaction(): ?string
    {
        return $this->lastTypeTransaction;
    }

    public function setLastTypeTransaction(string $lastTypeTransaction): static
    {
        $this->lastTypeTransaction = $lastTypeTransaction;

        return $this;
    }

    public function __toString()
    {
        return $this->ref;
    }

    /**
     * @return Collection<int, Log>
     */
    public function getLogs(): Collection
    {
        return $this->logs;
    }

    public function addLog(Log $log): static
    {
        if (!$this->logs->contains($log)) {
            $this->logs->add($log);
            $log->setOrders($this);
        }

        return $this;
    }

    public function removeLog(Log $log): static
    {
        if ($this->logs->removeElement($log)) {
            // set the owning side to null (unless already changed)
            if ($log->getOrders() === $this) {
                $log->setOrders(null);
            }
        }

        return $this;
    }
}
