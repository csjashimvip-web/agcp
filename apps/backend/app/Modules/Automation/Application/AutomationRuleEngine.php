<?php

namespace App\Modules\Automation\Application;

use App\Modules\Notifications\Application\NotificationChannelRouter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AutomationRuleEngine
{
    public function __construct(
        private readonly NotificationChannelRouter $notifications,
    ) {
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<int,array<string,mixed>>
     */
    public function dispatch(
        int $tenantId,
        string $eventType,
        array $payload,
    ): array {
        $rules = DB::table('automation_rules')
            ->where('tenant_id', $tenantId)
            ->where('event_type', $eventType)
            ->where('status', 'active')
            ->orderBy('priority')
            ->get();

        $results = [];

        foreach ($rules as $rule) {
            $runId = DB::table('automation_runs')->insertGetId([
                'tenant_id' => $tenantId,
                'automation_rule_id' => $rule->id,
                'run_uuid' => (string) Str::uuid(),
                'event_type' => $eventType,
                'status' => 'running',
                'input' => json_encode($payload, JSON_THROW_ON_ERROR),
                'started_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            try {
                $result = $this->execute($tenantId, $rule, $payload);

                DB::table('automation_runs')
                    ->where('id', $runId)
                    ->update([
                        'status' => 'completed',
                        'result' => json_encode($result, JSON_THROW_ON_ERROR),
                        'finished_at' => now(),
                        'updated_at' => now(),
                    ]);

                $results[] = [
                    'rule_id' => (int) $rule->id,
                    'status' => 'completed',
                    'result' => $result,
                ];
            } catch (\Throwable $e) {
                DB::table('automation_runs')
                    ->where('id', $runId)
                    ->update([
                        'status' => 'failed',
                        'error' => mb_substr($e->getMessage(), 0, 5000),
                        'finished_at' => now(),
                        'updated_at' => now(),
                    ]);

                $results[] = [
                    'rule_id' => (int) $rule->id,
                    'status' => 'failed',
                ];
            }
        }

        return $results;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function execute(
        int $tenantId,
        object $rule,
        array $payload,
    ): array {
        $config = json_decode(
            (string) ($rule->action_config ?? '{}'),
            true
        );

        if (! is_array($config)) {
            $config = [];
        }

        if ($rule->action_type === 'notify') {
            $userId = isset($payload['user_id'])
                ? (int) $payload['user_id']
                : null;

            $deliveryId = $this->notifications->queue(
                tenantId: $tenantId,
                userId: $userId,
                channelType: (string) ($config['channel'] ?? 'in_app'),
                subject: $config['subject'] ?? null,
                body: (string) ($config['body'] ?? $rule->name),
                metadata: [
                    'automation_rule_id' => $rule->id,
                    'event_type' => $rule->event_type,
                ],
            );

            return ['delivery_id' => $deliveryId];
        }

        return ['skipped' => 'unsupported_action_type'];
    }
}