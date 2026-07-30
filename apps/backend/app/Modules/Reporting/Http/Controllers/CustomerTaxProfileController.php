<?php
namespace Modules\Reporting\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Reporting\Infrastructure\Models\CustomerTaxProfile;
use Modules\Tenancy\Application\TenantContext;
final class CustomerTaxProfileController extends Controller
{
    public function show(TenantContext $context):array{$profile=CustomerTaxProfile::query()->firstOrCreate(['tenant_id'=>$context->requireId(),'user_id'=>request()->user()->id],['legal_name'=>request()->user()->name,'tax_exempt'=>false]);return ['data'=>$profile];}
    public function update(Request $request,TenantContext $context):array{$data=$request->validate(['legal_name'=>['nullable','string','max:190'],'tax_identifier'=>['nullable','string','max:120'],'country_code'=>['nullable','string','size:2'],'region_code'=>['nullable','string','max:80'],'address'=>['nullable','array'],'tax_exempt'=>['nullable','boolean'],'exemption_reference'=>['nullable','string','max:190']]);if(isset($data['country_code']))$data['country_code']=strtoupper($data['country_code']);$profile=CustomerTaxProfile::query()->updateOrCreate(['tenant_id'=>$context->requireId(),'user_id'=>$request->user()->id],$data);return ['data'=>$profile];}
}
