"use client";

import { FormEvent, useEffect, useState } from "react";
import { useAdminContext } from "@/components/admin-shell";
import { apiFetch, ApiEnvelope } from "@/lib/agcp-api";

type Data = {
  security_audits: Array<{
    id: number;
    environment: string;
    status: string;
    critical_findings: number;
    warning_findings: number;
  }>;
  dependency_audits: Array<{
    id: number;
    ecosystem: string;
    status: string;
    critical_count: number;
    high_count: number;
  }>;
  staging_runs: Array<{
    id: number;
    git_commit: string;
    status: string;
    critical_failures: number;
    warnings: number;
  }>;
  cutover_runs: Array<{
    id: number;
    git_commit: string;
    status: string;
    traffic_open_allowed: boolean;
  }>;
};

export default function Rc1Page() {
  const { tenantId } = useAdminContext();
  const [data, setData] = useState<Data>({
    security_audits: [],
    dependency_audits: [],
    staging_runs: [],
    cutover_runs: [],
  });

  async function load() {
    if (!tenantId) return;

    const result = await apiFetch<ApiEnvelope<Data>>(
      "/api/v1/admin/rc1",
      {},
      tenantId,
    );

    setData(result.data);
  }

  useEffect(() => {
    load().catch(() => undefined);
  }, [tenantId]);

  async function runSecurity(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!tenantId) return;

    const form = new FormData(event.currentTarget);

    await apiFetch(
      "/api/v1/admin/rc1/security-audit",
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
          <p className="eyebrow">Release Candidate 1</p>
          <h2>Stabilization & Cutover</h2>
          <p>
            Security, dependency, staging and production-traffic gates.
          </p>
        </div>
      </div>

      <form className="write-card form-stack" onSubmit={runSecurity}>
        <select name="environment" defaultValue="staging">
          <option value="test">Test</option>
          <option value="staging">Staging</option>
          <option value="production">Production</option>
        </select>
        <input name="git_commit" placeholder="Git commit SHA" required />
        <button className="primary-button" type="submit">
          Run security audit
        </button>
      </form>

      <div className="metric-grid spaced-card">
        <article className="metric-card">
          <span>Security audits</span>
          <strong>{data.security_audits.length}</strong>
        </article>
        <article className="metric-card">
          <span>Dependency audits</span>
          <strong>{data.dependency_audits.length}</strong>
        </article>
        <article className="metric-card">
          <span>Staging runs</span>
          <strong>{data.staging_runs.length}</strong>
        </article>
        <article className="metric-card">
          <span>Cutover runs</span>
          <strong>{data.cutover_runs.length}</strong>
        </article>
      </div>
    </div>
  );
}