<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Rules\Infrastructure\Models\Rule;
use Modules\Rules\Infrastructure\Models\RuleVersion;
use Modules\Tenancy\Infrastructure\Models\Tenant;
final class RulesFraudSeeder extends Seeder
{
    public function run(): void
    {
        $tenant=Tenant::query()->where('slug','araabi-global')->firstOrFail();
        $definitions=[
            ['name'=>'Volume discount','slug'=>'volume-discount','scope'=>'pricing','priority'=>100,'conditions'=>[['field'=>'pricing.quantity','operator'=>'gte','value'=>3]],'actions'=>[['type'=>'discount_percent','value'=>5]]],
            ['name'=>'High value manual review','slug'=>'high-value-review','scope'=>'fraud','priority'=>100,'conditions'=>[['field'=>'order.total_minor','operator'=>'gte','value'=>50000]],'actions'=>[['type'=>'risk_score','value'=>30]]],
            ['name'=>'Critical value block','slug'=>'critical-value-block','scope'=>'fraud','priority'=>10,'stop_on_match'=>true,'conditions'=>[['field'=>'order.total_minor','operator'=>'gte','value'=>200000]],'actions'=>[['type'=>'decision','value'=>'block']]],
        ];
        foreach($definitions as $definition) {
            $rule=Rule::query()->updateOrCreate(['tenant_id'=>$tenant->id,'slug'=>$definition['slug']],['name'=>$definition['name'],'scope'=>$definition['scope'],'status'=>'active','priority'=>$definition['priority'],'stop_on_match'=>$definition['stop_on_match'] ?? false,'published_version'=>1]);
            $payload=['condition_mode'=>'all','conditions'=>$definition['conditions'],'actions'=>$definition['actions']];
            RuleVersion::query()->updateOrCreate(['rule_id'=>$rule->id,'version'=>1],$payload+['checksum'=>hash('sha256',json_encode($payload,JSON_THROW_ON_ERROR)),'published_at'=>now()]);
        }
    }
}
