import { apiFetch } from "./auth-api";
export type Plan={id:string;name:string;slug:string;status:string;currency:string;price_monthly_minor:number;price_yearly_minor:number;trial_days:number;features:Record<string,unknown>;limits:Record<string,number>;is_public:boolean};
export type Domain={id:string;domain:string;primary:boolean;verified:boolean;status:string;verification_token?:string};
export type TenantRecord={id:string;name:string;slug:string;status:string;default_currency:string;timezone:string;branding?:{display_name:string;primary_color:string;secondary_color:string;support_email:string|null}|null;domains:Domain[];subscription?:{id:string;status:string;plan:string;plan_slug:string}|null};
export type PluginRecord={id:string;slug:string;name:string;version:string;category:string;provider_type:string|null;description:string|null;is_core:boolean;capabilities:string[];config_schema:{required?:string[];properties?:Record<string,{type?:string;secret?:boolean}>};requested_permissions:string[];installation?:{id:string;status:string;enabled:boolean;installed_version:string;installed_at:string|null;configured_keys:string[];last_error:string|null}|null};
export const saasApi={
 dashboard:()=>apiFetch<{data:{platform_admin:boolean;current_tenant:TenantRecord;entitlements:{plan:{name:string;slug:string}|null;features:Record<string,unknown>;limits:Record<string,number>};usage:Array<{metric:string;quantity:number;limit:number|null;remaining:number|null}>;plans:Plan[];tenants:TenantRecord[]}}>("/api/v1/admin/saas"),
 updateBranding:(input:Record<string,unknown>)=>apiFetch("/api/v1/admin/tenant-profile",{method:"PATCH",body:JSON.stringify(input)}),
 domains:()=>apiFetch<{data:Array<Domain&{verification_token:string|null}>}>("/api/v1/admin/tenant-domains"),
 addDomain:(domain:string)=>apiFetch<{data:Domain&{verification_token:string}}>("/api/v1/admin/tenant-domains",{method:"POST",body:JSON.stringify({domain})}),
 verifyDomain:(id:string,token:string)=>apiFetch(`/api/v1/admin/tenant-domains/${id}/verify`,{method:"POST",body:JSON.stringify({token})}),
 primaryDomain:(id:string)=>apiFetch(`/api/v1/admin/tenant-domains/${id}/primary`,{method:"POST"}),
 plugins:()=>apiFetch<{data:PluginRecord[]}>("/api/v1/admin/plugins"),
 installPlugin:(id:string,configuration:Record<string,unknown>)=>apiFetch(`/api/v1/admin/plugins/${id}/install`,{method:"POST",body:JSON.stringify({configuration})}),
 configurePlugin:(id:string,configuration:Record<string,unknown>)=>apiFetch(`/api/v1/admin/plugin-installations/${id}`,{method:"PATCH",body:JSON.stringify({configuration})}),
 enablePlugin:(id:string)=>apiFetch(`/api/v1/admin/plugin-installations/${id}/enable`,{method:"POST"}),
 disablePlugin:(id:string)=>apiFetch(`/api/v1/admin/plugin-installations/${id}/disable`,{method:"POST"}),
 createTenant:(input:Record<string,unknown>)=>apiFetch("/api/v1/admin/saas/tenants",{method:"POST",body:JSON.stringify(input)}),
};
