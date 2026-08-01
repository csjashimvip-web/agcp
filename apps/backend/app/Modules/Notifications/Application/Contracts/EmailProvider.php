<?php

namespace App\Modules\Notifications\Application\Contracts;

interface EmailProvider
{
    /**
     * @param array<string,mixed> $config
     */
    public function send(
        string $recipient,
        ?string $subject,
        string $body,
        array $config = [],
    ): void;
}