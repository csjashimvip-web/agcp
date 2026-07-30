<?php
namespace Modules\Payments\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Payments\Application\Services\PaymentProviderRegistry;
use Modules\Payments\Application\Services\PaymentReconciliationService;
use Modules\Payments\Application\Services\PaymentRefundService;
use Modules\Payments\Http\Resources\PaymentIntentResource;
use Modules\Payments\Http\Resources\PaymentProviderResource;
use Modules\Payments\Infrastructure\Models\PaymentIntent;
use Modules\Payments\Infrastructure\Models\PaymentProviderAccount;
use Modules\Payments\Infrastructure\Models\PaymentReconciliationRun;
use Modules\Payments\Infrastructure\Models\PaymentRefund;
use Modules\Payments\Infrastructure\Models\PaymentWebhook;
use Modules\Tenancy\Application\TenantContext;
use Modules\Wallet\Domain\ValueObjects\Money;

final class AdminPaymentController extends Controller
{
    public function index(TenantContext $context): array
    {
        $tenantId = $context->requireId();
        return ['data' => [
            'providers' => PaymentProviderResource::collection(PaymentProviderAccount::query()->where('tenant_id', $tenantId)->orderBy('priority')->get())->resolve(),
            'intents' => PaymentIntentResource::collection(PaymentIntent::query()->with(['providerAccount', 'deposit', 'refunds'])->where('tenant_id', $tenantId)->latest()->limit(100)->get())->resolve(),
            'webhooks' => PaymentWebhook::query()->with('providerAccount:id,name,code,provider')->where('tenant_id', $tenantId)->latest('received_at')->limit(100)->get()->map(fn (PaymentWebhook $item) => [
                'id' => $item->id,
                'provider' => $item->providerAccount?->name,
                'external_event_id' => $item->external_event_id,
                'event_type' => $item->event_type,
                'status' => $item->status->value,
                'payment_intent_id' => $item->payment_intent_id,
                'error_message' => $item->error_message,
                'received_at' => $item->received_at?->toAtomString(),
            ])->values(),
            'refunds' => PaymentRefund::query()->with(['intent:id,reference', 'requester:id,name,email'])->where('tenant_id', $tenantId)->latest()->limit(100)->get()->map(fn (PaymentRefund $refund) => [
                'id' => $refund->id,
                'reference' => $refund->reference,
                'payment_intent_id' => $refund->payment_intent_id,
                'payment_reference' => $refund->intent?->reference,
                'provider_refund_id' => $refund->provider_refund_id,
                'amount_minor' => (int) $refund->amount_minor,
                'currency' => $refund->currency,
                'status' => $refund->status->value,
                'reason' => $refund->reason,
                'requested_by' => $refund->requester?->name,
                'completed_at' => $refund->completed_at?->toAtomString(),
            ])->values(),
            'reconciliation_runs' => PaymentReconciliationRun::query()->with('items')->where('tenant_id', $tenantId)->latest('started_at')->limit(20)->get(),
        ]];
    }

    public function providerTypes(PaymentProviderRegistry $registry): array
    {
        return ['data' => $registry->codes()];
    }

