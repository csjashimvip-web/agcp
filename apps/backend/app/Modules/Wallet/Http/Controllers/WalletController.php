<?php
namespace Modules\Wallet\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Modules\Tenancy\Application\TenantContext;
use Modules\Wallet\Application\Services\WalletService;
use Modules\Wallet\Domain\Enums\WalletType;
use Modules\Wallet\Http\Resources\LedgerTransactionResource;
use Modules\Wallet\Http\Resources\WalletResource;
use Modules\Wallet\Infrastructure\Models\LedgerTransaction;
use Modules\Wallet\Infrastructure\Models\Wallet;
final class WalletController extends Controller
{
    public function __construct(private readonly TenantContext $tenant, private readonly WalletService $wallets) {}
    public function index(Request $request)
    {
        /** @var User $user */ $user = $request->user();
        $this->wallets->ensureUserWallet($user, $this->tenant->requireId(), 'USD', WalletType::Main);
        return WalletResource::collection(Wallet::query()->with('account')->where(['tenant_id' => $this->tenant->requireId(), 'owner_type' => User::class, 'owner_id' => $user->id])->orderBy('currency')->orderBy('type')->get());
    }
    public function show(Request $request, Wallet $wallet): WalletResource
    {
        $this->authorizeOwner($request, $wallet);
        return new WalletResource($wallet->load('account'));
    }
    public function transactions(Request $request, Wallet $wallet)
    {
        $this->authorizeOwner($request, $wallet);
        $transactions = LedgerTransaction::query()->where('tenant_id', $this->tenant->requireId())
            ->whereHas('entries', fn ($q) => $q->where('ledger_account_id', $wallet->ledger_account_id))
            ->with(['entries.account'])->latest('posted_at')->paginate(25);
        return LedgerTransactionResource::collection($transactions);
    }
    private function authorizeOwner(Request $request, Wallet $wallet): void
    {
        abort_unless($wallet->tenant_id === $this->tenant->requireId() && $wallet->owner_type === User::class && $wallet->owner_id === $request->user()->id, 404);
    }
}
