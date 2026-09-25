<?php

namespace App\Entity;

use App\Repository\TerminalSuiviRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TerminalSuiviRepository::class)]
class TerminalSuivi
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private ?string $statut = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message = null;

    #[ORM\ManyToOne(inversedBy: 'terminalSuivis')]
    #[ORM\JoinColumn(nullable: false)]
    private ?OrderTransaction $transaction = null;

    #[ORM\ManyToOne(inversedBy: 'terminalSuivis')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Terminal $terminal = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): self
    {
        $this->statut = $statut;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function getTransaction(): ?OrderTransaction
    {
        return $this->transaction;
    }

    public function setTransaction(?OrderTransaction $transaction): self
    {
        $this->transaction = $transaction;

        return $this;
    }

    public function getTerminal(): ?Terminal
    {
        return $this->terminal;
    }

    public function setTerminal(?Terminal $terminal): self
    {
        $this->terminal = $terminal;

        return $this;
    }
}
