"use client";

import { FormEvent, useEffect, useState } from "react";
import { useAdminContext } from "@/components/admin-shell";
import { apiFetch, ApiEnvelope } from "@/lib/agcp-api";

type Inbox = {
  id: number;
  supplier_name: string;
  external_service_id: string;
  external_name: string;
  cost_minor: number;
  currency: string;
  status: string;
};

type Product = {
  id: number;
  sku: string;
  name: string;
};

type RouteRow = {
  id: number;
  product_name: string;
  sku: string;
  supplier_name: string;
  external_name: string;
  priority: number;
  weight: number;
  enabled: boolean | number;
};

export default function RoutingPage() {
  const { tenantId } = useAdminContext();
  const [inbox, setInbox] = useState<Inbox[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [routes, setRoutes] = useState<RouteRow[]>([]);
  const [message, setMessage] = useState("");

  async function load() {
    if (!tenantId) return;

    const [inboxResult, productsResult, routesResult] = await Promise.all([
      apiFetch<ApiEnvelope<Inbox[]>>(
        "/api/v1/admin/supplier-inbox",
        {},
        tenantId,
      ),
      apiFetch<ApiEnvelope<Product[]>>(
        "/api/v1/admin/products?limit=100",
        {},
        tenantId,
      ),
      apiFetch<ApiEnvelope<RouteRow[]>>(
        "/api/v1/admin/supplier-routing",
        {},
        tenantId,
      ),
    ]);

    setInbox(inboxResult.data);
    setProducts(productsResult.data);
    setRoutes(routesResult.data);
  }

  useEffect(() => {
    load().catch((error) =>
      setMessage(error instanceof Error ? error.message : "Unable to load."),
    );
  }, [tenantId]);

  async function mapService(
    event: FormEvent<HTMLFormElement>,
    inboxId: number,
  ) {
    event.preventDefault();
    if (!tenantId) return;

    const form = new FormData(event.currentTarget);

    await apiFetch(
      `/api/v1/admin/supplier-inbox/${inboxId}/map`,
      {
        method: "POST",
        body: JSON.stringify({
          product_id: Number(form.get("product_id")),
          priority: Number(form.get("priority") || 100),
          weight: Number(form.get("weight") || 100),
        }),
      },
      tenantId,
    );

    setMessage("Supplier service mapped to AGCP product.");
    await load();
  }

  async function updateRoute(
    routeId: number,
    payload: Record<string, unknown>,
  ) {
    if (!tenantId) return;

    await apiFetch(
      `/api/v1/admin/supplier-routing/${routeId}`,
      {
        method: "PATCH",
        body: JSON.stringify(payload),
      },
      tenantId,
    );

    setMessage("Routing rule updated.");
    await load();
  }

  const unmapped = inbox.filter((row) => row.status === "unmapped");

  return (
    <div>
      <div className="page-heading">
        <div>
          <p className="eyebrow">Supplier orchestration</p>
          <h2>Routing Editor</h2>
          <p>
            Review synchronized supplier services, map them explicitly to AGCP
            products and control failover priority.
          </p>
        </div>
      </div>

      {message && <div className="info-banner">{message}</div>}

      <div className="write-card">
        <h3>Unmapped supplier services</h3>

        <div className="stack-list">
          {unmapped.map((service) => (
            <form
              className="mapping-row"
              key={service.id}
              onSubmit={(event) => mapService(event, service.id)}
            >
              <div>
                <strong>{service.external_name}</strong>
                <div className="subtle">
                  {service.supplier_name} Â· {service.external_service_id} Â·{" "}
                  {service.cost_minor} {service.currency}
                </div>
              </div>

              <select name="product_id" required defaultValue="">
                <option value="" disabled>
                  Map to productâ€¦
                </option>
                {products.map((product) => (
                  <option key={product.id} value={product.id}>
                    {product.sku} â€” {product.name}
                  </option>
                ))}
              </select>

              <input
                name="priority"
                type="number"
                min="1"
                defaultValue="100"
                title="Priority"
              />
              <input
                name="weight"
                type="number"
                min="1"
                defaultValue="100"
                title="Weight"
              />

              <button className="small-button" type="submit">
                Map
              </button>
            </form>
          ))}

          {unmapped.length === 0 && (
            <div className="empty-state">
              No unmapped supplier services. Run supplier sync to populate the
              inbox.
            </div>
          )}
        </div>
      </div>

      <div className="table-card">
        <div className="table-status">{routes.length} routing rules</div>
        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Product</th>
                <th>Supplier</th>
                <th>External service</th>
                <th>Priority</th>
                <th>Weight</th>
                <th>Enabled</th>
              </tr>
            </thead>
            <tbody>
              {routes.map((route) => (
                <tr key={route.id}>
                  <td>
                    <strong>{route.product_name}</strong>
                    <div className="subtle">{route.sku}</div>
                  </td>
                  <td>{route.supplier_name}</td>
                  <td>{route.external_name}</td>
                  <td>
                    <input
                      className="table-input"
                      type="number"
                      min="1"
                      defaultValue={route.priority}
                      onBlur={(event) =>
                        updateRoute(route.id, {
                          priority: Number(event.target.value),
                        })
                      }
                    />
                  </td>
                  <td>
                    <input
                      className="table-input"
                      type="number"
                      min="1"
                      defaultValue={route.weight}
                      onBlur={(event) =>
                        updateRoute(route.id, {
                          weight: Number(event.target.value),
                        })
                      }
                    />
                  </td>
                  <td>
                    <input
                      type="checkbox"
                      checked={Boolean(route.enabled)}
                      onChange={(event) =>
                        updateRoute(route.id, {
                          enabled: event.target.checked,
                        })
                      }
                    />
                  </td>
                </tr>
              ))}
              {routes.length === 0 && (
                <tr>
                  <td colSpan={6} className="empty-cell">
                    No routing rules configured.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}