    public function storeProvider(Request $request, TenantContext $context, PaymentProviderRegistry $registry): JsonResponse
    {
        $tenantId = $context->requireId();
        $data = $request->validate([
            'provider' => ['required', Rule::in($registry->codes())],
            'code' => ['required', 'alpha_dash', 'max:100', Rule::unique('payment_provider_accounts', 'code')->where('tenant_id', $tenantId)],
            'name' => ['required', 'string', 'max:160'],
            'mode' => ['required', Rule::in(['sandbox', 'live'])],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'priority' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'currencies' => ['required', 'array', 'min:1'],
            'currencies.*' => ['string', 'size:3'],
            'minimum_amount_minor' => ['nullable', 'integer', 'min:1'],
            'maximum_amount_minor' => ['nullable', 'integer', 'min:1'],
            'fee_basis_points' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'fee_fixed_minor' => ['nullable', 'integer', 'min:0'],
            'credentials' => ['nullable', 'array'],
        ]);
        $secret = Str::random(64);
        $account = PaymentProviderAccount::query()->create([
            'tenant_id' => $tenantId,
            'provider' => $data['provider'],
            'code' => $data['code'],
            'name' => $data['name'],
            'mode' => $data['mode'],
            'status' => $data['status'] ?? 'active',
            'priority' => $data['priority'] ?? 100,
            'currencies' => array_values(array_unique(array_map('strtoupper', $data['currencies']))),
            'minimum_amount_minor' => $data['minimum_amount_minor'] ?? 100,
            'maximum_amount_minor' => $data['maximum_amount_minor'] ?? 100000000,
            'fee_basis_points' => $data['fee_basis_points'] ?? 0,
            'fee_fixed_minor' => $data['fee_fixed_minor'] ?? 0,
            'credentials' => $data['credentials'] ?? [],
            'webhook_secret' => $secret,
        ]);
        return response()->json(['data' => (new PaymentProviderResource($account))->resolve(), 'webhook_secret' => $secret], 201);
    }

    public function updateProvider(Request $request, TenantContext $context, PaymentProviderAccount $providerAccount): PaymentProviderResource
    {
        abort_unless($providerAccount->tenant_id === $context->requireId(), 404);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'mode' => ['sometimes', Rule::in(['sandbox', 'live'])],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'currencies' => ['sometimes', 'array', 'min:1'],
            'currencies.*' => ['string', 'size:3'],
            'minimum_amount_minor' => ['sometimes', 'integer', 'min:1'],
            'maximum_amount_minor' => ['sometimes', 'integer', 'min:1'],
            'fee_basis_points' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'fee_fixed_minor' => ['sometimes', 'integer', 'min:0'],
            'credentials' => ['sometimes', 'array'],
        ]);
        if (isset($data['currencies'])) $data['currencies'] = array_values(array_unique(array_map('strtoupper', $data['currencies'])));
        $providerAccount->update($data);
        return new PaymentProviderResource($providerAccount->fresh());
    }

    public function rotateWebhookSecret(TenantContext $context, PaymentProviderAccount $providerAccount): array
    {
        abort_unless($providerAccount->tenant_id === $context->requireId(), 404);
        $secret = Str::random(64);
        $providerAccount->update(['webhook_secret' => $secret]);
        return ['data' => ['provider_account_id' => $providerAccount->id, 'webhook_secret' => $secret, 'rotated_at' => now()->toAtomString()]];
    }

    public function reconcile(Request $request, TenantContext $context, PaymentReconciliationService $service): array
    {
        $data = $request->validate(['provider_account_id' => ['nullable', 'uuid']]);
        $account = null;
        if (! empty($data['provider_account_id'])) {
            $account = PaymentProviderAccount::query()->where('tenant_id', $context->requireId())->whereKey($data['provider_account_id'])->firstOrFail();
        }
        $run = $service->run($context->requireId(), $account, $request->user());
        return ['data' => $run];
    }

    public function refund(Request $request, TenantContext $context, PaymentIntent $paymentIntent, PaymentRefundService $service): array
    {
        abort_unless($paymentIntent->tenant_id === $context->requireId(), 404);
        $data = $request->validate([
            'amount' => ['required', 'decimal:0,2', 'min:0.01'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $idempotency = trim((string) $request->header('Idempotency-Key'));
        if (mb_strlen($idempotency) < 16 || mb_strlen($idempotency) > 128) {
            throw ValidationException::withMessages(['idempotency_key' => 'A 16–128 character Idempotency-Key header is required.']);
        }
        $money = Money::fromDecimal((string) $data['amount'], $paymentIntent->currency);
        return ['data' => $service->request($paymentIntent, $request->user(), $money->minor, $data['reason'], $idempotency)];
    }
}
