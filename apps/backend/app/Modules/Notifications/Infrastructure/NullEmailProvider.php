<?php

namespace App\Modules\Notifications\Infrastructure;

use App\Modules\Notifications\Application\Contracts\EmailProvider;

final class NullEmailProvider implements EmailProvider
{
    public function send(
        string $recipient,
        ?string $subject,
        string $body,
        array $config = [],
    ): void {
        // Intentional no-op provider for non-external environments.
    }
}