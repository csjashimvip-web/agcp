import { apiFetch } from "./auth-api";

export type AnalyticsSnapshot = {
  id: string;
  currency: string;
  period_start: string;
  period_end: string;
  orders_count: number;
  completed_orders_count: number;
  gross_revenue_minor: number;
  net_revenue_minor: number;
  refunded_minor: number;
  discounts_minor: number;
  surcharges_minor: number;
  unique_customers: number;
  average_order_value_minor: number;
  risk_review_count: number;
  supplier_success_rate: number;
  metrics?: Record<string, number>;
  calculated_at: string;
};
export type SalesForecast = {
  id: string;
  currency: string;
  horizon_days: number;
  method: string;
  basis_start: string;
  basis_end: string;
  predicted_revenue_minor: number;
  confidence: number;
  trend_percent: number;
  points: Array<{date:string; predicted_revenue_minor:number}>;
  generated_at: string;
};
export type CustomerSegment = {
  id: string;
  segment_code: string;
  score: number;
  recency_days: number | null;
  frequency_orders: number;
  monetary_minor: number;
  average_order_minor: number;
  calculated_at: string;
  user?: {id:string; name:string; email:string};
};
export type SupplierRecommendation = {
  id: string;
  strategy: string;
  score: number;
  confidence: number;
  candidates: Array<{supplier_account_id:string; supplier_name:string; cost_minor:number; currency:string; health_score:number; success_rate:number; average_latency_ms:number; score:number}>;
  reason: string | null;
  generated_at: string;
  supplier?: {id:string; name:string; code:string; health_score:number; success_rate:number; average_latency_ms:number};
  variant?: {id:string; name:string; sku:string};
};
export type AiInsight = {
  id:string;
  type:string;
  severity:string;
  title:string;
  summary:string;
  recommendations:string[];
  evidence:Record<string,unknown>;
  provider_key:string;
  model_version:string;
  status:string;
  generated_at:string;
};
export type ModelRun = {id:string; run_type:string; status:string; records_processed:number; metrics?:Record<string,number|string>; started_at:string|null; completed_at:string|null; error_message:string|null};
export type AnalyticsDashboard = {
  snapshot: AnalyticsSnapshot | null;
  forecast: SalesForecast | null;
  segment_summary: Record<string,number>;
  segments: CustomerSegment[];
  supplier_recommendations: SupplierRecommendation[];
  insights: AiInsight[];
  runs: ModelRun[];
};

export const analyticsApi = {
  dashboard: () => apiFetch<{data:AnalyticsDashboard}>("/api/v1/admin/analytics"),
  refresh: (input: {currency?:string; async?:boolean; window_days?:number; horizon_days?:number} = {}) =>
    apiFetch<{data:{run_id?:string;status?:string;queued?:boolean;segments?:number;supplier_recommendations?:number;insights?:number}}>("/api/v1/admin/analytics/refresh", {method:"POST", body:JSON.stringify(input)}),
  updateInsight: (id:string,status:"active"|"dismissed") => apiFetch<{data:AiInsight}>(`/api/v1/admin/analytics/insights/${id}`, {method:"PATCH",body:JSON.stringify({status})}),
};
