<?php
namespace Modules\Wallet\Application\Services;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Wallet\Domain\Enums\AccountType;
use Modules\Wallet\Domain\Enums\LedgerDirection;
use Modules\Wallet\Domain\Enums\WalletType;
use Modules\Wallet\Infrastructure\Models\LedgerAccount;
use Modules\Wallet\Infrastructure\Models\Wallet;
final class WalletService
{
    public function ensureUserWallet(User $user, string $tenantId, string $currency = 'USD', WalletType $type = WalletType::Main): Wallet
    {
        $currency = strtoupper($currency);
        $existing = Wallet::query()->where([
            'tenant_id' => $tenantId,
            'owner_type' => User::class,
            'owner_id' => $user->id,
            'type' => $type->value,
            'currency' => $currency,
        ])->first();
        if ($existing) return $existing->load('account');

        return DB::transaction(function () use ($user, $tenantId, $currency, $type): Wallet {
            $existing = Wallet::query()->where([
                'tenant_id' => $tenantId,
                'owner_type' => User::class,
                'owner_id' => $user->id,
                'type' => $type->value,
                'currency' => $currency,
            ])->lockForUpdate()->first();
            if ($existing) return $existing->load('account');

            $account = LedgerAccount::query()->create([
                'tenant_id' => $tenantId,
                'code' => sprintf('wallet:%s:%s:%s', $user->id, $type->value, strtolower($currency)),
                'name' => sprintf('%s %s wallet', $user->name, $type->value),
                'account_type' => AccountType::Liability,
                'normal_balance' => LedgerDirection::Credit,
                'owner_type' => User::class,
                'owner_id' => $user->id,
                'currency' => $currency,
                'status' => 'active',
                'metadata' => ['public_id' => (string) Str::uuid()],
            ]);

            return Wallet::query()->create([
                'tenant_id' => $tenantId,
                'owner_type' => User::class,
                'owner_id' => $user->id,
                'ledger_account_id' => $account->id,
                'type' => $type,
                'currency' => $currency,
                'status' => 'active',
                'limits' => ['single_deposit_minor' => 1_000_000_00],
            ])->load('account');
        }, 5);
    }

    public function systemAccount(string $tenantId, string $currency, string $code, string $name, AccountType $type, LedgerDirection $normal): LedgerAccount
    {
        return LedgerAccount::query()->firstOrCreate(
            ['tenant_id' => $tenantId, 'code' => $code.':'.strtolower($currency)],
            [
                'name' => $name.' '.$currency,
                'account_type' => $type,
                'normal_balance' => $normal,
                'currency' => strtoupper($currency),
                'status' => 'active',
                'metadata' => ['system' => true, 'allow_negative' => true],
            ],
        );
    }
}
