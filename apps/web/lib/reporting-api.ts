import {apiFetch} from "./auth-api";
export type InvoiceLine={id:string;sequence:number;description:string;sku:string|null;item_type:string|null;quantity:number;unit_price_minor:number;net_minor:number;tax_rate_basis_points:number;tax_minor:number;gross_minor:number};
export type Invoice={id:string;number:string;order_id:string;order_number?:string;status:string;currency:string;subtotal_minor:number;discount_minor:number;surcharge_minor:number;tax_minor:number;total_minor:number;amount_paid_minor:number;amount_due_minor:number;content_hash:string;issued_at:string|null;lines?:InvoiceLine[]};
export type ReportingDashboard={period:{start:string;end:string};metrics:Record<string,number>;invoices:Array<Invoice&{user?:{name:string;email:string}}> ;tax_rates:any[];exports:any[];schedules:any[];runs:any[]};
export const reportingApi={
  invoices:()=>apiFetch<{data:{data:Invoice[]}}>("/api/v1/invoices"),
  invoice:(id:string)=>apiFetch<{data:Invoice}>(`/api/v1/invoices/${id}`),
  taxProfile:()=>apiFetch<{data:any}>("/api/v1/tax-profile"),
  updateTaxProfile:(input:any)=>apiFetch<{data:any}>("/api/v1/tax-profile",{method:"PUT",body:JSON.stringify(input)}),
  dashboard:()=>apiFetch<{data:ReportingDashboard}>("/api/v1/admin/reports"),
  generateInvoice:(orderId:string)=>apiFetch<{data:Invoice}>(`/api/v1/admin/reports/invoices/orders/${orderId}`,{method:"POST"}),
  createExport:(type:string)=>apiFetch<{data:any}>("/api/v1/admin/reports/exports",{method:"POST",body:JSON.stringify({type})}),
  createTaxRate:(input:any)=>apiFetch<{data:any}>("/api/v1/admin/reports/tax-rates",{method:"POST",body:JSON.stringify(input)}),
  createSchedule:(input:any)=>apiFetch<{data:any}>("/api/v1/admin/reports/schedules",{method:"POST",body:JSON.stringify(input)}),
  runSchedule:(id:string)=>apiFetch<{data:any}>(`/api/v1/admin/reports/schedules/${id}/run`,{method:"POST"}),
};
