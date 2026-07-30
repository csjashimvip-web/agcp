import { apiFetch } from "./auth-api";

export type PaymentProvider = {
  id:string; provider:string; code:string; name:string; mode:string; status:string; priority:number;
  currencies:string[]; minimum_amount_minor:number; maximum_amount_minor:number;
  fee_basis_points:number; fee_fixed_minor:number; webhook_path?:string; metadata?:Record<string,unknown>;
};
export type PaymentIntent = {
  id:string; reference:string; provider_payment_id:string|null; wallet_id:string;
  provider:{id:string;code:string;name:string;provider:string;mode:string}|null;
  amount_minor:number; fee_minor:number; total_minor:number; currency:string; status:string;
  checkout_url:string|null; expires_at:string|null; completed_at:string|null;
  failure_code:string|null; failure_message:string|null; deposit_id:string|null;
  ledger_transaction_id:string|null; refunded_minor:number; created_at:string|null;
};
export type PaymentWebhook = {id:string;provider:string|null;external_event_id:string;event_type:string;status:string;payment_intent_id:string|null;error_message:string|null;received_at:string|null};
export type PaymentRefund = {id:string;reference:string;payment_intent_id:string;payment_reference:string|null;provider_refund_id:string|null;amount_minor:number;currency:string;status:string;reason:string;requested_by:string|null;completed_at:string|null};
export type ReconciliationRun = {id:string;status:string;checked_count:number;mismatch_count:number;resolved_count:number;summary?:Record<string,unknown>;started_at:string;completed_at:string|null;items?:Array<{id:string;type:string;severity:string;status:string;description:string;expected_amount_minor:number|null;actual_amount_minor:number|null;currency:string|null}>};
export type PaymentAdminDashboard = {providers:PaymentProvider[];intents:PaymentIntent[];webhooks:PaymentWebhook[];refunds:PaymentRefund[];reconciliation_runs:ReconciliationRun[]};
type Collection<T>={data:T[];meta?:unknown;links?:unknown};

export const paymentsApi={
  providers:()=>apiFetch<Collection<PaymentProvider>>("/api/v1/payments/providers"),
  intents:()=>apiFetch<Collection<PaymentIntent>>("/api/v1/payments"),
  create:(input:{wallet_id:string;provider_code:string;amount:string;currency:string})=>apiFetch<{data:PaymentIntent}>("/api/v1/payments",{method:"POST",headers:{"Idempotency-Key":crypto.randomUUID()},body:JSON.stringify(input)}),
  cancel:(id:string)=>apiFetch<{data:PaymentIntent}>(`/api/v1/payments/${id}/cancel`,{method:"POST"}),
  simulate:(id:string)=>apiFetch<{data:PaymentIntent}>(`/api/v1/payments/${id}/sandbox-complete`,{method:"POST"}),
  admin:()=>apiFetch<{data:PaymentAdminDashboard}>("/api/v1/admin/payments"),
  reconcile:(provider_account_id?:string)=>apiFetch<{data:ReconciliationRun}>("/api/v1/admin/payments/reconcile",{method:"POST",body:JSON.stringify({provider_account_id:provider_account_id||null})}),
  refund:(id:string,amount:string,reason:string)=>apiFetch<{data:PaymentRefund}>(`/api/v1/admin/payments/intents/${id}/refund`,{method:"POST",headers:{"Idempotency-Key":crypto.randomUUID()},body:JSON.stringify({amount,reason})}),
  createProvider:(input:{provider:string;code:string;name:string;mode:string;currencies:string[]})=>apiFetch<{data:PaymentProvider;webhook_secret:string}>("/api/v1/admin/payments/providers",{method:"POST",body:JSON.stringify(input)}),
};
