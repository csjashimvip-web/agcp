"use client";

import { FormEvent, useEffect, useState } from "react";
import { useAdminContext } from "@/components/admin-shell";
import { apiFetch, ApiEnvelope } from "@/lib/agcp-api";

type Plugin = {
  id: number;
  plugin_key: string;
  name: string;
  version: string;
  vendor: string;
  tenant_status?: string | null;
};

export default function PluginsPage() {
  const { tenantId } = useAdminContext();
  const [rows, setRows] = useState<Plugin[]>([]);

  async function load() {
    if (!tenantId) return;

    const result = await apiFetch<ApiEnvelope<Plugin[]>>(
      "/api/v1/admin/plugins",
      {},
      tenantId,
    );

    setRows(result.data);
  }

  useEffect(() => {
    load().catch(() => undefined);
  }, [tenantId]);

  async function register(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!tenantId) return;
    const form = new FormData(event.currentTarget);

    await apiFetch(
      "/api/v1/admin/plugins/manifests",
      {
        method: "POST",
        body: JSON.stringify({
          plugin_key: String(form.get("plugin_key") || ""),
          name: String(form.get("name") || ""),
          version: String(form.get("version") || "1.0.0"),
          vendor: String(form.get("vendor") || "AGCP"),
          capabilities: [],
          required_entitlements: [],
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
          <p className="eyebrow">Extensibility</p>
          <h2>Plugin Registry</h2>
          <p>
            Governed manifests and capabilities. Arbitrary uploaded code is not
            executed by this foundation.
          </p>
        </div>
      </div>

      <form className="write-card form-stack" onSubmit={register}>
        <input name="plugin_key" placeholder="vendor.plugin" required />
        <input name="name" placeholder="Plugin name" required />
        <input name="version" defaultValue="1.0.0" required />
        <input name="vendor" defaultValue="AGCP" required />
        <button className="primary-button" type="submit">
          Register manifest
        </button>
      </form>

      <div className="table-card spaced-card">
        <div className="table-status">{rows.length} manifests</div>
        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Plugin</th>
                <th>Version</th>
                <th>Vendor</th>
                <th>Tenant status</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id}>
                  <td>
                    {row.name}
                    <div className="subtle">{row.plugin_key}</div>
                  </td>
                  <td>{row.version}</td>
                  <td>{row.vendor}</td>
                  <td>{row.tenant_status ?? "not installed"}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}