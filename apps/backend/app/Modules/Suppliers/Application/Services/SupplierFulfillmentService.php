<?php
namespace Modules\Suppliers\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\Audit\Application\AuditLogger;
use Modules\Commerce\Domain\Enums\OrderStatus;
use Modules\Commerce\Infrastructure\Models\Order;
use Modules\Commerce\Infrastructure\Models\OrderItem;
use Modules\Commerce\Infrastructure\Models\OrderStatusHistory;
use Modules\Suppliers\Application\Jobs\SubmitSupplierOrder;
use Modules\Suppliers\Domain\Enums\SupplierOrderStatus;
use Modules\Suppliers\Infrastructure\Models\SupplierAttempt;
use Modules\Suppliers\Infrastructure\Models\SupplierOrder;
use Modules\Wallet\Application\Services\LedgerService;
use Modules\Wallet\Application\Services\WalletService;
use Modules\Wallet\Domain\Enums\AccountType;
use Modules\Wallet\Domain\Enums\LedgerDirection;
use Throwable;

final class SupplierFulfillmentService
{
    public function __construct(
        private readonly SupplierRoutingEngine $router,
        private readonly SupplierProviderRegistry $providers,
        private readonly SupplierHealthService $health,
        private readonly LedgerService $ledger,
        private readonly WalletService $wallets,
        private readonly AuditLogger $audit,
    ) {}

