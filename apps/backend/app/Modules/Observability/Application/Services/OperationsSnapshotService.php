<?php
namespace Modules\Observability\Application\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Modules\Integrations\Infrastructure\Models\WebhookDelivery;
use Modules\Notifications\Infrastructure\Models\NotificationDelivery;
use Modules\Observability\Infrastructure\Models\OperationalIncident;
use Modules\Observability\Infrastructure\Models\OperationsSnapshot;
use Modules\Payments\Infrastructure\Models\PaymentReconciliationItem;
use Modules\Shared\Infrastructure\Models\OutboxMessage;
use Modules\Suppliers\Infrastructure\Models\SupplierAccount;
use Modules\Support\Infrastructure\Models\SupportTicket;
final class OperationsSnapshotService
{
    public function capture(?string $tenantId=null):OperationsSnapshot
    {
        $scope=fn($q)=>$tenantId?$q->where('tenant_id',$tenantId):$q;
        $checks=['database'=>true,'redis'=>false];try{DB::select('select 1');}catch(\Throwable){$checks['database']=false;}try{$checks['redis']=Redis::connection()->ping()!==false;}catch(\Throwable){$checks['redis']=false;}
        $queueDepth=0;try{foreach(['critical','payments','wallet','supplier','webhooks','notifications','emails','sms','invoices','reports','default'] as $queue)$queueDepth+=(int)Redis::connection()->llen('queues:'.$queue);}catch(\Throwable){}
        $metrics=['queue_depth'=>$queueDepth,'failed_jobs'=>(int)DB::table('failed_jobs')->count(),'outbox_pending'=>(int)$scope(OutboxMessage::query())->whereNull('published_at')->whereNull('failed_at')->count(),'outbox_failed'=>(int)$scope(OutboxMessage::query())->whereNotNull('failed_at')->count(),'webhook_pending'=>(int)$scope(WebhookDelivery::query())->whereIn('status',['queued','sending','failed'])->count(),'webhook_failed'=>(int)$scope(WebhookDelivery::query())->where('status','dead')->count(),'notification_pending'=>(int)$scope(NotificationDelivery::query())->whereIn('status',['queued','sending'])->count(),'notification_failed'=>(int)$scope(NotificationDelivery::query())->where('status','failed')->count(),'open_support_tickets'=>(int)$scope(SupportTicket::query())->whereNotIn('status',['resolved','closed'])->count(),'overdue_support_tickets'=>(int)$scope(SupportTicket::query())->whereNotIn('status',['resolved','closed'])->where(fn($q)=>$q->where('resolution_due_at','<',now())->orWhere(fn($x)=>$x->where('first_response_due_at','<',now())->whereNull('first_responded_at')))->count(),'open_payment_mismatches'=>(int)PaymentReconciliationItem::query()->where('status','open')->whereHas('run',fn($q)=>$tenantId?$q->where('tenant_id',$tenantId):$q)->count(),'unhealthy_suppliers'=>(int)$scope(SupplierAccount::query())->where(fn($q)=>$q->where('status','!=','active')->orWhere('health_score','<',60))->count()];
        $status=(!$checks['database']||!$checks['redis']||$metrics['webhook_failed']>0||$metrics['outbox_failed']>0)?'critical':(($metrics['queue_depth']>1000||$metrics['overdue_support_tickets']>0||$metrics['notification_failed']>10)?'degraded':'healthy');$snapshot=OperationsSnapshot::query()->create(['tenant_id'=>$tenantId,'status'=>$status]+$metrics+['checks'=>$checks,'captured_at'=>now()]);$this->syncIncidents($tenantId,$checks,$metrics);return $snapshot;
    }
    private function syncIncidents(?string $tenantId,array $checks,array $metrics):void
    {
        $signals=[];if(!$checks['database'])$signals[]=['database-unavailable','Database health check failed','critical','database',$checks];if(!$checks['redis'])$signals[]=['redis-unavailable','Redis health check failed','critical','redis',$checks];if($metrics['webhook_failed']>0)$signals[]=['webhook-dead-letter','Outbound webhooks reached dead-letter state','warning','webhooks',['count'=>$metrics['webhook_failed']]];if($metrics['outbox_failed']>0)$signals[]=['outbox-failed','Transactional outbox contains permanently failed messages','critical','outbox',['count'=>$metrics['outbox_failed']]];if($metrics['overdue_support_tickets']>0)$signals[]=['support-sla-overdue','Support tickets exceeded SLA','warning','support',['count'=>$metrics['overdue_support_tickets']]];
        $active=[];foreach($signals as[$fingerprint,$title,$severity,$source,$evidence]){$active[]=$fingerprint;OperationalIncident::query()->updateOrCreate(['tenant_id'=>$tenantId,'fingerprint'=>$fingerprint],['title'=>$title,'description'=>$title.'. Review operational evidence and resolve the underlying condition.','severity'=>$severity,'status'=>'open','source'=>$source,'evidence'=>$evidence,'last_seen_at'=>now(),'resolved_at'=>null]);}OperationalIncident::query()->where('tenant_id',$tenantId)->where('status','open')->when($active,fn($q)=>$q->whereNotIn('fingerprint',$active))->update(['status'=>'resolved','resolved_at'=>now()]);
    }
}
