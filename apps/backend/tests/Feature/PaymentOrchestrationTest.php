<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Payments\Application\Services\PaymentIntentService;
use Modules\Payments\Application\Services\PaymentReconciliationService;
use Modules\Payments\Application\Services\PaymentRefundService;
use Modules\Payments\Application\Services\PaymentWebhookService;
use Modules\Payments\Infrastructure\Models\PaymentProviderAccount;
use Modules\Payments\Infrastructure\Models\PaymentRefund;
use Modules\Payments\Infrastructure\Models\PaymentWebhook;
use Modules\Tenancy\Infrastructure\Models\Tenant;
use Modules\Wallet\Application\Services\WalletService;

uses(RefreshDatabase::class);

function paymentFixture(): array
{
    $tenant = Tenant::query()->create([
        'name' => 'Payment Tenant', 'slug' => 'payment-tenant', 'status' => 'active',
        'default_currency' => 'USD', 'timezone' => 'UTC',
    ]);
    $user = User::query()->create([
        'name' => 'Payment Customer', 'email' => 'payment@example.test',
        'password' => 'Secret123!', 'status' => 'active', 'email_verified_at' => now(),
    ]);
    $admin = User::query()->create([
        'name' => 'Payment Admin', 'email' => 'payment-admin@example.test',
        'password' => 'Secret123!', 'status' => 'active', 'email_verified_at' => now(),
    ]);
    $wallet = app(WalletService::class)->ensureUserWallet($user, $tenant->id, 'USD');
    $account = PaymentProviderAccount::query()->create([
        'tenant_id' => $tenant->id,
        'provider' => 'sandbox',
        'code' => 'sandbox-test',
        'name' => 'Sandbox Test',
        'mode' => 'sandbox',
        'status' => 'active',
        'priority' => 1,
        'currencies' => ['USD'],
        'minimum_amount_minor' => 100,
        'maximum_amount_minor' => 1000000,
        'fee_basis_points' => 200,
        'fee_fixed_minor' => 0,
        'credentials' => [],
        'webhook_secret' => 'payment-test-secret',
    ]);
    $intent = app(PaymentIntentService::class)->create($user, $tenant->id, $wallet->id, $account->code, 5000, 'USD', 'payment-test-idempotency-key');
    return compact('tenant', 'user', 'admin', 'wallet', 'account', 'intent');
}


it('replays a matching customer idempotency key without creating another intent', function () {
    $fixture = paymentFixture();
    $replayed = app(PaymentIntentService::class)->create(
        $fixture['user'],
        $fixture['tenant']->id,
        $fixture['wallet']->id,
        $fixture['account']->code,
        5000,
        'USD',
        'payment-test-idempotency-key',
    );

    expect($replayed->id)->toBe($fixture['intent']->id)
        ->and($fixture['account']->intents()->count())->toBe(1);
});

it('credits a customer wallet only after a signed payment webhook', function () {
    $fixture = paymentFixture();
    expect($fixture['wallet']->account()->first()->balance_minor)->toBe(0);

    app(PaymentWebhookService::class)->simulateCapture($fixture['intent']);
    $intent = $fixture['intent']->fresh(['deposit.ledgerTransaction']);

    expect($intent->status->value)->toBe('completed')
        ->and($intent->deposit)->not->toBeNull()
        ->and($intent->deposit->status->value)->toBe('approved')
        ->and($intent->fee_minor)->toBe(100)
        ->and($intent->fee_ledger_transaction_id)->not->toBeNull()
        ->and($intent->deposit->ledger_transaction_id)->not->toBeNull()
        ->and($fixture['wallet']->account()->first()->balance_minor)->toBe(5000);
});

