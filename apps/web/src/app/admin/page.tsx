"use client";

import { useEffect, useState } from "react";
import { useAdminContext } from "@/components/admin-shell";
import { apiFetch, ApiEnvelope } from "@/lib/agcp-api";

type Overview = {
  tenant_id: number;
  users: number;
  products: number;
  orders: number;
  suppliers: number;
  wallet_liability_minor: number;
  pending_supplier_orders: number;
  failed_supplier_orders: number;
  pending_deposits: number;
  unmapped_supplier_services: number;
  pending_outbox_events: number;
};

export default function AdminOverviewPage() {
  const { tenantId } = useAdminContext();
  const [data, setData] = useState<Overview | null>(null);
  const [error, setError] = useState("");

  useEffect(() => {
    if (!tenantId) {
      setData(null);
      return;
    }

    let active = true;
    setError("");

    apiFetch<ApiEnvelope<Overview>>("/api/v1/admin/overview", {}, tenantId)
      .then((result) => {
        if (active) setData(result.data);
      })
      .catch((reason) => {
        if (active) {
          setError(reason instanceof Error ? reason.message : "Request failed.");
        }
      });

    return () => {
      active = false;
    };
  }, [tenantId]);

  const metrics = [
    ["Active members", data?.users ?? 0],
    ["Products", data?.products ?? 0],
    ["Orders", data?.orders ?? 0],
    ["Active suppliers", data?.suppliers ?? 0],
    ["Supplier orders pending", data?.pending_supplier_orders ?? 0],
    ["Supplier orders failed", data?.failed_supplier_orders ?? 0],
    ["Deposits pending", data?.pending_deposits ?? 0],
    ["Services unmapped", data?.unmapped_supplier_services ?? 0],
    ["Outbox events pending", data?.pending_outbox_events ?? 0],
  ];

  return (
    <div>
      <div className="page-heading">
        <div>
          <p className="eyebrow">Operational intelligence</p>
          <h2>Overview</h2>
          <p>
            Tenant-level commerce, supplier fulfillment and financial workflow
            visibility.
          </p>
        </div>
      </div>

      {error && <div className="error-banner">{error}</div>}

      <div className="metric-grid operations-grid">
        {metrics.map(([label, value]) => (
          <article className="metric-card" key={String(label)}>
            <span>{label}</span>
            <strong>{value}</strong>
          </article>
        ))}

        <article className="metric-card">
          <span>Wallet liability (minor units)</span>
          <strong>
            {(data?.wallet_liability_minor ?? 0).toLocaleString()}
          </strong>
        </article>
      </div>

      <article className="architecture-card">
        <p className="eyebrow">Fulfillment control</p>
        <h3>Provider-independent supplier orchestration</h3>
        <p>
          Supplier orders are queued, routed through provider adapters and
          reconciled asynchronously. Failed fulfillment is moved to
          attention-required state instead of silently altering wallet money.
        </p>
      </article>
    </div>
  );
}