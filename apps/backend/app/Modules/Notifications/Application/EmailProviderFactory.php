<?php

namespace App\Modules\Notifications\Application;

use App\Modules\Notifications\Application\Contracts\EmailProvider;
use App\Modules\Notifications\Infrastructure\LaravelMailProvider;
use App\Modules\Notifications\Infrastructure\NullEmailProvider;

final class EmailProviderFactory
{
    public function make(string $driver): EmailProvider
    {
        return match ($driver) {
            'laravel_mail' => app(LaravelMailProvider::class),
            default => app(NullEmailProvider::class),
        };
    }
}