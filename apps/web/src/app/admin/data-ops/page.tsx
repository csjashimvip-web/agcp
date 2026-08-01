"use client";

import { FormEvent, useEffect, useState } from "react";
import { useAdminContext } from "@/components/admin-shell";
import { apiFetch, ApiEnvelope } from "@/lib/agcp-api";

type Data = {
  exports: Array<{
    id: number;
    status: string;
    size_bytes: number;
    sha256?: string | null;
  }>;
  imports: Array<{
    id: number;
    status: string;
    rows_total: number;
    rows_valid: number;
    rows_failed: number;
    dry_run: boolean;
  }>;
  retention_policies: Array<{
    id: number;
    dataset: string;
    retention_days: number;
    mode: string;
  }>;
};

export default function DataOpsPage() {
  const { tenantId } = useAdminContext();
  const [data, setData] = useState<Data>({
    exports: [],
    imports: [],
    retention_policies: [],
  });

  async function load() {
    if (!tenantId) return;

    const result = await apiFetch<ApiEnvelope<Data>>(
      "/api/v1/admin/data-ops",
      {},
      tenantId,
    );

    setData(result.data);
  }

  useEffect(() => {
    load().catch(() => undefined);
  }, [tenantId]);

  async function createExport() {
    if (!tenantId) return;

    await apiFetch(
      "/api/v1/admin/data-ops/exports",
      { method: "POST" },
      tenantId,
    );

    await load();
  }

  async function saveRetention(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!tenantId) return;

    const form = new FormData(event.currentTarget);

    await apiFetch(
      "/api/v1/admin/data-ops/retention",
      {
        method: "POST",
        body: JSON.stringify({
          dataset: String(form.get("dataset") || ""),
          retention_days: Number(form.get("retention_days") || 365),
          mode: String(form.get("mode") || "review"),
        }),
      },
      tenantId,
    );

    event.currentTarget.reset();
    await load();
  }

  return (
    <div>
      <div className="page-heading">
        <div>
          <p className="eyebrow">Data operations</p>
          <h2>Import / Export / Retention</h2>
          <p>Portable exports and dry-run-first catalog imports.</p>
        </div>

        <button className="small-button" onClick={createExport}>
          Create export
        </button>
      </div>

      <form className="write-card form-stack" onSubmit={saveRetention}>
        <h3>Retention policy</h3>
        <input name="dataset" placeholder="admin_audit_events" required />
        <input name="retention_days" type="number" min="1" defaultValue="365" required />
        <select name="mode" defaultValue="review">
          <option value="review">Review only</option>
          <option value="purge">Eligible for controlled purge</option>
        </select>
        <button className="primary-button" type="submit">
          Save policy
        </button>
      </form>

      <div className="metric-grid spaced-card">
        <article className="metric-card">
          <span>Exports</span>
          <strong>{data.exports.length}</strong>
        </article>
        <article className="metric-card">
          <span>Imports</span>
          <strong>{data.imports.length}</strong>
        </article>
        <article className="metric-card">
          <span>Retention policies</span>
          <strong>{data.retention_policies.length}</strong>
        </article>
      </div>
    </div>
  );
}