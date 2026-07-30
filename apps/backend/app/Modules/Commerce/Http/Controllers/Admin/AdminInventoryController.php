<?php
namespace Modules\Commerce\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Commerce\Infrastructure\Models\CatalogVariant;
use Modules\Commerce\Infrastructure\Models\InventoryLevel;
use Modules\Commerce\Infrastructure\Models\InventoryLocation;
use Modules\Tenancy\Application\TenantContext;
final class AdminInventoryController extends Controller
{
    public function index(TenantContext $tenant)
    { return response()->json(['data'=>InventoryLocation::query()->with('levels.variant.item')->where('tenant_id',$tenant->requireId())->orderBy('name')->get()]); }
    public function upsert(Request $request,TenantContext $tenant)
    {
        $tenantId=$tenant->requireId();
        $data=$request->validate(['variant_id'=>['required','uuid'],'location_code'=>['sometimes','string','max:80'],'location_name'=>['sometimes','string','max:140'],'on_hand'=>['required','integer','min:0'],'safety_stock'=>['sometimes','integer','min:0']]);
        $variant=CatalogVariant::query()->whereKey($data['variant_id'])->whereHas('item',fn($q)=>$q->where('tenant_id',$tenantId))->firstOrFail();
        $code=$data['location_code']??'MAIN';
        $location=InventoryLocation::query()->firstOrCreate(['tenant_id'=>$tenantId,'code'=>$code],['name'=>$data['location_name']??'Main inventory','status'=>'active']);
        $level=InventoryLevel::query()->firstOrNew(['inventory_location_id'=>$location->id,'catalog_variant_id'=>$variant->id]);
        if ((int)$data['on_hand'] < (int)($level->reserved ?? 0)) abort(422, 'On-hand inventory cannot be lower than reserved inventory.');
        $level->fill(['on_hand'=>$data['on_hand'],'safety_stock'=>$data['safety_stock']??0])->save();
        return response()->json(['data'=>$level->load('location','variant.item')]);
    }
}
