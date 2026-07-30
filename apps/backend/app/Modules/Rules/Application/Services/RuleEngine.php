<?php
namespace Modules\Rules\Application\Services;
use Illuminate\Support\Str;
use Modules\Rules\Application\ValueObjects\RuleEvaluationResult;
use Modules\Rules\Domain\Enums\RuleScope;
use Modules\Rules\Infrastructure\Models\Rule;
use Modules\Rules\Infrastructure\Models\RuleExecution;
final class RuleEngine
{
    public function __construct(private readonly ConditionEvaluator $conditions) {}

    public function evaluate(string $tenantId, RuleScope $scope, array $context, ?string $contextType=null, ?string $contextId=null): RuleEvaluationResult
    {
        $rules=Rule::query()->with('versions')->where('tenant_id',$tenantId)->where('scope',$scope->value)->where('status','active')->whereNotNull('published_version')->orderBy('priority')->orderBy('id')->get();
        $matched=[]; $actions=[]; $executions=[];
        foreach($rules as $rule) {
            $version=$rule->versions->firstWhere('version',$rule->published_version);
            if(!$version) continue;
            $isMatch=$this->conditions->matches($version->conditions ?? [],$version->condition_mode,$context);
            $execution=RuleExecution::query()->create([
                'tenant_id'=>$tenantId,'rule_id'=>$rule->id,'rule_version_id'=>$version->id,'scope'=>$scope->value,
                'context_type'=>$contextType,'context_id'=>$contextId,'matched'=>$isMatch,
                'input_snapshot'=>$this->redact($context),'result_snapshot'=>$isMatch ? ['actions'=>$version->actions] : ['actions'=>[]],
                'correlation_id'=>request()?->attributes->get('correlation_id') ?? (string)Str::uuid(),'executed_at'=>now(),
            ]);
            $executions[]=$execution->id;
            if(!$isMatch) continue;
            $matched[]=$rule->id;
            foreach($version->actions ?? [] as $action) $actions[]=['rule_id'=>$rule->id,'rule_name'=>$rule->name]+$action;
            if($rule->stop_on_match) break;
        }
        return new RuleEvaluationResult($matched,$actions,$executions);
    }

    private function redact(array $context): array
    {
        foreach(['password','token','secret','credentials'] as $key) if(array_key_exists($key,$context)) $context[$key]='[redacted]';
        return $context;
    }
}
