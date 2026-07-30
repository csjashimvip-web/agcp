<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Modules\Reporting\Infrastructure\Models\ReportSchedule;
use Modules\Reporting\Infrastructure\Models\TaxRate;
use Modules\Reporting\Infrastructure\Models\TenantTaxProfile;
use Modules\Tenancy\Infrastructure\Models\Tenant;
final class ReportingSeeder extends Seeder
{
    public function run():void
    {
        $tenant=Tenant::query()->where('slug','araabi-global')->firstOrFail();
        TenantTaxProfile::query()->firstOrCreate(['tenant_id'=>$tenant->id],['legal_name'=>$tenant->name,'country_code'=>'BD','invoice_prefix'=>'AGCP','next_invoice_sequence'=>1,'default_tax_behavior'=>'inclusive','invoice_footer'=>'Thank you for choosing Araabi Global.','status'=>'active']);
        TaxRate::query()->firstOrCreate(['tenant_id'=>$tenant->id,'code'=>'ZERO'],['name'=>'Zero-rated','jurisdiction'=>'Default','country_code'=>'BD','rate_basis_points'=>0,'price_inclusive'=>true,'applies_to'=>'all','status'=>'active']);
        ReportSchedule::query()->firstOrCreate(['tenant_id'=>$tenant->id,'name'=>'Monthly invoice export'],['report_type'=>'invoices','frequency'=>'monthly','timezone'=>$tenant->timezone?:'UTC','recipients'=>[],'filters'=>[],'enabled'=>false]);
    }
}
