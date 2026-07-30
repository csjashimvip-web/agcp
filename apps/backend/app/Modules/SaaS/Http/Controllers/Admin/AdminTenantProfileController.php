<?php
namespace Modules\SaaS\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\SaaS\Infrastructure\Models\TenantBrandingProfile;
use Modules\Tenancy\Application\TenantContext;

final class AdminTenantProfileController extends Controller
{
    public function show(TenantContext $context): array
    {
        return ['data' => TenantBrandingProfile::query()->firstOrCreate(['tenant_id' => $context->requireId()], ['display_name' => $context->tenant()?->name ?? 'Tenant'])];
    }
    public function update(Request $request, TenantContext $context): array
    {
        $data = $request->validate([
            'display_name' => ['sometimes', 'string', 'max:160'], 'legal_name' => ['nullable', 'string', 'max:200'],
            'logo_url' => ['nullable', 'url', 'max:2048'], 'favicon_url' => ['nullable', 'url', 'max:2048'],
            'primary_color' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'secondary_color' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'support_email' => ['nullable', 'email:rfc', 'max:254'], 'support_url' => ['nullable', 'url', 'max:2048'],
            'locale' => ['sometimes', 'string', 'max:10'], 'custom_css' => ['nullable', 'string', 'max:10000', 'not_regex:/[<>]/'], 'settings' => ['nullable', 'array'],
        ]);
        $profile = TenantBrandingProfile::query()->firstOrCreate(['tenant_id' => $context->requireId()], ['display_name' => $context->tenant()?->name ?? 'Tenant']);
        $profile->fill($data)->save();
        return ['data' => $profile->fresh()];
    }
}
