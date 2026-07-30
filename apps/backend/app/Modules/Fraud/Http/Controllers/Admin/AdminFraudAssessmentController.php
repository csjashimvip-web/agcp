<?php
namespace Modules\Fraud\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Fraud\Application\Services\FraudReviewService;
use Modules\Fraud\Http\Resources\FraudAssessmentResource;
use Modules\Fraud\Infrastructure\Models\FraudRiskAssessment;
use Modules\Tenancy\Application\TenantContext;
final class AdminFraudAssessmentController extends Controller
{
    public function index(Request $request,TenantContext $tenant) { $query=FraudRiskAssessment::query()->with(['signals','user','order'])->where('tenant_id',$tenant->requireId()); if($request->filled('status'))$query->where('status',(string)$request->string('status')); if($request->filled('decision'))$query->where('decision',(string)$request->string('decision')); return FraudAssessmentResource::collection($query->orderByDesc('score')->latest()->paginate(50)); }
    public function approve(Request $request,FraudRiskAssessment $assessment,TenantContext $tenant,FraudReviewService $service): FraudAssessmentResource { abort_unless($assessment->tenant_id===$tenant->requireId(),404); $data=$request->validate(['note'=>['nullable','string','max:2000']]); return new FraudAssessmentResource($service->approve($assessment,$request->user(),$data['note'] ?? null)); }
    public function reject(Request $request,FraudRiskAssessment $assessment,TenantContext $tenant,FraudReviewService $service): FraudAssessmentResource { abort_unless($assessment->tenant_id===$tenant->requireId(),404); $data=$request->validate(['note'=>['nullable','string','max:2000']]); return new FraudAssessmentResource($service->reject($assessment,$request->user(),$data['note'] ?? null)); }
}
