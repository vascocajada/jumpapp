<?php
namespace App\Message;

class UnsubscribeEmailMessage
{
    private int $emailId;

    public function __construct(int $emailId)
    {
        $this->emailId = $emailId;
    }

    public function getEmailId(): int
    {
        return $this->emailId;
    }
} 