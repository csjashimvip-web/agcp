"use client";

import { FormEvent, useEffect, useState } from "react";
import { useAdminContext } from "@/components/admin-shell";
import { apiFetch, ApiEnvelope } from "@/lib/agcp-api";

type Data = {
  releases: Array<{
    id: number;
    release_uuid: string;
    environment: string;
    git_commit: string;
    status: string;
    started_at: string;
  }>;
  checks: Array<{
    id: number;
    deployment_release_id: number;
    check_key: string;
    status: string;
  }>;
};

export default function DeploymentsPage() {
  const { tenantId } = useAdminContext();
  const [data, setData] = useState<Data>({ releases: [], checks: [] });

  async function load() {
    if (!tenantId) return;

    const result = await apiFetch<ApiEnvelope<Data>>(
      "/api/v1/admin/deployments",
      {},
      tenantId,
    );

    setData(result.data);
  }

  useEffect(() => {
    load().catch(() => undefined);
  }, [tenantId]);

  async function record(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!tenantId) return;

    const form = new FormData(event.currentTarget);

    await apiFetch(
      "/api/v1/admin/deployments",
      {
        method: "POST",
        body: JSON.stringify({
          environment: String(form.get("environment") || "staging"),
          git_commit: String(form.get("git_commit") || ""),
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
          <p className="eyebrow">Release engineering</p>
          <h2>Deployment Readiness</h2>
          <p>Record release checks before opening production traffic.</p>
        </div>
      </div>

      <form className="write-card form-stack" onSubmit={record}>
        <select name="environment" defaultValue="staging">
          <option value="staging">Staging</option>
          <option value="production">Production</option>
        </select>
        <input name="git_commit" placeholder="Git commit SHA" required />
        <button className="primary-button" type="submit">
          Run readiness checks
        </button>
      </form>

      <div className="table-card spaced-card">
        <div className="table-status">{data.releases.length} releases</div>
        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Environment</th>
                <th>Commit</th>
                <th>Status</th>
                <th>Started</th>
              </tr>
            </thead>
            <tbody>
              {data.releases.map((row) => (
                <tr key={row.id}>
                  <td>{row.environment}</td>
                  <td><code>{row.git_commit.slice(0, 12)}</code></td>
                  <td>{row.status}</td>
                  <td>{row.started_at}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}