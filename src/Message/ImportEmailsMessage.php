<?php

namespace App\Message;

class ImportEmailsMessage
{
    private string $userIdentifier;

    public function __construct(string $userIdentifier)
    {
        $this->userIdentifier = $userIdentifier;
    }

    public function getUserIdentifier(): string
    {
        return $this->userIdentifier;
    }
} 