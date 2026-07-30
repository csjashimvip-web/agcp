<?php
namespace Modules\Wallet\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Modules\Tenancy\Application\TenantContext;
use Modules\Wallet\Application\Services\WalletService;
use Modules\Wallet\Domain\Enums\DepositStatus;
use Modules\Wallet\Domain\Enums\WalletType;
use Modules\Wallet\Domain\ValueObjects\Money;
use Modules\Wallet\Http\Resources\DepositResource;
use Modules\Wallet\Infrastructure\Models\DepositRequest;
final class DepositController extends Controller
{
    public function __construct(private readonly TenantContext $tenant, private readonly WalletService $wallets) {}
    public function index(Request $request)
    {
        return DepositResource::collection(DepositRequest::query()->where(['tenant_id' => $this->tenant->requireId(), 'user_id' => $request->user()->id])->latest('submitted_at')->paginate(25));
    }
    public function store(Request $request): DepositResource
    {
        $validated = $request->validate([
            'amount' => ['required', 'decimal:0,2', 'min:1', 'max:1000000'],
            'currency' => ['required', 'string', 'size:3', Rule::in(['USD', 'BDT', 'EUR'])],
            'method' => ['required', Rule::in(['bank_transfer', 'manual', 'mobile_financial_service'])],
            'external_reference' => ['nullable', 'string', 'max:190'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
        ]);
        /** @var User $user */ $user = $request->user();
        $idempotencyKey = trim((string) $request->header('Idempotency-Key'));
        if (mb_strlen($idempotencyKey) < 16 || mb_strlen($idempotencyKey) > 128) {
            throw ValidationException::withMessages(['idempotency_key' => 'A 16–128 character Idempotency-Key header is required.']);
        }
        $money = Money::fromDecimal((string) $validated['amount'], (string) $validated['currency']);
        $wallet = $this->wallets->ensureUserWallet($user, $this->tenant->requireId(), $money->currency, WalletType::Main);
        $keyHash = hash('sha256', $idempotencyKey);
        $requestHash = hash('sha256', json_encode([
            'wallet_id' => $wallet->id,
            'amount_minor' => $money->minor,
            'currency' => $money->currency,
            'method' => $validated['method'],
            'external_reference' => $validated['external_reference'] ?? null,
            'customer_note' => $validated['customer_note'] ?? null,
        ], JSON_THROW_ON_ERROR));
        $deposit = DepositRequest::query()->firstOrCreate(
            ['tenant_id' => $this->tenant->requireId(), 'user_id' => $user->id, 'idempotency_key_hash' => $keyHash],
            [
                'wallet_id' => $wallet->id, 'amount_minor' => $money->minor, 'currency' => $money->currency,
                'method' => $validated['method'], 'status' => DepositStatus::Pending,
                'external_reference' => $validated['external_reference'] ?? null,
                'customer_note' => $validated['customer_note'] ?? null, 'request_hash' => $requestHash, 'submitted_at' => now(),
            ],
        );
        if ($deposit->request_hash !== $requestHash) {
            abort(409, 'This Idempotency-Key was already used with a different deposit request.');
        }
        return new DepositResource($deposit);
    }
    public function show(Request $request, DepositRequest $deposit): DepositResource
    {
        abort_unless($deposit->tenant_id === $this->tenant->requireId() && $deposit->user_id === $request->user()->id, 404);
        return new DepositResource($deposit);
    }
    public function cancel(Request $request, DepositRequest $deposit): DepositResource
    {
        abort_unless($deposit->tenant_id === $this->tenant->requireId() && $deposit->user_id === $request->user()->id, 404);
        $cancelled = DB::transaction(function () use ($deposit): DepositRequest {
            $locked = DepositRequest::query()->whereKey($deposit->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === DepositStatus::Pending, 409, 'Only pending deposits can be cancelled.');
            $locked->forceFill(['status' => DepositStatus::Cancelled])->save();
            return $locked;
        }, 5);
        return new DepositResource($cancelled);
    }
}
