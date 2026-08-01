"use client";

import { useEffect, useState } from "react";
import { useAdminContext } from "@/components/admin-shell";
import { apiFetch, ApiEnvelope } from "@/lib/agcp-api";

type Data = {
  snapshot: {
    database_ok: boolean;
    cache_ok: boolean;
    queue_backlog: number;
    failed_jobs: number;
    pending_outbox: number;
    pending_supplier_orders: number;
    health_bps: number;
  };
  slos: Array<{
    id: number;
    name: string;
    target_bps: number;
    observed_bps?: number | null;
    met: boolean;
  }>;
  backups: Array<{
    id: number;
    status: string;
    size_bytes: number;
    verified_at?: string | null;
  }>;
};

export default function ReliabilityPage() {
  const { tenantId } = useAdminContext();
  const [data, setData] = useState<Data | null>(null);

  async function load() {
    if (!tenantId) return;

    const result = await apiFetch<ApiEnvelope<Data>>(
      "/api/v1/admin/reliability",
      {},
      tenantId,
    );

    setData(result.data);
  }

  useEffect(() => {
    load().catch(() => undefined);
  }, [tenantId]);

  const snapshot = data?.snapshot;

  return (
    <div>
      <div className="page-heading">
        <div>
          <p className="eyebrow">Enterprise reliability</p>
          <h2>Reliability & SLO</h2>
          <p>Runtime readiness, backlog health, backup evidence and SLO state.</p>
        </div>

        <button className="small-button" onClick={() => load()}>
          Refresh
        </button>
      </div>

      <div className="metric-grid">
        {[
          ["Database", snapshot?.database_ok ? "OK" : "FAILED"],
          ["Cache", snapshot?.cache_ok ? "OK" : "FAILED"],
          ["Queue backlog", snapshot?.queue_backlog ?? 0],
          ["Failed jobs", snapshot?.failed_jobs ?? 0],
          ["Pending outbox", snapshot?.pending_outbox ?? 0],
          ["Supplier pending", snapshot?.pending_supplier_orders ?? 0],
          ["Health bps", snapshot?.health_bps ?? 0],
          ["Backups", data?.backups.length ?? 0],
        ].map(([label, value]) => (
          <article className="metric-card" key={String(label)}>
            <span>{label}</span>
            <strong>{String(value)}</strong>
          </article>
        ))}
      </div>
    </div>
  );
}