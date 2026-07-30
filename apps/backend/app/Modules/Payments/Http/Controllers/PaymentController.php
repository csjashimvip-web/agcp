<?php
namespace Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Payments\Application\Services\PaymentIntentService;
use Modules\Payments\Application\Services\PaymentWebhookService;
use Modules\Payments\Http\Resources\PaymentIntentResource;
use Modules\Payments\Http\Resources\PaymentProviderResource;
use Modules\Payments\Infrastructure\Models\PaymentIntent;
use Modules\Payments\Infrastructure\Models\PaymentProviderAccount;
use Modules\Tenancy\Application\TenantContext;
use Modules\Wallet\Domain\ValueObjects\Money;

final class PaymentController extends Controller
{
    public function providers(TenantContext $context)
    {
        return PaymentProviderResource::collection(PaymentProviderAccount::query()
            ->where('tenant_id', $context->requireId())->where('status', 'active')->orderBy('priority')->get());
    }

    public function index(Request $request, TenantContext $context)
    {
        return PaymentIntentResource::collection(PaymentIntent::query()->with(['providerAccount', 'deposit', 'refunds'])
            ->where(['tenant_id' => $context->requireId(), 'user_id' => $request->user()->id])
            ->latest()->paginate(25));
    }

    public function store(Request $request, TenantContext $context, PaymentIntentService $service): PaymentIntentResource
    {
        $data = $request->validate([
            'wallet_id' => ['required', 'uuid'],
            'provider_code' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'decimal:0,2', 'min:1', 'max:1000000'],
            'currency' => ['required', 'string', 'size:3'],
        ]);
        $idempotency = trim((string) $request->header('Idempotency-Key'));
        if (mb_strlen($idempotency) < 16 || mb_strlen($idempotency) > 128) {
            throw ValidationException::withMessages(['idempotency_key' => 'A 16–128 character Idempotency-Key header is required.']);
        }
        /** @var User $user */ $user = $request->user();
        $money = Money::fromDecimal((string) $data['amount'], strtoupper((string) $data['currency']));
        return new PaymentIntentResource($service->create(
            $user,
            $context->requireId(),
            $data['wallet_id'],
            $data['provider_code'],
            $money->minor,
            $money->currency,
            $idempotency,
        ));
    }

    public function show(Request $request, TenantContext $context, PaymentIntent $paymentIntent): PaymentIntentResource
    {
        abort_unless($paymentIntent->tenant_id === $context->requireId() && $paymentIntent->user_id === $request->user()->id, 404);
        return new PaymentIntentResource($paymentIntent->load(['providerAccount', 'wallet.account', 'attempts', 'deposit', 'refunds']));
    }

    public function cancel(Request $request, TenantContext $context, PaymentIntent $paymentIntent, PaymentIntentService $service): PaymentIntentResource
    {
        abort_unless($paymentIntent->tenant_id === $context->requireId() && $paymentIntent->user_id === $request->user()->id, 404);
        return new PaymentIntentResource($service->cancel($paymentIntent, $request->user()));
    }

    public function simulate(Request $request, TenantContext $context, PaymentIntent $paymentIntent, PaymentWebhookService $webhooks): PaymentIntentResource
    {
        abort_unless($paymentIntent->tenant_id === $context->requireId() && $paymentIntent->user_id === $request->user()->id, 404);
        $webhooks->simulateCapture($paymentIntent);
        return new PaymentIntentResource($paymentIntent->fresh(['providerAccount', 'wallet.account', 'attempts', 'deposit', 'refunds']));
    }
}
