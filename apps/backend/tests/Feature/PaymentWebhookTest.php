<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Payments\Domain\Models\PaymentIntent;
use App\Modules\Payments\Domain\Models\PaymentProvider;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Wallet\Domain\Models\LedgerAccount;
use App\Modules\Wallet\Domain\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

final class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_payment_webhook_credits_wallet_once(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Payments Tenant',
            'slug' => 'payments-tenant',
            'status' => 'active',
            'default_currency' => 'USD',
        ]);

        $user = User::factory()->create();

        $walletAccount = LedgerAccount::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'wallet-'.$user->id,
            'name' => 'Customer Wallet',
            'type' => 'asset',
            'currency' => 'USD',
            'status' => 'active',
            'balance_minor' => 0,
        ]);

        LedgerAccount::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'payment-clearing',
            'name' => 'Payment Clearing',
            'type' => 'asset',
            'currency' => 'USD',
            'status' => 'active',
            'balance_minor' => 100000,
        ]);

        $wallet = Wallet::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'ledger_account_id' => $walletAccount->id,
            'currency' => 'USD',
            'status' => 'active',
            'available_balance_minor' => 0,
            'held_balance_minor' => 0,
        ]);

        $secret = 'test-webhook-secret';

        $provider = PaymentProvider::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Test HMAC Provider',
            'code' => 'test-hmac',
            'driver' => 'generic-hmac',
            'status' => 'active',
            'secret_payload' => Crypt::encryptString(json_encode([
                'webhook_secret' => $secret,
            ], JSON_THROW_ON_ERROR)),
        ]);

        PaymentIntent::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'payment_provider_id' => $provider->id,
            'intent_uuid' => 'intent-001',
            'idempotency_key' => 'payment-intent-001',
            'provider_reference' => 'provider-ref-001',
            'status' => 'pending',
            'amount_minor' => 7500,
            'provider_fee_minor' => 0,
            'currency' => 'USD',
        ]);

        $payload = json_encode([
            'event_id' => 'evt-001',
            'type' => 'payment.completed',
            'provider_reference' => 'provider-ref-001',
            'status' => 'completed',
        ], JSON_THROW_ON_ERROR);

        $signature = hash_hmac('sha256', $payload, $secret);

        $this
            ->call(
                'POST',
                "/api/v1/payments/webhooks/{$provider->id}",
                [],
                [],
                [],
                [
                    'CONTENT_TYPE' => 'application/json',
                    'HTTP_X_AGCP_SIGNATURE' => $signature,
                ],
                $payload,
            )
            ->assertOk();

        $this
            ->call(
                'POST',
                "/api/v1/payments/webhooks/{$provider->id}",
                [],
                [],
                [],
                [
                    'CONTENT_TYPE' => 'application/json',
                    'HTTP_X_AGCP_SIGNATURE' => $signature,
                ],
                $payload,
            )
            ->assertOk()
            ->assertJsonPath('data.duplicate', true);

        $this->assertSame(7500, $wallet->fresh()->available_balance_minor);
        $this->assertSame(1, PaymentIntent::query()->where('status', 'completed')->count());
    }
}