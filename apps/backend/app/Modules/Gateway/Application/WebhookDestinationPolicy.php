<?php

namespace App\Modules\Gateway\Application;

use RuntimeException;

final class WebhookDestinationPolicy
{
    public function assertAllowed(string $url): void
    {
        $parts = parse_url($url);

        if (! is_array($parts)
            || ($parts['scheme'] ?? null) !== 'https'
            || empty($parts['host'])) {
            throw new RuntimeException(
                'Webhook destination must use HTTPS and include a hostname.'
            );
        }

        $host = strtolower((string) $parts['host']);

        if ($host === 'localhost'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')) {
            throw new RuntimeException(
                'Local webhook destinations are not allowed.'
            );
        }

        $ips = gethostbynamel($host) ?: [];

        if ($ips === []) {
            throw new RuntimeException(
                'Webhook hostname could not be resolved.'
            );
        }

        foreach ($ips as $ip) {
            if (! filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            )) {
                throw new RuntimeException(
                    'Webhook destination resolves to a private/reserved address.'
                );
            }
        }
    }
}