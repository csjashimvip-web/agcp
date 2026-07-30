"use client";

import Link from "next/link";
import { FormEvent, useCallback, useEffect, useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { PortalHeader } from "@/components/PortalHeader";
import { ApiUser, authApi, errorMessage } from "@/lib/auth-api";
import { CatalogItem, commerceApi } from "@/lib/commerce-api";
import { SupplierAccount, SupplierOrder, SupplierRoutingProfile, supplierApi } from "@/lib/supplier-api";

export default function SupplierAdminPage() {
  const router = useRouter();
  const [user, setUser] = useState<ApiUser | null>(null);
  const [suppliers, setSuppliers] = useState<SupplierAccount[]>([]);
  const [orders, setOrders] = useState<SupplierOrder[]>([]);
  const [items, setItems] = useState<CatalogItem[]>([]);
  const [providers, setProviders] = useState<string[]>([]);
  const [routingStrategy, setRoutingStrategy] = useState<SupplierRoutingProfile["strategy"]>("balanced");
  const [message, setMessage] = useState("");
  const [busy, setBusy] = useState(false);

  const [name, setName] = useState("");
  const [code, setCode] = useState("");
  const [provider, setProvider] = useState("sandbox");
  const [priority, setPriority] = useState("100");

  const [mappingSupplier, setMappingSupplier] = useState("");
  const [mappingVariant, setMappingVariant] = useState("");
  const [serviceCode, setServiceCode] = useState("");
  const [cost, setCost] = useState("1.00");
  const [eta, setEta] = useState("60");

  const variants = useMemo(() => items.flatMap(item => item.variants.map(variant => ({
    id: variant.id, label: `${item.name} · ${variant.name} · ${variant.sku}`, fulfillment: item.fulfillment_mode,
  }))), [items]);

  const load = useCallback(async () => {
    const me = await authApi.me();
    if (!me.data.permissions.includes("supplier.admin.access")) throw new Error("Supplier administration permission is required.");
    const [accounts, supplierOrders, catalog, providerList, profile] = await Promise.all([
      supplierApi.accounts(), supplierApi.orders(), commerceApi.adminItems(), supplierApi.providers(), supplierApi.routingProfile(),
    ]);
    setUser(me.data);
    setSuppliers(accounts.data);
    setOrders(supplierOrders.data);
    setItems(catalog.data);
    setProviders(providerList.data);
    setRoutingStrategy(profile.data.strategy);
    if (!mappingSupplier && accounts.data[0]) setMappingSupplier(accounts.data[0].id);
    if (!mappingVariant) {
      const automated = catalog.data.flatMap(item => item.variants.map(variant => ({ item, variant }))).find(row => row.item.fulfillment_mode === "supplier_api");
      if (automated) setMappingVariant(automated.variant.id);
    }
  }, [mappingSupplier, mappingVariant]);

  useEffect(() => {
    load().catch(error => {
      if ((error as { status?: number }).status === 401) router.replace("/login");
      else setMessage(errorMessage(error));
    });
  }, [load, router]);

  async function saveRouting(event: FormEvent) {
    event.preventDefault(); setBusy(true); setMessage("");
    try { await supplierApi.updateRoutingProfile(routingStrategy); setMessage("Default routing strategy updated."); await load(); }
    catch (error) { setMessage(errorMessage(error)); } finally { setBusy(false); }
  }

  async function createSupplier(event: FormEvent) {
    event.preventDefault(); setBusy(true); setMessage("");
    try {
      await supplierApi.createAccount({ name, code: code.toLowerCase(), provider, priority: Number(priority) });
      setName(""); setCode(""); setMessage("Supplier account created."); await load();
    } catch (error) { setMessage(errorMessage(error)); } finally { setBusy(false); }
  }

  async function mapService(event: FormEvent) {
    event.preventDefault(); setBusy(true); setMessage("");
    try {
      await supplierApi.mapService(mappingSupplier, {
        catalog_variant_id: mappingVariant, supplier_service_code: serviceCode,
        cost_minor: Math.round(Number(cost) * 100), currency: "USD", estimated_seconds: Number(eta),
      });
      setServiceCode(""); setMessage("Supplier service mapping saved."); await load();
    } catch (error) { setMessage(errorMessage(error)); } finally { setBusy(false); }
  }

  async function health(supplier: SupplierAccount) {
    setBusy(true); setMessage("");
    try { await supplierApi.checkHealth(supplier.id); setMessage(`${supplier.name} health check completed.`); await load(); }
    catch (error) { setMessage(errorMessage(error)); } finally { setBusy(false); }
  }

  async function retry(order: SupplierOrder) {
    setBusy(true); setMessage("");
    try { await supplierApi.retry(order.id); setMessage(`Retry queued for ${order.client_reference}.`); await load(); }
    catch (error) { setMessage(errorMessage(error)); } finally { setBusy(false); }
  }

  if (!user && !message) return <main className="centerPage"><div className="loader"/><p>Loading supplier engine…</p></main>;

  return <main className="portal">
    <PortalHeader name={user?.name} admin />
    <section className="shell portalTitle">
      <p className="eyebrow">Phase 5 · Smart supplier engine</p>
      <h1>Routing, health and failover</h1>
      <p>Connect provider adapters, map catalog services, compare auditable scores and retry failed fulfillment without touching commerce code.</p>
      <div className="actions"><Link className="secondary" href="/admin/commerce">Commerce admin</Link><Link className="secondary" href="/orders">Customer orders</Link></div>
    </section>
    {message ? <div className="shell notice success">{message}</div> : null}
    <section className="shell adminLayout">
      <article className="adminCard wide">
        <div className="cardHead"><div><small>Decision policy</small><h2>Default routing strategy</h2></div><span className="pill good">Auditable</span></div>
        <form className="inlineForm" onSubmit={saveRouting}>
          <select value={routingStrategy} onChange={event => setRoutingStrategy(event.target.value as SupplierRoutingProfile["strategy"])}>
            <option value="balanced">Balanced</option><option value="cheapest">Cheapest</option><option value="fastest">Fastest</option><option value="highest_success">Highest success</option><option value="priority">Priority</option>
          </select>
          <button className="primary" disabled={busy}>Save strategy</button>
        </form>
      </article>
      <article className="adminCard">
        <div className="cardHead"><div><small>Provider account</small><h2>Add supplier</h2></div></div>
        <form className="formStack compact" onSubmit={createSupplier}>
          <label>Name<input value={name} onChange={event => setName(event.target.value)} required /></label>
          <label>Code<input value={code} onChange={event => setCode(event.target.value.replace(/\s+/g, "-"))} required /></label>
          <label>Provider<select value={provider} onChange={event => setProvider(event.target.value)}>{providers.map(value => <option key={value}>{value}</option>)}</select></label>
          <label>Priority<input type="number" min="0" value={priority} onChange={event => setPriority(event.target.value)} required /></label>
          <button className="primary" disabled={busy}>Create supplier</button>
        </form>
      </article>
      <article className="adminCard">
        <div className="cardHead"><div><small>Catalog mapping</small><h2>Map supplier service</h2></div></div>
        <form className="formStack compact" onSubmit={mapService}>
          <label>Supplier<select value={mappingSupplier} onChange={event => setMappingSupplier(event.target.value)} required><option value="">Select supplier</option>{suppliers.map(value => <option value={value.id} key={value.id}>{value.name}</option>)}</select></label>
          <label>Catalog variant<select value={mappingVariant} onChange={event => setMappingVariant(event.target.value)} required><option value="">Select variant</option>{variants.map(value => <option value={value.id} key={value.id}>{value.label} · {value.fulfillment}</option>)}</select></label>
          <label>Supplier service code<input value={serviceCode} onChange={event => setServiceCode(event.target.value)} required /></label>
          <label>Cost (USD)<input type="number" min="0" step="0.01" value={cost} onChange={event => setCost(event.target.value)} required /></label>
          <label>Estimated seconds<input type="number" min="1" value={eta} onChange={event => setEta(event.target.value)} required /></label>
          <button className="primary" disabled={busy}>Save mapping</button>
        </form>
      </article>
      <article className="adminCard wide">
        <div className="cardHead"><div><small>Live provider health</small><h2>{suppliers.length} suppliers</h2></div></div>
        <div className="tableWrap"><table><thead><tr><th>Supplier</th><th>Provider</th><th>Health</th><th>Success</th><th>Latency</th><th>Mappings</th><th>Action</th></tr></thead><tbody>
          {suppliers.map(value => <tr key={value.id}><td><strong>{value.name}</strong><small>{value.code} · priority {value.priority}</small></td><td>{value.provider}</td><td><span className={value.health_status === "healthy" ? "pill good" : "pill warn"}>{value.health_status} · {value.health_score.toFixed(0)}</span></td><td>{value.success_rate.toFixed(2)}%</td><td>{value.average_latency_ms} ms</td><td>{value.services?.length ?? 0}</td><td><button disabled={busy} onClick={() => health(value)}>Check now</button></td></tr>)}
        </tbody></table></div>
      </article>
      <article className="adminCard wide">
        <div className="cardHead"><div><small>Automated operations</small><h2>Supplier orders</h2></div></div>
        <div className="tableWrap"><table><thead><tr><th>Reference</th><th>Order</th><th>Item</th><th>Supplier</th><th>Status</th><th>Attempts</th><th>Action</th></tr></thead><tbody>
          {orders.length ? orders.map(order => <tr key={order.id}><td><strong>{order.client_reference}</strong><small>{order.supplier_reference ?? "Not submitted"}</small></td><td>{order.order?.number ?? order.order_id}</td><td>{order.item?.name ?? order.order_item_id}</td><td>{order.supplier?.name ?? "Routing pending"}</td><td><span className={["completed"].includes(order.status) ? "pill good" : ["failed", "refunded"].includes(order.status) ? "pill warn" : "pill"}>{order.status}</span>{order.error_message ? <small>{order.error_message}</small> : null}</td><td>{order.attempts}/{order.max_attempts}</td><td>{["failed", "retrying"].includes(order.status) ? <button disabled={busy} onClick={() => retry(order)}>Retry</button> : "—"}</td></tr>) : <tr><td colSpan={7}>No supplier orders yet. Place the seeded IMEI service order to test automation.</td></tr>}
        </tbody></table></div>
      </article>
    </section>
  </main>;
}