it('deduplicates a provider event and never double credits the wallet', function () {
    $fixture = paymentFixture();
    $timestamp = now()->timestamp;
    $payload = json_encode([
        'event_id' => 'evt-fixed-duplicate',
        'type' => 'payment.captured',
        'reference' => $fixture['intent']->reference,
        'provider_payment_id' => $fixture['intent']->provider_payment_id,
        'amount_minor' => $fixture['intent']->total_minor,
        'currency' => 'USD',
        'occurred_at' => now()->toAtomString(),
    ], JSON_THROW_ON_ERROR);
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'payment-test-secret');
    $headers = ['X-AGCP-Timestamp' => (string) $timestamp, 'X-AGCP-Signature' => 'sha256='.$signature];

    $service = app(PaymentWebhookService::class);
    $first = $service->ingest($fixture['account'], $payload, $headers);
    $second = $service->ingest($fixture['account'], $payload, $headers);

    expect($second->id)->toBe($first->id)
        ->and(PaymentWebhook::query()->where('external_event_id', 'evt-fixed-duplicate')->count())->toBe(1)
        ->and($fixture['wallet']->account()->first()->balance_minor)->toBe(5000);
});

it('rejects an invalid webhook signature without moving money', function () {
    $fixture = paymentFixture();
    $payload = json_encode([
        'event_id' => 'evt-invalid', 'type' => 'payment.captured', 'reference' => $fixture['intent']->reference,
        'provider_payment_id' => $fixture['intent']->provider_payment_id, 'amount_minor' => 5000, 'currency' => 'USD',
    ], JSON_THROW_ON_ERROR);

    expect(fn () => app(PaymentWebhookService::class)->ingest($fixture['account'], $payload, [
        'X-AGCP-Timestamp' => (string) now()->timestamp,
        'X-AGCP-Signature' => 'sha256=invalid',
    ]))->toThrow(ValidationException::class)
        ->and($fixture['wallet']->account()->first()->balance_minor)->toBe(0)
        ->and(PaymentWebhook::query()->where('status', 'rejected')->count())->toBe(1);
});

it('reverses wallet credit after a confirmed provider refund', function () {
    $fixture = paymentFixture();
    app(PaymentWebhookService::class)->simulateCapture($fixture['intent']);
    $intent = $fixture['intent']->fresh(['providerAccount', 'wallet.account']);

    $refund = app(PaymentRefundService::class)->request($intent, $fixture['admin'], 5000, 'Test full refund', 'refund-test-idempotency-key');

    expect($refund->status->value)->toBe('completed')
        ->and($refund->ledger_transaction_id)->not->toBeNull()
        ->and($refund->wallet_hold_id)->not->toBeNull()
        ->and($refund->walletHold->status)->toBe('released')
        ->and($intent->fresh()->status->value)->toBe('refunded')
        ->and($fixture['wallet']->account()->first()->balance_minor)->toBe(0);
});

it('reconciles a correctly settled payment without mismatches', function () {
    $fixture = paymentFixture();
    app(PaymentWebhookService::class)->simulateCapture($fixture['intent']);

    $run = app(PaymentReconciliationService::class)->run($fixture['tenant']->id, $fixture['account'], $fixture['admin']);

    expect($run->status->value)->toBe('completed')
        ->and($run->checked_count)->toBe(1)
        ->and($run->mismatch_count)->toBe(0)
        ->and($run->items)->toHaveCount(0);
});

it('flags a provider-confirmed refund that is waiting for ledger settlement', function () {
    $fixture = paymentFixture();
    app(PaymentWebhookService::class)->simulateCapture($fixture['intent']);
    $refund = PaymentRefund::query()->create([
        'tenant_id' => $fixture['tenant']->id,
        'payment_intent_id' => $fixture['intent']->id,
        'requested_by' => $fixture['admin']->id,
        'reference' => 'REF-'.strtoupper((string) Str::ulid()),
        'provider_refund_id' => 'provider-confirmed-no-ledger',
        'amount_minor' => 1000,
        'currency' => 'USD',
        'status' => 'processing',
        'reason' => 'Reconciliation fixture',
        'idempotency_key_hash' => hash('sha256', 'reconciliation-refund-key'),
        'request_hash' => hash('sha256', 'reconciliation-refund-request'),
        'requested_at' => now(),
    ]);

    $run = app(PaymentReconciliationService::class)->run($fixture['tenant']->id, $fixture['account'], $fixture['admin']);

    expect($run->mismatch_count)->toBe(1)
        ->and($run->items()->where('type', 'provider_refund_pending_ledger')->where('payment_intent_id', $refund->payment_intent_id)->exists())->toBeTrue();
});

