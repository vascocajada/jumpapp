<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Config
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: User::class, inversedBy: 'config', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $useGmailListUnsubscribe = true;

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(User $user): static { $this->user = $user; return $this; }
    public function getUseGmailListUnsubscribe(): bool { return $this->useGmailListUnsubscribe; }
    public function setUseGmailListUnsubscribe(bool $value): static { $this->useGmailListUnsubscribe = $value; return $this; }
} 