<?php
namespace Modules\Fraud\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Fraud\Http\Resources\FraudAssessmentResource;
use Modules\Fraud\Infrastructure\Models\FraudRiskAssessment;
use Modules\Tenancy\Application\TenantContext;
final class FraudAssessmentController extends Controller
{
    public function index(Request $request,TenantContext $tenant) { return FraudAssessmentResource::collection(FraudRiskAssessment::query()->with(['signals','order'])->where('tenant_id',$tenant->requireId())->where('user_id',$request->user()->id)->latest()->paginate(30)); }
}
