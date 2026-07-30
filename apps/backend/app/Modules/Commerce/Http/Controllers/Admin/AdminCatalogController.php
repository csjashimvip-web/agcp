<?php
namespace Modules\Commerce\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Commerce\Http\Resources\CatalogItemResource;
use Modules\Commerce\Infrastructure\Models\CatalogCategory;
use Modules\Commerce\Infrastructure\Models\CatalogItem;
use Modules\Commerce\Infrastructure\Models\CatalogVariant;
use Modules\Tenancy\Application\TenantContext;
final class AdminCatalogController extends Controller
{
    public function categories(TenantContext $tenant)
    { return response()->json(['data'=>CatalogCategory::query()->where('tenant_id',$tenant->requireId())->orderBy('sort_order')->orderBy('name')->get()]); }
    public function storeCategory(Request $request,TenantContext $tenant)
    {
        $tenantId=$tenant->requireId();
        $data=$request->validate(['name'=>['required','string','max:160'],'slug'=>['nullable','string','max:180'],'description'=>['nullable','string'],'parent_id'=>['nullable','uuid'],'status'=>['sometimes',Rule::in(['active','inactive'])],'sort_order'=>['sometimes','integer','min:0']]);
        $slug=Str::slug($data['slug']??$data['name']);
        if(CatalogCategory::query()->where(['tenant_id'=>$tenantId,'slug'=>$slug])->exists())$slug.='-'.Str::lower(Str::random(5));
        $category=CatalogCategory::query()->create(['tenant_id'=>$tenantId,'name'=>$data['name'],'slug'=>$slug,'description'=>$data['description']??null,'parent_id'=>$data['parent_id']??null,'status'=>$data['status']??'active','sort_order'=>$data['sort_order']??0]);
        return response()->json(['data'=>$category],201);
    }
    public function updateCategory(Request $request,CatalogCategory $category,TenantContext $tenant)
    {
        abort_unless($category->tenant_id===$tenant->requireId(),404);
        $data=$request->validate(['name'=>['sometimes','string','max:160'],'description'=>['nullable','string'],'parent_id'=>['nullable','uuid'],'status'=>['sometimes',Rule::in(['active','inactive'])],'sort_order'=>['sometimes','integer','min:0']]);
        $category->fill($data)->save(); return response()->json(['data'=>$category->fresh()]);
    }
    public function items(TenantContext $tenant)
    { return CatalogItemResource::collection(CatalogItem::query()->with(['category','variants.prices.priceList','variants.inventoryLevels'])->where('tenant_id',$tenant->requireId())->latest()->paginate(40)); }
    public function storeItem(Request $request,TenantContext $tenant):CatalogItemResource
    {
        $tenantId=$tenant->requireId();
        $data=$request->validate([
            'name'=>['required','string','max:190'],'slug'=>['nullable','string','max:210'],'sku'=>['required','string','max:120'],
            'type'=>['required',Rule::in(['physical','digital','service'])],'category_id'=>['nullable','uuid'],'summary'=>['nullable','string','max:500'],'description'=>['nullable','string'],
            'status'=>['sometimes',Rule::in(['draft','active','inactive'])],'fulfillment_mode'=>['sometimes','string','max:40'],'inventory_tracking'=>['sometimes','boolean'],'allow_backorder'=>['sometimes','boolean'],
            'service_schema'=>['nullable','array'],'variant_name'=>['sometimes','string','max:160'],'variant_sku'=>['nullable','string','max:140'],'attributes'=>['nullable','array'],
        ]);
        return DB::transaction(function()use($data,$tenantId){
            $slug=Str::slug($data['slug']??$data['name']);
            if(CatalogItem::query()->where(['tenant_id'=>$tenantId,'slug'=>$slug])->exists())$slug.='-'.Str::lower(Str::random(5));
            $item=CatalogItem::query()->create([
                'tenant_id'=>$tenantId,'category_id'=>$data['category_id']??null,'type'=>$data['type'],'name'=>$data['name'],'slug'=>$slug,'sku'=>$data['sku'],
                'summary'=>$data['summary']??null,'description'=>$data['description']??null,'status'=>$data['status']??'draft','fulfillment_mode'=>$data['fulfillment_mode']??'manual',
                'inventory_tracking'=>$data['inventory_tracking']??($data['type']==='physical'),'allow_backorder'=>$data['allow_backorder']??false,
                'service_schema'=>$data['service_schema']??null,'published_at'=>($data['status']??'draft')==='active'?now():null,
            ]);
            CatalogVariant::query()->create(['catalog_item_id'=>$item->id,'name'=>$data['variant_name']??'Default','sku'=>$data['variant_sku']??$data['sku'],'attributes'=>$data['attributes']??[],'status'=>'active','is_default'=>true]);
            return new CatalogItemResource($item->fresh(['category','variants.prices.priceList','variants.inventoryLevels']));
        },5);
    }
    public function updateItem(Request $request,CatalogItem $item,TenantContext $tenant):CatalogItemResource
    {
        abort_unless($item->tenant_id===$tenant->requireId(),404);
        $data=$request->validate(['name'=>['sometimes','string','max:190'],'summary'=>['nullable','string','max:500'],'description'=>['nullable','string'],'category_id'=>['nullable','uuid'],'status'=>['sometimes',Rule::in(['draft','active','inactive'])],'fulfillment_mode'=>['sometimes','string','max:40'],'inventory_tracking'=>['sometimes','boolean'],'allow_backorder'=>['sometimes','boolean'],'service_schema'=>['nullable','array']]);
        if(($data['status']??null)==='active'&&$item->published_at===null)$data['published_at']=now();
        if(($data['status']??null)!==null&&$data['status']!=='active')$data['published_at']=null;
        $item->fill($data)->save(); return new CatalogItemResource($item->fresh(['category','variants.prices.priceList','variants.inventoryLevels']));
    }
}
