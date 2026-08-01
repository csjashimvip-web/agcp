"use client";

import { useEffect, useState } from "react";
import { useAdminContext } from "@/components/admin-shell";
import { apiFetch, ApiEnvelope } from "@/lib/agcp-api";

type Health = {
  database: string;
  queue_connection: string;
  cache_store: string;
  pending_outbox_events: number;
  oldest_pending_outbox_at?: string | null;
  failed_queue_jobs: number;
  pending_supplier_orders: number;
  failed_supplier_orders: number;
  unread_notifications: number;
  financial_compensations: number;
  generated_at: string;
};

export default function OperationsPage() {
  const { tenantId } = useAdminContext();
  const [health, setHealth] = useState<Health | null>(null);
  const [error, setError] = useState("");

  async function load() {
    if (!tenantId) return;

    const result = await apiFetch<ApiEnvelope<Health>>(
      "/api/v1/admin/operations/health",
      {},
      tenantId,
    );

    setHealth(result.data);
  }

  useEffect(() => {
    load().catch((reason) =>
      setError(reason instanceof Error ? reason.message : "Unable to load."),
    );
  }, [tenantId]);

  const metrics = [
    ["Database", health?.database ?? "â€”"],
    ["Queue", health?.queue_connection ?? "â€”"],
    ["Cache", health?.cache_store ?? "â€”"],
    ["Pending outbox", health?.pending_outbox_events ?? 0],
    ["Failed queue jobs", health?.failed_queue_jobs ?? 0],
    ["Pending supplier orders", health?.pending_supplier_orders ?? 0],
    ["Failed supplier orders", health?.failed_supplier_orders ?? 0],
    ["Unread notifications", health?.unread_notifications ?? 0],
    ["Compensations", health?.financial_compensations ?? 0],
  ];

  return (
    <div>
      <div className="page-heading">
        <div>
          <p className="eyebrow">Reliability</p>
          <h2>Operations Health</h2>
          <p>
            Background processing, outbox publication and financial
            compensation visibility.
          </p>
        </div>

        <button className="small-button" onClick={() => load()}>
          Refresh
        </button>
      </div>

      {error && <div className="error-banner">{error}</div>}

      <div className="metric-grid operations-grid">
        {metrics.map(([label, value]) => (
          <article className="metric-card" key={String(label)}>
            <span>{label}</span>
            <strong className="metric-value-small">{value}</strong>
          </article>
        ))}
      </div>

      <article className="architecture-card">
        <p className="eyebrow">Runtime requirement</p>
        <h3>Queue worker + scheduler</h3>
        <p>
          Supplier execution, reconciliation and outbox publication depend on
          the Redis queue worker and Laravel scheduler remaining active.
        </p>
        <p className="subtle">
          Oldest pending outbox: {health?.oldest_pending_outbox_at ?? "none"}
          {" Â· "}
          Generated: {health?.generated_at ?? "â€”"}
        </p>
      </article>
    </div>
  );
}