<?php
namespace Modules\Rules\Application\Services;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Audit\Application\AuditLogger;
use Modules\Rules\Domain\Enums\RuleScope;
use Modules\Rules\Infrastructure\Models\Rule;
use Modules\Rules\Infrastructure\Models\RuleVersion;
final class RuleManagementService
{
    public function __construct(private readonly AuditLogger $audit) {}
    public function create(string $tenantId, User $actor, array $input): Rule
    {
        return DB::transaction(function() use($tenantId,$actor,$input): Rule {
            $rule=Rule::query()->create([
                'tenant_id'=>$tenantId,'name'=>$input['name'],'slug'=>$input['slug'] ?? Str::slug($input['name']),
                'scope'=>RuleScope::from($input['scope']),'status'=>'draft','priority'=>$input['priority'] ?? 100,
                'stop_on_match'=>$input['stop_on_match'] ?? false,'created_by'=>$actor->id,'updated_by'=>$actor->id,
            ]);
            $this->createVersion($rule,$actor,$input['condition_mode'] ?? 'all',$input['conditions'] ?? [],$input['actions'] ?? []);
            $this->audit->record('rules.rule.created',Rule::class,$rule->id,['scope'=>$rule->scope->value],[],$tenantId,User::class,$actor->id);
            return $rule->fresh('versions');
        },5);
    }
    public function revise(Rule $rule, User $actor, array $input): Rule
    {
        return DB::transaction(function() use($rule,$actor,$input): Rule {
            $rule->forceFill([
                'name'=>$input['name'] ?? $rule->name,'priority'=>$input['priority'] ?? $rule->priority,
                'stop_on_match'=>$input['stop_on_match'] ?? $rule->stop_on_match,'updated_by'=>$actor->id,
            ])->save();
            if(array_key_exists('conditions',$input) || array_key_exists('actions',$input)) {
                $latest=$rule->versions()->orderByDesc('version')->first();
                $this->createVersion($rule,$actor,$input['condition_mode'] ?? $latest?->condition_mode ?? 'all',$input['conditions'] ?? $latest?->conditions ?? [],$input['actions'] ?? $latest?->actions ?? []);
                $rule->forceFill(['status'=>'draft'])->save();
            }
            return $rule->fresh('versions');
        },5);
    }
    public function publish(Rule $rule, User $actor): Rule
    {
        $latest=$rule->versions()->orderByDesc('version')->firstOrFail();
        $latest->forceFill(['published_at'=>now()])->save();
        $rule->forceFill(['status'=>'active','published_version'=>$latest->version,'updated_by'=>$actor->id])->save();
        $this->audit->record('rules.rule.published',Rule::class,$rule->id,['version'=>$latest->version],[],$rule->tenant_id,User::class,$actor->id);
        return $rule->fresh('versions');
    }
    public function pause(Rule $rule, User $actor): Rule
    {
        $rule->forceFill(['status'=>'paused','updated_by'=>$actor->id])->save();
        return $rule->fresh('versions');
    }
    private function createVersion(Rule $rule, User $actor, string $mode, array $conditions, array $actions): RuleVersion
    {
        $version=((int)$rule->versions()->max('version'))+1;
        $payload=['condition_mode'=>$mode,'conditions'=>$conditions,'actions'=>$actions];
        return RuleVersion::query()->create(['rule_id'=>$rule->id,'version'=>$version]+$payload+['checksum'=>hash('sha256',json_encode($payload,JSON_THROW_ON_ERROR)),'created_by'=>$actor->id]);
    }
}
