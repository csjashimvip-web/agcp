<?php
namespace Modules\Wallet\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Tenancy\Application\TenantContext;
use Modules\Wallet\Http\Resources\LedgerTransactionResource;
use Modules\Wallet\Http\Resources\WalletResource;
use Modules\Wallet\Infrastructure\Models\LedgerTransaction;
use Modules\Wallet\Infrastructure\Models\Wallet;
final class AdminWalletController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}
    public function index(Request $request)
    {
        $query = Wallet::query()->with('account')->where('tenant_id', $this->tenant->requireId());
        if ($request->filled('currency')) $query->where('currency', strtoupper($request->string('currency')->toString()));
        return WalletResource::collection($query->latest()->paginate(40));
    }
    public function show(Wallet $wallet): WalletResource
    {
        abort_unless($wallet->tenant_id === $this->tenant->requireId(), 404);
        return new WalletResource($wallet->load('account'));
    }
    public function ledger(Request $request)
    {
        $transactions = LedgerTransaction::query()->where('tenant_id', $this->tenant->requireId())->with(['entries.account'])->latest('posted_at')->paginate(50);
        return LedgerTransactionResource::collection($transactions);
    }
}
