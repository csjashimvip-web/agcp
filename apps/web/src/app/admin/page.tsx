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

  const cards = [
    ["Active members", data?.users ?? 0],
    ["Products", data?.products ?? 0],
    ["Orders", data?.orders ?? 0],
    ["Active suppliers", data?.suppliers ?? 0],
  ];

  return (
    <div>
      <div className="page-heading">
        <div>
          <p className="eyebrow">Operational intelligence</p>
          <h2>Overview</h2>
          <p>Live tenant-level visibility across the transactional core.</p>
        </div>
      </div>

      {error && <div className="error-banner">{error}</div>}

      <div className="metric-grid">
        {cards.map(([label, value]) => (
          <article className="metric-card" key={String(label)}>
            <span>{label}</span>
            <strong>{value}</strong>
          </article>
        ))}

        <article className="metric-card wide">
          <span>Wallet liability (minor units)</span>
          <strong>
            {(data?.wallet_liability_minor ?? 0).toLocaleString()}
          </strong>
        </article>
      </div>

      <article className="architecture-card">
        <p className="eyebrow">Architecture status</p>
        <h3>Event-driven modular commerce core</h3>
        <p>
          Tenant isolation, double-entry ledger, supplier routing/failover,
          signed payment webhooks and versioned APIs are active boundaries.
        </p>
      </article>
    </div>
  );
}