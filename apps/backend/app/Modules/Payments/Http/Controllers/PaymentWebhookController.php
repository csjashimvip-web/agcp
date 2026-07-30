<?php
namespace Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Payments\Application\Services\PaymentWebhookService;
use Modules\Payments\Infrastructure\Models\PaymentProviderAccount;
use Modules\Tenancy\Application\TenantContext;

final class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, TenantContext $context, PaymentWebhookService $service, string $provider, ?string $accountCode = null): JsonResponse
    {
        $query = PaymentProviderAccount::query()->where([
            'tenant_id' => $context->requireId(),
            'provider' => $provider,
            'status' => 'active',
        ]);
        $accountCode = trim((string) ($accountCode ?: $request->header('X-AGCP-Provider-Account')));
        if ($accountCode !== '') $query->where('code', $accountCode);
        $account = $query->orderBy('priority')->firstOrFail();
        $webhook = $service->ingest($account, $request->getContent(), $request->headers->all());
        return response()->json(['data' => ['received' => true, 'webhook_id' => $webhook->id, 'status' => $webhook->status->value]], 202);
    }
}
