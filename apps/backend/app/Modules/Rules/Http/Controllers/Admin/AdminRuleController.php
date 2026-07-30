<?php
namespace Modules\Rules\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule as ValidationRule;
use Modules\Rules\Application\Services\RuleManagementService;
use Modules\Rules\Http\Resources\RuleResource;
use Modules\Rules\Infrastructure\Models\Rule;
use Modules\Tenancy\Application\TenantContext;
final class AdminRuleController extends Controller
{
    public function index(Request $request,TenantContext $tenant) { $query=Rule::query()->with('versions')->where('tenant_id',$tenant->requireId()); if($request->filled('scope'))$query->where('scope',(string)$request->string('scope')); return RuleResource::collection($query->orderBy('priority')->paginate(50)); }
    public function store(Request $request,TenantContext $tenant,RuleManagementService $service): RuleResource { $data=$this->validated($request,true); return new RuleResource($service->create($tenant->requireId(),$request->user(),$data)); }
    public function update(Request $request,Rule $rule,TenantContext $tenant,RuleManagementService $service): RuleResource { abort_unless($rule->tenant_id===$tenant->requireId(),404); $data=$this->validated($request,false); return new RuleResource($service->revise($rule,$request->user(),$data)); }
    public function publish(Request $request,Rule $rule,TenantContext $tenant,RuleManagementService $service): RuleResource { abort_unless($rule->tenant_id===$tenant->requireId(),404); return new RuleResource($service->publish($rule,$request->user())); }
    public function pause(Request $request,Rule $rule,TenantContext $tenant,RuleManagementService $service): RuleResource { abort_unless($rule->tenant_id===$tenant->requireId(),404); return new RuleResource($service->pause($rule,$request->user())); }
    private function validated(Request $request,bool $create): array { return $request->validate([
        'name'=>[$create?'required':'sometimes','string','max:180'],'slug'=>['sometimes','string','max:180'],'scope'=>[$create?'required':'sometimes',ValidationRule::in(['pricing','fraud','operations'])],
        'priority'=>['sometimes','integer','min:0','max:100000'],'stop_on_match'=>['sometimes','boolean'],'condition_mode'=>['sometimes',ValidationRule::in(['all','any'])],
        'conditions'=>[$create?'required':'sometimes','array','max:50'],'conditions.*.field'=>['required','string','max:160'],'conditions.*.operator'=>['required',ValidationRule::in(['eq','neq','gt','gte','lt','lte','in','not_in','contains','between','exists','missing'])],'conditions.*.value'=>['nullable'],
        'actions'=>[$create?'required':'sometimes','array','max:20'],'actions.*.type'=>['required','string','max:80'],'actions.*.value'=>['nullable'],
    ]); }
}
