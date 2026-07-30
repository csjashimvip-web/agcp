<?php
namespace Modules\Wallet\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Tenancy\Application\TenantContext;
use Modules\Wallet\Application\Services\AdjustmentService;
use Modules\Wallet\Domain\Enums\AdjustmentStatus;
use Modules\Wallet\Domain\Enums\LedgerDirection;
use Modules\Wallet\Domain\ValueObjects\Money;
use Modules\Wallet\Infrastructure\Models\Wallet;
use Modules\Wallet\Infrastructure\Models\WalletAdjustment;
final class WalletAdjustmentController extends Controller
{
    public function __construct(private readonly TenantContext $tenant, private readonly AdjustmentService $service) {}
    public function index()
    {
        return response()->json(['data' => WalletAdjustment::query()->with(['wallet.account', 'requester', 'reviewer'])->where('tenant_id', $this->tenant->requireId())->latest()->paginate(40)]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'wallet_id' => ['required', 'uuid'],
            'direction' => ['required', Rule::in(['credit', 'debit'])],
            'amount' => ['required', 'decimal:0,2', 'min:0.01', 'max:1000000'],
            'reason' => ['required', 'string', 'min:12', 'max:500'],
        ]);
        $wallet = Wallet::query()->where(['id' => $validated['wallet_id'], 'tenant_id' => $this->tenant->requireId()])->firstOrFail();
        $idempotencyKey = trim((string) $request->header('Idempotency-Key'));
        if (mb_strlen($idempotencyKey) < 16 || mb_strlen($idempotencyKey) > 128) {
            throw ValidationException::withMessages(['idempotency_key' => 'A 16–128 character Idempotency-Key header is required.']);
        }
        $money = Money::fromDecimal((string) $validated['amount'], $wallet->currency);
        $keyHash = hash('sha256', $idempotencyKey);
        $requestHash = hash('sha256', json_encode([
            'wallet_id' => $wallet->id, 'direction' => $validated['direction'], 'amount_minor' => $money->minor,
            'currency' => $money->currency, 'reason' => $validated['reason'],
        ], JSON_THROW_ON_ERROR));
        $adjustment = WalletAdjustment::query()->firstOrCreate(
            ['tenant_id' => $wallet->tenant_id, 'requested_by' => $request->user()->id, 'idempotency_key_hash' => $keyHash],
            [
                'wallet_id' => $wallet->id, 'direction' => LedgerDirection::from($validated['direction']),
                'amount_minor' => $money->minor, 'currency' => $money->currency, 'status' => AdjustmentStatus::Pending,
                'reason' => $validated['reason'], 'request_hash' => $requestHash,
            ],
        );
        if ($adjustment->request_hash !== $requestHash) {
            abort(409, 'This Idempotency-Key was already used with a different wallet adjustment.');
        }
        return response()->json(['data' => $adjustment->load('wallet.account', 'requester')], 201);
    }
    public function approve(Request $request, WalletAdjustment $adjustment)
    {
        abort_unless($adjustment->tenant_id === $this->tenant->requireId(), 404);
        return response()->json(['data' => $this->service->approve($adjustment, $request->user(), $request->header('Idempotency-Key'))]);
    }
    public function reject(Request $request, WalletAdjustment $adjustment)
    {
        abort_unless($adjustment->tenant_id === $this->tenant->requireId(), 404);
        abort_unless($adjustment->status === AdjustmentStatus::Pending, 409, 'Only pending adjustments can be rejected.');
        abort_if($adjustment->requested_by === $request->user()->id, 422, 'The requester cannot reject the same adjustment.');
        $adjustment->forceFill(['status' => AdjustmentStatus::Rejected, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()])->save();
        return response()->json(['data' => $adjustment->fresh(['wallet.account', 'requester', 'reviewer'])]);
    }
}
