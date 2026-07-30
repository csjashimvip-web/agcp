<?php
namespace Modules\Fraud\Application\Services;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Application\AuditLogger;
use Modules\Commerce\Application\Services\OrderService;
use Modules\Fraud\Infrastructure\Models\FraudRiskAssessment;
use Modules\Suppliers\Application\Services\SupplierFulfillmentService;
final class FraudReviewService
{
    public function __construct(private readonly SupplierFulfillmentService $fulfillment,private readonly OrderService $orders,private readonly AuditLogger $audit) {}
    public function approve(FraudRiskAssessment $assessment,User $reviewer,?string $note=null): FraudRiskAssessment
    {
        return DB::transaction(function() use($assessment,$reviewer,$note): FraudRiskAssessment {
            $locked=FraudRiskAssessment::query()->with('order.items.variant.item')->whereKey($assessment->id)->lockForUpdate()->firstOrFail();
            if($locked->status!=='open') return $locked;
            $locked->forceFill(['status'=>'approved','reviewed_by'=>$reviewer->id,'reviewed_at'=>now(),'review_note'=>$note])->save();
            if($locked->order && $locked->order->fulfillment_status==='on_hold') {
                $locked->order->forceFill(['fulfillment_status'=>'unfulfilled'])->save();
                $locked->order->items()->where('status','on_hold')->update(['status'=>'pending']);
                $this->fulfillment->createForOrder($locked->order->fresh(['items.variant.item']));
            }
            $this->audit->record('fraud.assessment.approved',FraudRiskAssessment::class,$locked->id,['note'=>$note],[],$locked->tenant_id,User::class,$reviewer->id);
            return $locked->fresh(['signals','order']);
        },5);
    }
    public function reject(FraudRiskAssessment $assessment,User $reviewer,?string $note=null): FraudRiskAssessment
    {
        return DB::transaction(function() use($assessment,$reviewer,$note): FraudRiskAssessment {
            $locked=FraudRiskAssessment::query()->with('order')->whereKey($assessment->id)->lockForUpdate()->firstOrFail();
            if($locked->status!=='open') return $locked;
            if(!$locked->order) throw ValidationException::withMessages(['assessment'=>'No order is attached to this assessment.']);
            $this->orders->cancel($locked->order,$reviewer,$note ?? 'Order rejected by fraud review.');
            $locked->forceFill(['status'=>'rejected','reviewed_by'=>$reviewer->id,'reviewed_at'=>now(),'review_note'=>$note])->save();
            $this->audit->record('fraud.assessment.rejected',FraudRiskAssessment::class,$locked->id,['note'=>$note],[],$locked->tenant_id,User::class,$reviewer->id);
            return $locked->fresh(['signals','order']);
        },5);
    }
}
