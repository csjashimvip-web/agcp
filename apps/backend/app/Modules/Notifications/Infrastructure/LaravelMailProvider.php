<?php

namespace App\Modules\Notifications\Infrastructure;

use App\Modules\Notifications\Application\Contracts\EmailProvider;
use Illuminate\Support\Facades\Mail;

final class LaravelMailProvider implements EmailProvider
{
    public function send(
        string $recipient,
        ?string $subject,
        string $body,
        array $config = [],
    ): void {
        Mail::raw($body, function ($message) use (
            $recipient,
            $subject,
            $config,
        ): void {
            $message->to($recipient)
                ->subject($subject ?: 'AGCP Notification');

            if (! empty($config['from_address'])) {
                $message->from(
                    $config['from_address'],
                    $config['from_name'] ?? 'AGCP'
                );
            }
        });
    }
}