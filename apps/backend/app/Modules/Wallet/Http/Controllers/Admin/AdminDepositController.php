<?php
namespace Modules\Wallet\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Tenancy\Application\TenantContext;
use Modules\Wallet\Application\Services\DepositService;
use Modules\Wallet\Domain\Enums\DepositStatus;
use Modules\Wallet\Http\Resources\DepositResource;
use Modules\Wallet\Infrastructure\Models\DepositRequest;
final class AdminDepositController extends Controller
{
    public function __construct(private readonly TenantContext $tenant, private readonly DepositService $service) {}
    public function index(Request $request)
    {
        $status = $request->string('status')->toString();
        $query = DepositRequest::query()->with('user')->where('tenant_id', $this->tenant->requireId())->latest('submitted_at');
        if ($status !== '') $query->where('status', $status);
        return DepositResource::collection($query->paginate(40));
    }
    public function show(DepositRequest $deposit): DepositResource
    {
        abort_unless($deposit->tenant_id === $this->tenant->requireId(), 404);
        return new DepositResource($deposit->load('user', 'wallet.account', 'reviewer'));
    }
    public function approve(Request $request, DepositRequest $deposit): DepositResource
    {
        abort_unless($deposit->tenant_id === $this->tenant->requireId(), 404);
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);
        return new DepositResource($this->service->approve($deposit, $request->user(), $validated['note'] ?? null, $request->header('Idempotency-Key')));
    }
    public function reject(Request $request, DepositRequest $deposit): DepositResource
    {
        abort_unless($deposit->tenant_id === $this->tenant->requireId(), 404);
        $validated = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        return new DepositResource($this->service->reject($deposit, $request->user(), $validated['note']));
    }
}
