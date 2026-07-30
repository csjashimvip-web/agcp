import { apiFetch } from "./auth-api";

export type CatalogVariant = {
  id: string; name: string; sku: string; attributes: Record<string,string>|null; status: string; is_default: boolean;
  price: {amount_minor:number; amount:string; currency:string; compare_at_minor:number|null}|null;
  available_quantity: number;
};
export type CatalogItem = {
  id:string; name:string; slug:string; sku:string|null; type:"physical"|"digital"|"service"; summary:string|null; description:string|null;
  status:string; fulfillment_mode:string; inventory_tracking:boolean; allow_backorder:boolean;
  service_schema:{fields?:Array<{name:string;label?:string;type?:string;required?:boolean}>}|null;
  category:{id:string;name:string;slug:string}|null; variants:CatalogVariant[]; published_at:string|null;
};
export type CartLine={
  id:string; quantity:number; unit_price_minor:number; unit_price:string; total_minor:number; configuration:Record<string,string>|null;
  variant:{id:string;name:string;sku:string;item:{id:string;name:string;slug:string;type:string}};
};
export type Cart={id:string;currency:string;status:string;subtotal_minor:number;subtotal:string;items:CartLine[];expires_at:string|null};
export type Order={
  id:string;number:string;status:string;payment_status:string;fulfillment_status:string;currency:string;subtotal_minor:number;discount_minor:number;total_minor:number;total:string;
  placed_at:string|null;canceled_at:string|null;user?:{id:string;name:string;email:string};
  items?:Array<{id:string;item_name:string;variant_name:string|null;sku:string;item_type:string;quantity:number;unit_price_minor:number;total_minor:number;status:string;configuration:Record<string,string>|null}>;
  history?:Array<{from:string|null;to:string;note:string|null;created_at:string|null}>;
};
type Collection<T>={data:T[];links?:unknown;meta?:unknown};

export const commerceApi={
  catalog:(query="")=>apiFetch<Collection<CatalogItem>>(`/api/v1/catalog${query?`?${query}`:""}`),
  item:(slug:string)=>apiFetch<{data:CatalogItem}>(`/api/v1/catalog/${slug}`),
  cart:(currency="USD")=>apiFetch<{data:Cart}>(`/api/v1/cart?currency=${currency}`),
  addToCart:(input:{variant_id:string;quantity:number;configuration?:Record<string,string>;currency?:string})=>apiFetch<{data:Cart}>("/api/v1/cart/items",{method:"POST",body:JSON.stringify(input)}),
  updateCartItem:(id:string,quantity:number)=>apiFetch<{data:Cart}>(`/api/v1/cart/items/${id}`,{method:"PATCH",body:JSON.stringify({quantity})}),
  removeCartItem:(id:string)=>apiFetch<{data:Cart}>(`/api/v1/cart/items/${id}`,{method:"DELETE"}),
  checkout:(cart_id:string,wallet_id:string)=>apiFetch<{data:Order}>("/api/v1/checkout",{method:"POST",headers:{"Idempotency-Key":crypto.randomUUID()},body:JSON.stringify({cart_id,wallet_id})}),
  orders:()=>apiFetch<Collection<Order>>("/api/v1/orders"),
  cancelOrder:(id:string,note?:string)=>apiFetch<{data:Order}>(`/api/v1/orders/${id}/cancel`,{method:"POST",body:JSON.stringify({note})}),
  adminItems:()=>apiFetch<Collection<CatalogItem>>("/api/v1/admin/commerce/items"),
  createItem:(input:{name:string;sku:string;type:string;summary?:string;status?:string;inventory_tracking?:boolean;allow_backorder?:boolean;service_schema?:unknown})=>apiFetch<{data:CatalogItem}>("/api/v1/admin/commerce/items",{method:"POST",body:JSON.stringify(input)}),
  upsertPrice:(input:{variant_id:string;currency:string;amount_minor:number;compare_at_minor?:number|null;min_quantity?:number})=>apiFetch("/api/v1/admin/commerce/pricing",{method:"POST",body:JSON.stringify(input)}),
  upsertInventory:(input:{variant_id:string;on_hand:number;safety_stock?:number;location_code?:string;location_name?:string})=>apiFetch("/api/v1/admin/commerce/inventory",{method:"POST",body:JSON.stringify(input)}),
  adminOrders:(status="")=>apiFetch<Collection<Order>>(`/api/v1/admin/commerce/orders${status?`?status=${status}`:""}`),
  transitionOrder:(id:string,status:string,note?:string)=>apiFetch<{data:Order}>(`/api/v1/admin/commerce/orders/${id}/transition`,{method:"POST",body:JSON.stringify({status,note})}),
};
