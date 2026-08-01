"use client";

import { FormEvent, useEffect, useState } from "react";
import { useAdminContext } from "@/components/admin-shell";
import { apiFetch, ApiEnvelope } from "@/lib/agcp-api";

type Data = {
  contracts: {
    total: number;
    missing: string[];
    passed: boolean;
  };
  performance: Array<{
    id: number;
    probe: string;
    p95_ms: number;
    environment: string;
  }>;
  audits: Array<{
    id: number;
    environment: string;
    git_commit: string;
    status: string;
    critical_findings: number;
    warning_findings: number;
  }>;
};

export default function ReleaseCandidatePage() {
  const { tenantId } = useAdminContext();
  const [data, setData] = useState<Data | null>(null);

  async function load() {
    if (!tenantId) return;

    const result = await apiFetch<ApiEnvelope<Data>>(
      "/api/v1/admin/release-candidate",
      {},
      tenantId,
    );

    setData(result.data);
  }

  useEffect(() => {
    load().catch(() => undefined);
  }, [tenantId]);

  async function capturePerformance() {
    if (!tenantId) return;

    await apiFetch(
      "/api/v1/admin/release-candidate/performance",
      {
        method: "POST",
        body: JSON.stringify({
          environment: "local",
          samples: 25,
        }),
      },
      tenantId,
    );

    await load();
  }

  async function runAudit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!tenantId) return;

    const form = new FormData(event.currentTarget);

    await apiFetch(
      "/api/v1/admin/release-candidate/audit",
      {
        method: "POST",
        body: JSON.stringify({
          environment: String(form.get("environment") || "staging"),
          git_commit: String(form.get("git_commit") || ""),
        }),
      },
      tenantId,
    );

    await load();
  }

  return (
    <div>
      <div className="page-heading">
        <div>
          <p className="eyebrow">Release engineering</p>
          <h2>Release Candidate Audit</h2>
          <p>
            API contracts, runtime readiness, performance evidence, backups and
            production safety checks.
          </p>
        </div>

        <button className="small-button" onClick={capturePerformance}>
          Capture performance
        </button>
      </div>

      <form className="write-card form-stack" onSubmit={runAudit}>
        <select name="environment" defaultValue="staging">
          <option value="local">Local</option>
          <option value="staging">Staging</option>
          <option value="production">Production</option>
        </select>
        <input name="git_commit" placeholder="Git commit SHA" required />
        <button className="primary-button" type="submit">
          Run RC audit
        </button>
      </form>

      <div className="metric-grid spaced-card">
        <article className="metric-card">
          <span>API contract</span>
          <strong>{data?.contracts.passed ? "PASS" : "CHECK"}</strong>
        </article>
        <article className="metric-card">
          <span>Performance records</span>
          <strong>{data?.performance.length ?? 0}</strong>
        </article>
        <article className="metric-card">
          <span>RC audits</span>
          <strong>{data?.audits.length ?? 0}</strong>
        </article>
      </div>
    </div>
  );
}