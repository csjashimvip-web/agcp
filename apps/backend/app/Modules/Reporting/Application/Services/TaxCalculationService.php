<?php
namespace Modules\Reporting\Application\Services;
use Modules\Reporting\Infrastructure\Models\CustomerTaxProfile;
use Modules\Reporting\Infrastructure\Models\TaxRate;
final class TaxCalculationService
{
    public function resolve(string $tenantId,string $itemType,?CustomerTaxProfile $customer=null):?TaxRate
    {
        if($customer?->tax_exempt)return null;
        return TaxRate::query()->where('tenant_id',$tenantId)->where('status','active')
            ->where(fn($q)=>$q->where('applies_to','all')->orWhere('applies_to',$itemType))
            ->where(fn($q)=>$q->whereNull('valid_from')->orWhere('valid_from','<=',now()))
            ->where(fn($q)=>$q->whereNull('valid_until')->orWhere('valid_until','>=',now()))
            ->orderByRaw("CASE WHEN applies_to = ? THEN 0 ELSE 1 END",[$itemType])->first();
    }
    public function split(int $grossMinor,?TaxRate $rate):array
    {
        $bps=(int)($rate?->rate_basis_points??0);
        if($bps<=0)return ['net_minor'=>$grossMinor,'tax_minor'=>0,'gross_minor'=>$grossMinor,'rate_basis_points'=>0];
        if($rate?->price_inclusive){$tax=(int)round($grossMinor*$bps/(10000+$bps));return ['net_minor'=>$grossMinor-$tax,'tax_minor'=>$tax,'gross_minor'=>$grossMinor,'rate_basis_points'=>$bps];}
        $tax=(int)round($grossMinor*$bps/10000);return ['net_minor'=>$grossMinor,'tax_minor'=>$tax,'gross_minor'=>$grossMinor+$tax,'rate_basis_points'=>$bps];
    }
}
