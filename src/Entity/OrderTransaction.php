<?php

namespace App\Entity;

use App\Repository\OrderTransactionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderTransactionRepository::class)]
class OrderTransaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $statut = null;

    #[ORM\Column(length: 10)]
    private ?string $typeTransaction = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $statutTransacPgm = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $statutMsgPgm = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\ManyToOne(inversedBy: 'transactions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Orders $orders = null;

    #[ORM\OneToMany(mappedBy: 'transaction', targetEntity: Requete::class, orphanRemoval: true)]
    private Collection $requetes;

    #[ORM\ManyToOne(cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $estCreePar = null;

    #[ORM\OneToMany(mappedBy: 'transaction', targetEntity: TerminalSuivi::class, orphanRemoval: true)]
    private Collection $terminalSuivis;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $transactionId = null;

    #[ORM\Column]
    private ?bool $lastTransacOnType = null;

    public function __construct()
    {
        $this->requetes = new ArrayCollection();
        $this->terminalSuivisSuivis = new ArrayCollection();
        $this->terminalSuivis = new ArrayCollection();
        $this->description = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getTypeTransaction(): ?string
    {
        return $this->typeTransaction;
    }

    public function setTypeTransaction(string $typeTransaction): self
    {
        $this->typeTransaction = $typeTransaction;

        return $this;
    }

    public function getStatutTransacPgm(): ?string
    {
        return $this->statutTransacPgm;
    }

    public function setStatutTransacPgm(?string $statutTransacPgm): self
    {
        $this->statutTransacPgm = $statutTransacPgm;

        return $this;
    }

    public function getStatutMsgPgm(): ?string
    {
        return $this->statutMsgPgm;
    }

    public function setStatutMsgPgm(?string $statutMsgPgm): self
    {
        $this->statutMsgPgm = $statutMsgPgm;

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

    public function getOrders(): ?Orders
    {
        return $this->orders;
    }

    public function setOrders(?Orders $orders): self
    {
        $this->orders = $orders;

        return $this;
    }

    /**
     * @return Collection<int, Requete>
     */
    public function getRequetes(): Collection
    {
        return $this->requetes;
    }

    public function addRequete(Requete $requete): self
    {
        if (!$this->requetes->contains($requete)) {
            $this->requetes->add($requete);
            $requete->setTransaction($this);
        }

        return $this;
    }

    public function removeRequete(Requete $requete): self
    {
        if ($this->requetes->removeElement($requete)) {
            // set the owning side to null (unless already changed)
            if ($requete->getTransaction() === $this) {
                $requete->setTransaction(null);
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
            $terminalSuivi->setTransaction($this);
        }

        return $this;
    }

    public function removeTerminalSuivi(TerminalSuivi $terminalSuivi): self
    {
        if ($this->terminalSuivis->removeElement($terminalSuivi)) {
            // set the owning side to null (unless already changed)
            if ($terminalSuivi->getTransaction() === $this) {
                $terminalSuivi->setTransaction(null);
            }
        }

        return $this;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    public function setTransactionId(?string $transactionId): self
    {
        $this->transactionId = $transactionId;

        return $this;
    }

    public function isLastTransacOnType(): ?bool
    {
        return $this->lastTransacOnType;
    }

    public function setLastTransacOnType(bool $lastTransacOnType): static
    {
        $this->lastTransacOnType = $lastTransacOnType;

        return $this;
    }

}
