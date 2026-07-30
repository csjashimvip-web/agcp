<?php
namespace Modules\Analytics\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
final class AnalyticsSnapshot extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected function casts(): array
    {
        return [
            'period_start'=>'immutable_date','period_end'=>'immutable_date','orders_count'=>'integer','completed_orders_count'=>'integer',
            'gross_revenue_minor'=>'integer','net_revenue_minor'=>'integer','refunded_minor'=>'integer','discounts_minor'=>'integer',
            'surcharges_minor'=>'integer','unique_customers'=>'integer','average_order_value_minor'=>'integer','risk_review_count'=>'integer',
            'supplier_success_rate'=>'float','metrics'=>'array','calculated_at'=>'immutable_datetime',
        ];
    }
}
