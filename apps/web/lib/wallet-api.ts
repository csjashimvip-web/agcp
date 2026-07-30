import { apiFetch } from "./auth-api";

export type Wallet = {
  id: string; type: string; currency: string; status: string;
  balance_minor: number; held_minor: number; available_minor: number;
  balance: string; available: string; created_at: string | null;
};
export type Deposit = {
  user?: {id:string; name:string; email:string};
  id: string; wallet_id: string; amount_minor: number; amount: string; currency: string;
  method: string; status: string; external_reference: string | null;
  customer_note: string | null; admin_note: string | null; submitted_at: string | null;
};
export type LedgerTransaction = {
  id: string; event_type: string; description: string; status: string; posted_at: string | null;
  entries?: Array<{id:string; account_name:string|null; direction:string; amount_minor:number; currency:string; balance_after_minor:number}>;
};
type Collection<T> = { data: T[]; links?: unknown; meta?: unknown };

export const walletApi = {
  wallets: () => apiFetch<Collection<Wallet>>("/api/v1/wallets"),
  transactions: (walletId: string) => apiFetch<Collection<LedgerTransaction>>(`/api/v1/wallets/${walletId}/transactions`),
  deposits: () => apiFetch<Collection<Deposit>>("/api/v1/deposits"),
  createDeposit: (input: {amount:string; currency:string; method:string; external_reference?:string; customer_note?:string}) =>
    apiFetch<{data:Deposit}>("/api/v1/deposits", {method:"POST", headers:{"Idempotency-Key":crypto.randomUUID()}, body:JSON.stringify(input)}),
  cancelDeposit: (id:string) => apiFetch<{data:Deposit}>(`/api/v1/deposits/${id}/cancel`, {method:"POST"}),
  adminDeposits: (status = "pending") => apiFetch<Collection<Deposit>>(`/api/v1/admin/deposits?status=${encodeURIComponent(status)}`),
  approveDeposit: (id:string, note?:string) => apiFetch<{data:Deposit}>(`/api/v1/admin/deposits/${id}/approve`, {method:"POST", headers:{"Idempotency-Key":crypto.randomUUID()}, body:JSON.stringify({note})}),
  rejectDeposit: (id:string, note:string) => apiFetch<{data:Deposit}>(`/api/v1/admin/deposits/${id}/reject`, {method:"POST", body:JSON.stringify({note})}),
};