    /** @return list<SupplierOrder> */
    public function createForOrder(Order $order): array
    {
        $created = DB::transaction(function () use ($order): array {
            $lockedOrder = Order::query()
                ->with(['items.variant.item'])
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedOrder->status, [OrderStatus::Confirmed, OrderStatus::Processing], true)) {
                return [];
            }

            $supplierOrders = [];
            foreach ($lockedOrder->items as $item) {
                if ($item->variant?->item?->fulfillment_mode !== 'supplier_api') continue;
                $supplierOrders[] = SupplierOrder::query()->firstOrCreate(
                    ['order_item_id' => $item->id],
                    [
                        'tenant_id' => $lockedOrder->tenant_id,
                        'order_id' => $lockedOrder->id,
                        'client_reference' => $lockedOrder->number.'-'.$item->id,
                        'status' => SupplierOrderStatus::Queued,
                        'attempts' => 0,
                        'max_attempts' => 3,
                        'request_payload' => $item->configuration ?? [],
                        'queued_at' => now(),
                    ],
                );
            }

            return $supplierOrders;
        }, 5);

        foreach ($created as $supplierOrder) {
            if (in_array($supplierOrder->status, [SupplierOrderStatus::Queued, SupplierOrderStatus::Retrying], true)) {
                SubmitSupplierOrder::dispatch($supplierOrder->id)->afterCommit()->onQueue('supplier');
            }
        }

        return $created;
    }

    public function submit(string $supplierOrderId): SupplierOrder
    {
        try {
            $prepared = DB::transaction(function () use ($supplierOrderId): array {
            $supplierOrder = SupplierOrder::query()
                ->with(['orderItem.variant.item', 'attemptLogs'])
                ->whereKey($supplierOrderId)
                ->lockForUpdate()
                ->firstOrFail();
            if ($supplierOrder->status->terminal() || in_array($supplierOrder->status, [SupplierOrderStatus::Submitting, SupplierOrderStatus::Submitted, SupplierOrderStatus::Processing], true)) {
                return ['skip' => true, 'order' => $supplierOrder];
            }

            $excluded = $supplierOrder->attemptLogs->where('status', 'failed')->pluck('supplier_account_id')->unique()->values()->all();
            $route = $this->router->select($supplierOrder, $excluded);
            $service = $route['service'];
            $attemptNumber = (int) $supplierOrder->attempts + 1;
            $maxAttempts = max(1, count($route['candidates']), (int) ($service->max_retries ?? $service->supplier->max_retries ?? 3));
            $payload = $this->mapFields($supplierOrder->request_payload ?? [], $service->field_map ?? []);

            $attempt = SupplierAttempt::query()->create([
                'supplier_order_id' => $supplierOrder->id,
                'supplier_account_id' => $service->supplier_account_id,
                'supplier_service_id' => $service->id,
                'attempt_number' => $attemptNumber,
                'status' => 'submitting',
                'routing_score' => $route['score'],
                'request_payload' => $payload,
                'started_at' => now(),
            ]);
            $supplierOrder->forceFill([
                'supplier_account_id' => $service->supplier_account_id,
                'supplier_service_id' => $service->id,
                'routing_profile_id' => $route['profile']?->id,
                'status' => SupplierOrderStatus::Submitting,
                'attempts' => $attemptNumber,
                'max_attempts' => $maxAttempts,
                'request_payload' => $payload,
                'error_code' => null,
                'error_message' => null,
            ])->save();

            return ['skip' => false, 'order' => $supplierOrder, 'attempt' => $attempt, 'service' => $service, 'payload' => $payload];
            }, 5);
        } catch (ValidationException|InvalidArgumentException $exception) {
            return $this->failBeforeSubmission($supplierOrderId, $exception);
        }

        if ($prepared['skip']) return $prepared['order'];
        $supplierOrder = $prepared['order'];
        $attempt = $prepared['attempt'];
        $service = $prepared['service'];
        $started = hrtime(true);

        try {
            $response = $this->providers->get($service->supplier->provider)->submit(
                $service->supplier,
                $service->supplier_service_code,
                $supplierOrder->client_reference,
                $prepared['payload'],
            );
        } catch (Throwable $exception) {
            $latency = (int) round((hrtime(true) - $started) / 1_000_000);
            $this->health->recordFailure($service->supplier);
            return $this->handleFailure($supplierOrder->id, $attempt->id, $exception, $latency);
        }

        $latency = (int) round((hrtime(true) - $started) / 1_000_000);
        $providerStatus = strtolower((string) ($response['status'] ?? 'processing'));
        if (in_array($providerStatus, ['failed', 'rejected', 'canceled'], true)) $this->health->recordFailure($service->supplier);
        else $this->health->recordSuccess($service->supplier, $latency);
        return $this->applyProviderResponse($supplierOrder->id, $attempt->id, $response, $latency);
    }

    public function poll(string $supplierOrderId): SupplierOrder
    {
        $supplierOrder = SupplierOrder::query()->with(['supplier', 'service'])->findOrFail($supplierOrderId);
        if (! in_array($supplierOrder->status, [SupplierOrderStatus::Submitted, SupplierOrderStatus::Processing], true) || ! $supplierOrder->supplier_reference) return $supplierOrder;

        $started = hrtime(true);
        try {
            $response = $this->providers->get($supplierOrder->supplier->provider)->status($supplierOrder->supplier, $supplierOrder->supplier_reference);
        } catch (Throwable $exception) {
            $this->health->recordFailure($supplierOrder->supplier);
            return $this->handlePollFailure($supplierOrder->id, $exception);
        }

        $latency = (int) round((hrtime(true) - $started) / 1_000_000);
        $providerStatus = strtolower((string) ($response['status'] ?? 'processing'));
        if (in_array($providerStatus, ['failed', 'rejected', 'canceled'], true)) $this->health->recordFailure($supplierOrder->supplier);
        else $this->health->recordSuccess($supplierOrder->supplier, $latency);
        return $this->applyProviderResponse($supplierOrder->id, null, $response, $latency);
    }

    public function retry(SupplierOrder $supplierOrder): SupplierOrder
    {
        if (! in_array($supplierOrder->status, [SupplierOrderStatus::Failed, SupplierOrderStatus::Retrying], true)) return $supplierOrder;
        $supplierOrder->forceFill([
            'status' => SupplierOrderStatus::Retrying,
            'failed_at' => null,
            'error_code' => null,
            'error_message' => null,
        ])->save();
        SubmitSupplierOrder::dispatch($supplierOrder->id)->afterCommit()->onQueue('supplier');
        return $supplierOrder->fresh();
    }

    private function applyProviderResponse(string $supplierOrderId, ?string $attemptId, array $response, int $latency): SupplierOrder
    {
        return DB::transaction(function () use ($supplierOrderId, $attemptId, $response, $latency): SupplierOrder {
            $supplierOrder = SupplierOrder::query()->with(['order.items', 'orderItem'])->whereKey($supplierOrderId)->lockForUpdate()->firstOrFail();
            $status = strtolower((string) ($response['status'] ?? 'processing'));
            $completed = in_array($status, ['completed', 'success', 'done'], true);
            $failed = in_array($status, ['failed', 'rejected', 'canceled'], true);

            if ($attemptId) {
                SupplierAttempt::query()->whereKey($attemptId)->update([
                    'status' => $completed ? 'completed' : ($failed ? 'failed' : 'submitted'),
                    'latency_ms' => $latency,
                    'response_payload' => $response,
                    'finished_at' => now(),
                ]);
            }

            if ($failed) {
                return $this->markTerminalFailure($supplierOrder, 'SUPPLIER_REJECTED', (string) ($response['message'] ?? 'Supplier rejected the order.'));
            }

            $supplierOrder->forceFill([
                'supplier_reference' => $response['supplier_reference'] ?? $supplierOrder->supplier_reference,
                'status' => $completed ? SupplierOrderStatus::Completed : SupplierOrderStatus::Processing,
                'response_payload' => $response['raw'] ?? $response,
                'result_payload' => $response['result'] ?? null,
                'submitted_at' => $supplierOrder->submitted_at ?? now(),
                'next_poll_at' => $completed ? null : now()->addSeconds(30),
                'completed_at' => $completed ? now() : null,
                'error_code' => null,
                'error_message' => null,
            ])->save();
            $supplierOrder->orderItem->forceFill(['status' => $completed ? 'completed' : 'processing'])->save();
            $this->refreshParentOrder($supplierOrder->order);
            $this->audit->record('supplier.order.'.($completed ? 'completed' : 'submitted'), SupplierOrder::class, $supplierOrder->id, [
                'supplier_reference' => $supplierOrder->supplier_reference,
                'supplier_id' => $supplierOrder->supplier_account_id,
            ], [], $supplierOrder->tenant_id);

            return $supplierOrder->fresh(['supplier', 'service', 'attemptLogs', 'decisions', 'orderItem']);
        }, 5);
    }

    private function handleFailure(string $supplierOrderId, ?string $attemptId, Throwable $exception, int $latency): SupplierOrder
    {
        return DB::transaction(function () use ($supplierOrderId, $attemptId, $exception, $latency): SupplierOrder {
            $supplierOrder = SupplierOrder::query()->with(['order.items', 'orderItem', 'attemptLogs'])->whereKey($supplierOrderId)->lockForUpdate()->firstOrFail();
            if ($attemptId) {
                SupplierAttempt::query()->whereKey($attemptId)->update([
                    'status' => 'failed',
                    'latency_ms' => $latency,
                    'error_code' => class_basename($exception),
                    'error_message' => mb_substr($exception->getMessage(), 0, 2000),
                    'finished_at' => now(),
                ]);
            }

            $failedSupplierIds = $supplierOrder->attemptLogs()->where('status', 'failed')->pluck('supplier_account_id')->unique()->all();
            $remaining = $supplierOrder->attempts < $supplierOrder->max_attempts
                && \Modules\Suppliers\Infrastructure\Models\SupplierService::query()
                    ->where('tenant_id', $supplierOrder->tenant_id)
                    ->where('catalog_variant_id', $supplierOrder->orderItem->catalog_variant_id)
                    ->where('enabled', true)
                    ->whereNotIn('supplier_account_id', $failedSupplierIds)
                    ->exists();

            if ($remaining) {
                $supplierOrder->forceFill([
                    'status' => SupplierOrderStatus::Retrying,
                    'error_code' => class_basename($exception),
                    'error_message' => mb_substr($exception->getMessage(), 0, 2000),
                    'next_poll_at' => now()->addSeconds(min(300, 2 ** min($supplierOrder->attempts, 8))),
                ])->save();
                SubmitSupplierOrder::dispatch($supplierOrder->id)->delay($supplierOrder->next_poll_at)->afterCommit()->onQueue('supplier');
                return $supplierOrder->fresh(['attemptLogs', 'decisions']);
            }

            return $this->markTerminalFailure($supplierOrder, class_basename($exception), $exception->getMessage());
        }, 5);
    }

    private function failBeforeSubmission(string $supplierOrderId, Throwable $exception): SupplierOrder
    {
        return DB::transaction(function () use ($supplierOrderId, $exception): SupplierOrder {
            $supplierOrder = SupplierOrder::query()
                ->with(['order.items', 'orderItem'])
                ->whereKey($supplierOrderId)
                ->lockForUpdate()
                ->firstOrFail();
            if ($supplierOrder->status->terminal()) return $supplierOrder;
            return $this->markTerminalFailure($supplierOrder, class_basename($exception), $exception->getMessage());
        }, 5);
    }

    private function handlePollFailure(string $supplierOrderId, Throwable $exception): SupplierOrder
    {
        return DB::transaction(function () use ($supplierOrderId, $exception): SupplierOrder {
            $supplierOrder = SupplierOrder::query()->whereKey($supplierOrderId)->lockForUpdate()->firstOrFail();
            if ($supplierOrder->status->terminal()) return $supplierOrder;
            $pollFailures = (int) (($supplierOrder->metadata['poll_failures'] ?? 0) + 1);
            $metadata = array_merge($supplierOrder->metadata ?? [], ['poll_failures' => $pollFailures]);
            if ($pollFailures >= 5) {
                $supplierOrder->metadata = $metadata;
                return $this->markTerminalFailure($supplierOrder, class_basename($exception), $exception->getMessage());
            }
            $supplierOrder->forceFill([
                'status' => SupplierOrderStatus::Processing,
                'error_code' => class_basename($exception),
                'error_message' => mb_substr($exception->getMessage(), 0, 2000),
                'next_poll_at' => now()->addSeconds(min(600, 30 * (2 ** min($pollFailures, 4)))),
                'metadata' => $metadata,
            ])->save();
            return $supplierOrder->fresh();
        }, 5);
    }

    private function markTerminalFailure(SupplierOrder $supplierOrder, string $code, string $message): SupplierOrder
    {
        $supplierOrder->forceFill([
            'status' => SupplierOrderStatus::Failed,
            'error_code' => $code,
            'error_message' => mb_substr($message, 0, 2000),
            'failed_at' => now(),
            'next_poll_at' => null,
        ])->save();
        $supplierOrder->orderItem->forceFill(['status' => 'failed'])->save();
        $this->refund($supplierOrder);
        $this->audit->record('supplier.order.failed', SupplierOrder::class, $supplierOrder->id, [
            'error_code' => $code,
            'error_message' => $message,
        ], [], $supplierOrder->tenant_id);
        return $supplierOrder->fresh(['attemptLogs', 'decisions', 'refundTransaction']);
    }

    private function refund(SupplierOrder $supplierOrder): void
    {
        if ($supplierOrder->refund_ledger_transaction_id !== null) return;
        $supplierOrder->loadMissing(['order.wallet.account', 'orderItem']);
        $order = $supplierOrder->order;
        $amount = (int) $supplierOrder->orderItem->total_minor;
        $revenue = $this->wallets->systemAccount($order->tenant_id, $order->currency, 'revenue:commerce-sales', 'Commerce sales revenue', AccountType::Revenue, LedgerDirection::Credit);
        $transaction = $this->ledger->post(
            tenantId: $order->tenant_id,
            eventType: 'supplier.order.refunded',
            description: 'Automatic refund for failed supplier fulfillment '.$supplierOrder->client_reference,
            entries: [
                ['account_id' => $revenue->id, 'direction' => LedgerDirection::Debit, 'amount_minor' => $amount],
                ['account_id' => $order->wallet->ledger_account_id, 'direction' => LedgerDirection::Credit, 'amount_minor' => $amount],
            ],
            referenceType: SupplierOrder::class,
            referenceId: $supplierOrder->id,
            idempotencyKey: 'supplier-refund-'.$supplierOrder->id,
            metadata: ['order_id' => $order->id, 'order_item_id' => $supplierOrder->order_item_id],
        );
        $supplierOrder->forceFill([
            'refund_ledger_transaction_id' => $transaction->id,
            'status' => SupplierOrderStatus::Refunded,
            'refunded_at' => now(),
        ])->save();
        $supplierOrder->orderItem->forceFill(['status' => 'refunded'])->save();
        $this->refreshParentOrder($order->fresh('items'));
    }

    private function refreshParentOrder(Order $order): void
    {
        $order->loadMissing('items');
        $statuses = $order->items->pluck('status');
        $refunded = $order->items->where('status', 'refunded')->sum(fn (OrderItem $item) => (int) $item->total_minor);
        $fields = [];

        if ($statuses->every(fn (string $status) => $status === 'completed')) {
            $fields = ['status' => OrderStatus::Completed, 'fulfillment_status' => 'fulfilled'];
        } elseif ($statuses->every(fn (string $status) => $status === 'refunded')) {
            $fields = ['status' => OrderStatus::Canceled, 'fulfillment_status' => 'failed', 'canceled_at' => now()];
        } elseif ($statuses->contains('processing') || $statuses->contains('completed')) {
            $fields = ['status' => OrderStatus::Processing, 'fulfillment_status' => 'processing'];
        } elseif ($statuses->contains('failed') || $statuses->contains('refunded')) {
            $fields['fulfillment_status'] = $refunded >= (int) $order->total_minor ? 'failed' : 'partial';
        }
        if ($refunded > 0) $fields['payment_status'] = $refunded >= (int) $order->total_minor ? 'refunded' : 'partially_refunded';

        if ($fields !== []) {
            $before = $order->status->value;
            $order->forceFill($fields)->save();
            if (isset($fields['status']) && $fields['status']->value !== $before) {
                OrderStatusHistory::query()->create([
                    'order_id' => $order->id,
                    'from_status' => $before,
                    'to_status' => $fields['status']->value,
                    'note' => 'Supplier engine synchronized the order lifecycle.',
                ]);
            }
        }
    }

    private function mapFields(array $input, array $fieldMap): array
    {
        if ($fieldMap === []) return $input;
        $mapped = [];
        foreach ($input as $key => $value) $mapped[$fieldMap[$key] ?? $key] = $value;
        return $mapped;
    }
}
