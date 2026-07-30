<?php
namespace Modules\Analytics\Application\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Analytics\Domain\Enums\CustomerSegmentCode;
use Modules\Analytics\Infrastructure\Models\CustomerSegment;
use Modules\Identity\Infrastructure\Models\TenantMembership;

final class CustomerSegmentationService
{
    /** @return Collection<int, CustomerSegment> */
    public function refresh(string $tenantId): Collection
    {
        $userIds = TenantMembership::query()->where('tenant_id', $tenantId)->where('status', 'active')->pluck('user_id');
        if ($userIds->isEmpty()) {
            $userIds = DB::table('orders')->where('tenant_id', $tenantId)->distinct()->pluck('user_id');
        }

        return User::query()->whereIn('id', $userIds)->get()->map(function (User $user) use ($tenantId): CustomerSegment {
            $orders = DB::table('orders')->where('tenant_id', $tenantId)->where('user_id', $user->id)->where('status', '!=', 'canceled');
            $frequency = (int) (clone $orders)->count();
            $monetary = (int) (clone $orders)->sum('total_minor');
            $lastOrder = (clone $orders)->selectRaw('MAX(COALESCE(placed_at, created_at)) AS last_order_at')->value('last_order_at');
            $recency = $lastOrder ? (int) max(0, now()->diffInDays($lastOrder)) : null;
            $average = $frequency > 0 ? intdiv($monetary, $frequency) : 0;
            $segment = $this->classify($frequency, $monetary, $recency);
            $score = $this->score($frequency, $monetary, $recency);

            return CustomerSegment::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'user_id' => $user->id],
                [
                    'segment_code' => $segment,
                    'score' => $score,
                    'recency_days' => $recency,
                    'frequency_orders' => $frequency,
                    'monetary_minor' => $monetary,
                    'average_order_minor' => $average,
                    'last_order_at' => $lastOrder,
                    'signals' => ['frequency' => $frequency, 'monetary_minor' => $monetary, 'recency_days' => $recency],
                    'calculated_at' => now(),
                ],
            );
        });
    }

    private function classify(int $frequency, int $monetary, ?int $recency): CustomerSegmentCode
    {
        if ($frequency === 0) return CustomerSegmentCode::NewCustomer;
        if (($recency ?? 999) <= 30 && $frequency >= 5 && $monetary >= 100000) return CustomerSegmentCode::Champions;
        if (($recency ?? 999) <= 60 && $frequency >= 3) return CustomerSegmentCode::Loyal;
        if (($recency ?? 999) <= 30) return CustomerSegmentCode::Promising;
        if (($recency ?? 999) > 90 && $frequency >= 2) return CustomerSegmentCode::AtRisk;
        return CustomerSegmentCode::Dormant;
    }

    private function score(int $frequency, int $monetary, ?int $recency): int
    {
        $recencyScore = $recency === null ? 5 : max(0, 40 - min(40, intdiv($recency, 3)));
        $frequencyScore = min(30, $frequency * 6);
        $monetaryScore = min(30, intdiv($monetary, 10000));
        return min(100, $recencyScore + $frequencyScore + $monetaryScore);
    }
}
