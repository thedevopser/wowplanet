<?php

declare(strict_types=1);

namespace App\Application\Health;

use RuntimeException;

final class UnreadableQueuedJobException extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self('A queued job payload is unreadable: '.$reason.'.');
    }
}
