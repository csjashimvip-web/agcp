<?php
namespace Modules\SaaS\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\SaaS\Application\Services\EntitlementService;
use Modules\SaaS\Infrastructure\Models\TenantBrandingProfile;
use Modules\Tenancy\Application\TenantContext;

final class TenantConfigurationController extends Controller
{
    public function __invoke(TenantContext $context, EntitlementService $entitlements): array
    {
        $tenant = $context->tenant();
        $branding = TenantBrandingProfile::query()->where('tenant_id', $context->requireId())->first();
        return ['data' => [
            'tenant' => ['id' => $tenant?->id, 'name' => $tenant?->name, 'slug' => $tenant?->slug, 'currency' => $tenant?->default_currency, 'timezone' => $tenant?->timezone],
            'branding' => $branding ? [
                'display_name' => $branding->display_name, 'legal_name' => $branding->legal_name, 'logo_url' => $branding->logo_url,
                'favicon_url' => $branding->favicon_url, 'primary_color' => $branding->primary_color,
                'secondary_color' => $branding->secondary_color, 'support_email' => $branding->support_email,
                'support_url' => $branding->support_url, 'locale' => $branding->locale,
            ] : null,
            'entitlements' => $entitlements->snapshot($context->requireId()),
        ]];
    }
}
