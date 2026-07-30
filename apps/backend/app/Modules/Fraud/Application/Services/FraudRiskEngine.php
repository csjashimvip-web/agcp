<?php
namespace Modules\Fraud\Application\Services;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Fraud\Application\ValueObjects\FraudAssessmentResult;
use Modules\Fraud\Domain\Enums\FraudDecision;
use Modules\Fraud\Domain\Enums\RiskLevel;
use Modules\Fraud\Infrastructure\Models\FraudRiskAssessment;
use Modules\Fraud\Infrastructure\Models\FraudSignal;
use Modules\Identity\Infrastructure\Models\UserDevice;
use Modules\Rules\Application\Services\RuleEngine;
use Modules\Rules\Domain\Enums\RuleScope;
use Modules\Wallet\Infrastructure\Models\DepositRequest;
final class FraudRiskEngine
{
    public function __construct(private readonly RuleEngine $rules) {}
    public function assessCheckout(User $user,string $tenantId,int $totalMinor,string $cartId,array $context=[]): FraudAssessmentResult
    {
        $signals=[];
        $trusted=UserDevice::query()->where('user_id',$user->id)->whereNotNull('trusted_at')->exists();
        if(!$trusted) $signals[]=$this->signal('untrusted_device',15,'medium','No trusted device is registered for this account.',['user_id'=>$user->id]);
        if($user->created_at && $user->created_at->gt(now()->subDay())) $signals[]=$this->signal('new_account',15,'medium','Account age is less than 24 hours.',['created_at'=>$user->created_at->toIso8601String()]);
        if($totalMinor >= (int)config('risk.high_value_minor',50000)) $signals[]=$this->signal('high_value_order',30,'high','Order value exceeds the high-value threshold.',['total_minor'=>$totalMinor]);
        if($totalMinor >= (int)config('risk.critical_value_minor',200000)) $signals[]=$this->signal('critical_value_order',45,'critical','Order value exceeds the critical-value threshold.',['total_minor'=>$totalMinor]);
        $rejected=DepositRequest::query()->where('tenant_id',$tenantId)->where('user_id',$user->id)->where('status','rejected')->where('created_at','>=',now()->subDays(7))->count();
        if($rejected>=2) $signals[]=$this->signal('repeated_rejected_deposits',20,'high','Multiple deposit requests were rejected recently.',['count'=>$rejected]);
        if(($context['ip_reputation'] ?? null)==='high_risk') $signals[]=$this->signal('high_risk_ip',35,'critical','The request originated from a high-risk network.',['ip'=>$context['ip'] ?? null]);

        $ruleContext=['order'=>['total_minor'=>$totalMinor,'currency'=>$context['currency'] ?? null],'customer'=>['id'=>$user->id,'status'=>$user->status],'device'=>['trusted'=>$trusted],'risk'=>['base_score'=>array_sum(array_column($signals,'score'))]]+$context;
        $evaluation=$this->rules->evaluate($tenantId,RuleScope::Fraud,$ruleContext,'checkout',$cartId);
        $forcedDecision=null;
        foreach($evaluation->actions as $action) {
            if(($action['type'] ?? null)==='risk_score') $signals[]=$this->signal('rule:'.$action['rule_id'],max(0,(int)($action['value'] ?? 0)),'high','Risk score added by rule '.$action['rule_name'].'.',['rule_id'=>$action['rule_id']]);
            if(($action['type'] ?? null)==='decision') $forcedDecision=(string)($action['value'] ?? '');
        }
        $score=min(100,array_sum(array_column($signals,'score')));
        $level=$score>=80?RiskLevel::Critical:($score>=60?RiskLevel::High:($score>=30?RiskLevel::Medium:RiskLevel::Low));
        $decision=match(true) {
            in_array($forcedDecision,['allow','step_up','review','block'],true) => FraudDecision::from($forcedDecision),
            $score >= (int)config('risk.block_score',80) => FraudDecision::Block,
            $score >= (int)config('risk.review_score',60) => FraudDecision::Review,
            $score >= 30 => FraudDecision::StepUp,
            default => FraudDecision::Allow,
        };
        $assessment=DB::transaction(function() use($tenantId,$user,$cartId,$score,$level,$decision,$context,$signals): FraudRiskAssessment {
            $assessment=FraudRiskAssessment::query()->create(['tenant_id'=>$tenantId,'user_id'=>$user->id,'subject_type'=>'checkout','subject_id'=>$cartId,'score'=>$score,'level'=>$level,'decision'=>$decision,'status'=>'open','context'=>$context]);
            foreach($signals as $signal) FraudSignal::query()->create(['assessment_id'=>$assessment->id]+$signal);
            return $assessment;
        },5);
        return new FraudAssessmentResult($assessment->id,$score,$level,$decision,$signals);
    }
    private function signal(string $code,int $score,string $severity,string $message,array $evidence=[]): array { return compact('code','score','severity','message','evidence'); }
}
