<?php
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tenancy\Infrastructure\Models\Tenant;
use Modules\Wallet\Application\Services\DepositService;
use Modules\Wallet\Application\Services\WalletService;
use Modules\Wallet\Domain\Enums\DepositStatus;
use Modules\Wallet\Domain\Enums\WalletType;
use Modules\Wallet\Infrastructure\Models\DepositRequest;

uses(RefreshDatabase::class);

it('creates a user wallet with an integer minor-unit balance', function () {
    $tenant = Tenant::query()->create(['name'=>'Test Tenant','slug'=>'test-tenant-1','status'=>'active','default_currency'=>'USD','timezone'=>'UTC']);
    $user = User::query()->create(['name'=>'Customer','email'=>'customer1@example.test','password'=>'Secret123!','status'=>'active']);
    $wallet = app(WalletService::class)->ensureUserWallet($user, $tenant->id, 'USD', WalletType::Main);
    expect($wallet->account->balance_minor)->toBe(0)->and($wallet->currency)->toBe('USD');
});

it('posts an approved deposit as a balanced double entry transaction', function () {
    $tenant = Tenant::query()->create(['name'=>'Test Tenant','slug'=>'test-tenant-2','status'=>'active','default_currency'=>'USD','timezone'=>'UTC']);
    $customer = User::query()->create(['name'=>'Customer','email'=>'customer2@example.test','password'=>'Secret123!','status'=>'active']);
    $reviewer = User::query()->create(['name'=>'Reviewer','email'=>'reviewer@example.test','password'=>'Secret123!','status'=>'active']);
    $wallet = app(WalletService::class)->ensureUserWallet($customer, $tenant->id, 'USD', WalletType::Main);
    $deposit = DepositRequest::query()->create([
        'tenant_id' => $tenant->id, 'user_id' => $customer->id, 'wallet_id' => $wallet->id,
        'amount_minor' => 2500, 'currency' => 'USD', 'method' => 'manual',
        'status' => DepositStatus::Pending, 'submitted_at' => now(),
    ]);
    $approved = app(DepositService::class)->approve($deposit, $reviewer, null, 'test-deposit-1');
    expect($approved->status)->toBe(DepositStatus::Approved)
        ->and((int) $approved->ledgerTransaction->entries()->sum('amount_minor'))->toBe(5000)
        ->and($wallet->account()->first()->balance_minor)->toBe(2500);
});

it('replays the same deposit approval without posting a second journal', function () {
    $tenant = Tenant::query()->create(['name'=>'Replay Tenant','slug'=>'replay-tenant','status'=>'active','default_currency'=>'USD','timezone'=>'UTC']);
    $customer = User::query()->create(['name'=>'Replay Customer','email'=>'replay-customer@example.test','password'=>'Secret123!','status'=>'active']);
    $reviewer = User::query()->create(['name'=>'Replay Reviewer','email'=>'replay-reviewer@example.test','password'=>'Secret123!','status'=>'active']);
    $wallet = app(WalletService::class)->ensureUserWallet($customer, $tenant->id, 'USD', WalletType::Main);
    $deposit = DepositRequest::query()->create([
        'tenant_id' => $tenant->id, 'user_id' => $customer->id, 'wallet_id' => $wallet->id,
        'amount_minor' => 4000, 'currency' => 'USD', 'method' => 'manual',
        'status' => DepositStatus::Pending, 'submitted_at' => now(),
    ]);
    $first = app(DepositService::class)->approve($deposit, $reviewer, null, 'replay-key-00000001');
    $second = app(DepositService::class)->approve($deposit->fresh(), $reviewer, null, 'replay-key-00000001');
    expect($second->ledger_transaction_id)->toBe($first->ledger_transaction_id)
        ->and($wallet->account()->first()->balance_minor)->toBe(4000);
});

it('rejects unbalanced ledger entries', function () {
    $tenant = Tenant::query()->create(['name'=>'Ledger Tenant','slug'=>'ledger-tenant','status'=>'active','default_currency'=>'USD','timezone'=>'UTC']);
    $customer = User::query()->create(['name'=>'Ledger Customer','email'=>'ledger-customer@example.test','password'=>'Secret123!','status'=>'active']);
    $wallet = app(WalletService::class)->ensureUserWallet($customer, $tenant->id, 'USD', WalletType::Main);
    app(\Modules\Wallet\Application\Services\LedgerService::class)->post(
        $tenant->id,
        'test.unbalanced',
        'Invalid test journal',
        [[
            'account_id' => $wallet->ledger_account_id,
            'direction' => \Modules\Wallet\Domain\Enums\LedgerDirection::Credit,
            'amount_minor' => 100,
        ]],
    );
})->throws(\Illuminate\Validation\ValidationException::class);

it('prevents an adjustment requester from approving their own request', function () {
    $tenant = Tenant::query()->create(['name'=>'Adjustment Tenant','slug'=>'adjustment-tenant','status'=>'active','default_currency'=>'USD','timezone'=>'UTC']);
    $admin = User::query()->create(['name'=>'Adjustment Admin','email'=>'adjustment-admin@example.test','password'=>'Secret123!','status'=>'active']);
    $customer = User::query()->create(['name'=>'Adjustment Customer','email'=>'adjustment-customer@example.test','password'=>'Secret123!','status'=>'active']);
    $wallet = app(WalletService::class)->ensureUserWallet($customer, $tenant->id, 'USD', WalletType::Main);
    $adjustment = \Modules\Wallet\Infrastructure\Models\WalletAdjustment::query()->create([
        'tenant_id' => $tenant->id,
        'wallet_id' => $wallet->id,
        'direction' => \Modules\Wallet\Domain\Enums\LedgerDirection::Credit,
        'amount_minor' => 500,
        'currency' => 'USD',
        'status' => \Modules\Wallet\Domain\Enums\AdjustmentStatus::Pending,
        'reason' => 'Controlled test adjustment',
        'requested_by' => $admin->id,
    ]);
    app(\Modules\Wallet\Application\Services\AdjustmentService::class)->approve($adjustment, $admin, 'adjustment-key-0001');
})->throws(\Illuminate\Validation\ValidationException::class);
