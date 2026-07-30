import { apiFetch } from "./auth-api";

export type SupplierServiceMapping = {
  id: string;
  catalog_variant_id: string;
  supplier_service_code: string;
  cost_minor: number;
  currency: string;
  estimated_seconds: number;
  priority: number;
  enabled: boolean;
  variant?: { id: string; name: string; sku: string; item_name: string | null } | null;
};

export type SupplierAccount = {
  id: string;
  name: string;
  code: string;
  provider: string;
  status: string;
  priority: number;
  timeout_seconds: number;
  max_retries: number;
  country_codes: string[];
  health_status: string;
  health_score: number;
  success_rate: number;
  average_latency_ms: number;
  total_requests: number;
  successful_requests: number;
  failed_requests: number;
  consecutive_failures: number;
  last_checked_at: string | null;
  disabled_until: string | null;
  metadata: Record<string, unknown> | null;
  services?: SupplierServiceMapping[];
};

export type SupplierOrder = {
  id: string;
  order_id: string;
  order_item_id: string;
  client_reference: string;
  supplier_reference: string | null;
  status: string;
  attempts: number;
  max_attempts: number;
  request_payload: Record<string, unknown> | null;
  result_payload: Record<string, unknown> | null;
  error_code: string | null;
  error_message: string | null;
  queued_at: string | null;
  completed_at: string | null;
  refunded_at: string | null;
  supplier?: { id: string; name: string; code: string; provider: string } | null;
  order?: { id: string; number: string; status: string; payment_status: string; fulfillment_status: string };
  item?: { id: string; name: string; sku: string; status: string; total_minor: number };
};


export type SupplierRoutingProfile = {
  id: string; name: string; slug: string; strategy: "balanced" | "cheapest" | "fastest" | "highest_success" | "priority";
  weights: Record<string, number> | null; is_default: boolean; status: string;
};

type Collection<T> = { data: T[]; links?: unknown; meta?: unknown };

export const supplierApi = {
  routingProfile: () => apiFetch<{ data: SupplierRoutingProfile }>("/api/v1/admin/supplier-routing-profile"),
  updateRoutingProfile: (strategy: SupplierRoutingProfile["strategy"]) => apiFetch<{ data: SupplierRoutingProfile }>("/api/v1/admin/supplier-routing-profile", { method: "PUT", body: JSON.stringify({ strategy }) }),
  providers: () => apiFetch<{ data: string[] }>("/api/v1/admin/suppliers/providers"),
  accounts: () => apiFetch<Collection<SupplierAccount>>("/api/v1/admin/suppliers"),
  createAccount: (input: {
    name: string; code: string; provider: string; priority?: number; timeout_seconds?: number;
    max_retries?: number; metadata?: Record<string, unknown>;
  }) => apiFetch<{ data: SupplierAccount }>("/api/v1/admin/suppliers", { method: "POST", body: JSON.stringify(input) }),
  updateAccount: (id: string, input: Partial<SupplierAccount>) =>
    apiFetch<{ data: SupplierAccount }>(`/api/v1/admin/suppliers/${id}`, { method: "PATCH", body: JSON.stringify(input) }),
  checkHealth: (id: string) => apiFetch<{ data: SupplierAccount }>(`/api/v1/admin/suppliers/${id}/health-check`, { method: "POST" }),
  mapService: (supplierId: string, input: {
    catalog_variant_id: string; supplier_service_code: string; cost_minor: number; currency: string;
    estimated_seconds?: number; priority?: number; enabled?: boolean;
  }) => apiFetch<{ data: SupplierAccount }>(`/api/v1/admin/suppliers/${supplierId}/services`, { method: "POST", body: JSON.stringify(input) }),
  orders: (status = "") => apiFetch<Collection<SupplierOrder>>(`/api/v1/admin/supplier-orders${status ? `?status=${status}` : ""}`),
  retry: (id: string) => apiFetch<{ data: SupplierOrder }>(`/api/v1/admin/supplier-orders/${id}/retry`, { method: "POST" }),
};
