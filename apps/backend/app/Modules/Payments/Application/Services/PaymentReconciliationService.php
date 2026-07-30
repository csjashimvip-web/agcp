<?php
namespace Modules\Payments\Application\Services;

use App\Models\User;
use Modules\Audit\Application\AuditLogger;
use Modules\Payments\Domain\Enums\PaymentIntentStatus;
use Modules\Payments\Domain\Enums\PaymentRefundStatus;
use Modules\Payments\Domain\Enums\ReconciliationRunStatus;
use Modules\Payments\Infrastructure\Models\PaymentIntent;
use Modules\Payments\Infrastructure\Models\PaymentProviderAccount;
use Modules\Payments\Infrastructure\Models\PaymentReconciliationItem;
use Modules\Payments\Infrastructure\Models\PaymentReconciliationRun;
use Modules\Payments\Infrastructure\Models\PaymentRefund;
use Modules\Wallet\Domain\Enums\DepositStatus;
use Modules\Wallet\Infrastructure\Models\DepositRequest;
use Throwable;

final class PaymentReconciliationService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function run(string $tenantId, ?PaymentProviderAccount $account = null, ?User $requestedBy = null): PaymentReconciliationRun
    {
        $run = PaymentReconciliationRun::query()->create([
            'tenant_id' => $tenantId,
            'provider_account_id' => $account?->id,
            'requested_by' => $requestedBy?->id,
            'status' => ReconciliationRunStatus::Running,
            'period_start' => now()->subDays((int) config('payments.reconciliation_window_days', 30)),
            'period_end' => now(),
            'started_at' => now(),
        ]);

        try {
            $query = PaymentIntent::query()->with(['deposit', 'refunds'])->where('tenant_id', $tenantId)
                ->where('created_at', '>=', $run->period_start);
            if ($account) $query->where('provider_account_id', $account->id);
            $intents = $query->get();

            foreach ($intents as $intent) {
                if (in_array($intent->status, [PaymentIntentStatus::Completed, PaymentIntentStatus::PartiallyRefunded, PaymentIntentStatus::Refunded], true)) {
                    if (! $intent->deposit) {
                        $this->item($run, $intent, 'missing_deposit', 'critical', $intent->amount_minor, null, 'Completed payment has no wallet deposit record.');
                    } elseif ($intent->deposit->status !== DepositStatus::Approved) {
                        $this->item($run, $intent, 'deposit_not_approved', 'critical', $intent->amount_minor, $intent->deposit->amount_minor, 'Completed payment deposit is not approved.');
                    } elseif ((int) $intent->deposit->amount_minor !== (int) $intent->amount_minor || $intent->deposit->currency !== $intent->currency) {
                        $this->item($run, $intent, 'deposit_amount_mismatch', 'critical', $intent->amount_minor, $intent->deposit->amount_minor, 'Wallet credit does not match the payment intent.');
                    } elseif (! $intent->deposit->ledger_transaction_id) {
                        $this->item($run, $intent, 'missing_deposit_ledger', 'critical', $intent->amount_minor, null, 'Approved payment deposit has no ledger transaction.');
                    }
                    if ((int) $intent->fee_minor > 0 && ! $intent->fee_ledger_transaction_id) {
                        $this->item($run, $intent, 'missing_fee_ledger', 'critical', $intent->fee_minor, null, 'Collected payment fee has no revenue ledger transaction.');
                    }
                }
                if (in_array($intent->status, [PaymentIntentStatus::Created, PaymentIntentStatus::Pending, PaymentIntentStatus::Processing], true)
                    && $intent->expires_at && $intent->expires_at->isPast()) {
                    $this->item($run, $intent, 'stale_pending_intent', 'warning', $intent->total_minor, null, 'Payment intent is still pending after its expiry time.');
                }
                $completedRefunds = $intent->refunds
                    ->filter(fn (PaymentRefund $refund): bool => $refund->status === PaymentRefundStatus::Completed)
                    ->sum('amount_minor');
                if ((int) $completedRefunds > (int) $intent->amount_minor) {
                    $this->item($run, $intent, 'refund_overage', 'critical', $intent->amount_minor, (int) $completedRefunds, 'Completed refunds exceed the credited payment amount.');
                }
            }

            $orphanDeposits = DepositRequest::query()->where('tenant_id', $tenantId)
                ->where('method', 'payment_gateway')->where('status', DepositStatus::Approved->value)
                ->whereNull('payment_intent_id')->where('created_at', '>=', $run->period_start)->get();
            foreach ($orphanDeposits as $deposit) {
                PaymentReconciliationItem::query()->create([
                    'reconciliation_run_id' => $run->id,
                    'type' => 'orphan_gateway_deposit',
                    'severity' => 'critical',
                    'status' => 'open',
                    'actual_amount_minor' => $deposit->amount_minor,
                    'currency' => $deposit->currency,
                    'description' => 'Approved gateway deposit is not linked to a payment intent.',
                    'evidence' => ['deposit_id' => $deposit->id, 'external_reference' => $deposit->external_reference],
                ]);
            }

            $refunds = PaymentRefund::query()->where('tenant_id', $tenantId)->where('status', PaymentRefundStatus::Completed->value)
                ->whereNull('ledger_transaction_id')->where('created_at', '>=', $run->period_start)->get();
            foreach ($refunds as $refund) {
                PaymentReconciliationItem::query()->create([
                    'reconciliation_run_id' => $run->id,
                    'payment_intent_id' => $refund->payment_intent_id,
                    'type' => 'missing_refund_ledger',
                    'severity' => 'critical',
                    'status' => 'open',
                    'expected_amount_minor' => $refund->amount_minor,
                    'currency' => $refund->currency,
                    'description' => 'Completed provider refund has no wallet reversal ledger transaction.',
                    'evidence' => ['payment_refund_id' => $refund->id, 'provider_refund_id' => $refund->provider_refund_id],
                ]);
            }

            $providerConfirmedRefunds = PaymentRefund::query()
                ->where('tenant_id', $tenantId)
                ->where('status', PaymentRefundStatus::Processing->value)
                ->whereNotNull('provider_refund_id')
                ->whereNull('ledger_transaction_id')
                ->where('created_at', '>=', $run->period_start)
                ->get();
            foreach ($providerConfirmedRefunds as $refund) {
                PaymentReconciliationItem::query()->create([
                    'reconciliation_run_id' => $run->id,
                    'payment_intent_id' => $refund->payment_intent_id,
                    'type' => 'provider_refund_pending_ledger',
                    'severity' => 'critical',
                    'status' => 'open',
                    'expected_amount_minor' => $refund->amount_minor,
                    'currency' => $refund->currency,
                    'description' => 'The provider confirmed this refund, but the wallet reversal ledger has not settled.',
                    'evidence' => [
                        'payment_refund_id' => $refund->id,
                        'provider_refund_id' => $refund->provider_refund_id,
                        'wallet_hold_id' => $refund->wallet_hold_id,
                        'failure_message' => $refund->failure_message,
                    ],
                ]);
            }

            $mismatches = $run->items()->count();
            $run->forceFill([
                'status' => ReconciliationRunStatus::Completed,
                'checked_count' => $intents->count(),
                'mismatch_count' => $mismatches,
                'resolved_count' => 0,
                'summary' => ['intents_checked' => $intents->count(), 'mismatches' => $mismatches, 'provider_account_id' => $account?->id],
                'completed_at' => now(),
            ])->save();
            $this->audit->record('payments.reconciliation.completed', PaymentReconciliationRun::class, $run->id, $run->summary ?? [], [], $tenantId, $requestedBy ? User::class : null, $requestedBy?->id);
            return $run->fresh(['items.intent', 'providerAccount', 'requestedBy']);
        } catch (Throwable $e) {
            $run->forceFill([
                'status' => ReconciliationRunStatus::Failed,
                'error_message' => mb_substr($e->getMessage(), 0, 2000),
                'completed_at' => now(),
            ])->save();
            throw $e;
        }
    }

    private function item(PaymentReconciliationRun $run, PaymentIntent $intent, string $type, string $severity, ?int $expected, ?int $actual, string $description): void
    {
        PaymentReconciliationItem::query()->create([
            'reconciliation_run_id' => $run->id,
            'payment_intent_id' => $intent->id,
            'type' => $type,
            'severity' => $severity,
            'status' => 'open',
            'expected_amount_minor' => $expected,
            'actual_amount_minor' => $actual,
            'currency' => $intent->currency,
            'description' => $description,
            'evidence' => ['reference' => $intent->reference, 'status' => $intent->status->value, 'provider_payment_id' => $intent->provider_payment_id],
        ]);
    }
}
