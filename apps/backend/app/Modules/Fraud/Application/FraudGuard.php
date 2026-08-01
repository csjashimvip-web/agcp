<?php

namespace App\Modules\Fraud\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class FraudGuard
{
    public function assertCheckoutAllowed(
        int $tenantId,
        int $userId,
        int $quoteTotalMinor,
        ?string $fingerprint = null,
    ): void {
        $rules = DB::table('fraud_rules')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('priority')
            ->get();

        if ($rules->isEmpty()) {
            return;
        }

        $score = 0;
        $decision = 'allow';
        $reasons = [];

        foreach ($rules as $rule) {
            $metricValue = match ((string) $rule->metric) {
                'order_total_minor' => $quoteTotalMinor,
                'orders_10m' => DB::table('orders')
                    ->where('tenant_id', $tenantId)
                    ->where('user_id', $userId)
                    ->where('created_at', '>=', now()->subMinutes(10))
                    ->count(),
                'cancelled_orders_24h' => DB::table('orders')
                    ->where('tenant_id', $tenantId)
                    ->where('user_id', $userId)
                    ->where('status', 'cancelled')
                    ->where('created_at', '>=', now()->subDay())
                    ->count(),
                default => 0,
            };

            if ($metricValue < (int) $rule->threshold_value) {
                continue;
            }

            $score += (int) $rule->risk_points;

            $reasons[] = [
                'rule_id' => (int) $rule->id,
                'code' => (string) $rule->code,
                'metric' => (string) $rule->metric,
                'value' => $metricValue,
                'threshold' => (int) $rule->threshold_value,
                'action' => (string) $rule->action,
            ];

            if ($rule->action === 'block') {
                $decision = 'block';
                break;
            }

            if ($rule->action === 'review' && $decision === 'allow') {
                $decision = 'review';
            }
        }

        if ($reasons === []) {
            return;
        }

        DB::table('fraud_assessments')->insert([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'assessment_uuid' => (string) Str::uuid(),
            'risk_score' => min(100, $score),
            'decision' => $decision,
            'quote_total_minor' => $quoteTotalMinor,
            'fingerprint_hash' => $fingerprint
                ? hash('sha256', $fingerprint)
                : null,
            'reasons' => json_encode($reasons, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($decision === 'block') {
            throw ValidationException::withMessages([
                'checkout' => [
                    'Checkout was blocked by the tenant risk policy.',
                ],
            ]);
        }

        if ($decision === 'review') {
            throw ValidationException::withMessages([
                'checkout' => [
                    'Checkout requires manual review before processing.',
                ],
            ]);
        }
    }
}