<?php

namespace Modules\Identity\Application\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Modules\Identity\Infrastructure\Models\UserDevice;

class DeviceDescriptor
{
    public function touch(User $user, Request $request): UserDevice
    {
        $clientId = trim((string) $request->header('X-Device-ID'));
        $source = $clientId !== '' ? $clientId : ((string) $request->userAgent()).'|'.(string) $request->ip();
        $fingerprint = hash_hmac('sha256', $source, (string) config('app.key'));
        $userAgent = mb_substr((string) $request->userAgent(), 0, 1000);

        $device = UserDevice::query()->firstOrNew([
            'user_id' => $user->id,
            'fingerprint_hash' => $fingerprint,
        ]);

        if (! $device->exists) {
            $device->first_seen_at = now();
        }

        $device->fill([
            'name' => $this->name($userAgent),
            'platform' => $this->platform($userAgent),
            'browser' => $this->browser($userAgent),
            'last_ip' => $request->ip(),
            'last_seen_at' => now(),
            'metadata' => ['client_device_id_present' => $clientId !== ''],
        ])->save();

        return $device;
    }

    private function platform(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone'), str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Macintosh') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Unknown',
        };
    }

    private function browser(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'Unknown',
        };
    }

    private function name(string $ua): string
    {
        return $this->browser($ua).' on '.$this->platform($ua);
    }
}
