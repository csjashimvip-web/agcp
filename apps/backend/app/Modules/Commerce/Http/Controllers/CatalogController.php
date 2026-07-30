<?php
namespace Modules\Commerce\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Commerce\Http\Resources\CatalogItemResource;
use Modules\Commerce\Infrastructure\Models\CatalogCategory;
use Modules\Commerce\Infrastructure\Models\CatalogItem;
use Modules\Tenancy\Application\TenantContext;
final class CatalogController extends Controller
{
    public function index(Request $request, TenantContext $tenant)
    {
        $query=CatalogItem::query()->with(['category','variants.prices.priceList','variants.inventoryLevels'])
            ->where('tenant_id',$tenant->requireId())->where('status','active')->whereNotNull('published_at');
        if($request->filled('type'))$query->where('type',(string)$request->string('type'));
        if($request->filled('category'))$query->whereHas('category',fn($q)=>$q->where('slug',(string)$request->string('category')));
        if($request->filled('q'))$query->where(fn($q)=>$q->where('name','like','%'.(string)$request->string('q').'%')->orWhere('summary','like','%'.(string)$request->string('q').'%'));
        return CatalogItemResource::collection($query->orderBy('name')->paginate(24));
    }
    public function show(string $slug, TenantContext $tenant): CatalogItemResource
    {
        $item=CatalogItem::query()->with(['category','variants.prices.priceList','variants.inventoryLevels'])
            ->where('tenant_id',$tenant->requireId())->where('slug',$slug)->where('status','active')->whereNotNull('published_at')->firstOrFail();
        return new CatalogItemResource($item);
    }
    public function categories(TenantContext $tenant)
    {
        return response()->json(['data'=>CatalogCategory::query()->where('tenant_id',$tenant->requireId())->where('status','active')->orderBy('sort_order')->orderBy('name')->get(['id','name','slug','parent_id'])]);
    }
}
