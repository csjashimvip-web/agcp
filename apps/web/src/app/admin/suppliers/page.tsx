"use client";

import { FormEvent, useState } from "react";
import ResourceTable from "@/components/resource-table";
import { useAdminContext } from "@/components/admin-shell";
import { apiFetch } from "@/lib/agcp-api";

export default function SuppliersPage() {
  const { tenantId } = useAdminContext();
  const [message, setMessage] = useState("");
  const [refreshKey, setRefreshKey] = useState(0);

  async function createSupplier(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!tenantId) return;

    const form = new FormData(event.currentTarget);

    await apiFetch(
      "/api/v1/admin/suppliers",
      {
        method: "POST",
        body: JSON.stringify({
          name: String(form.get("name") || ""),
          code: String(form.get("code") || ""),
          driver: "dhru-fusion",
          status: "active",
          priority: Number(form.get("priority") || 100),
          timeout_seconds: 30,
          max_retries: 2,
          base_url: String(form.get("base_url") || ""),
          username: String(form.get("username") || ""),
          api_key: String(form.get("api_key") || ""),
        }),
      },
      tenantId,
    );

    event.currentTarget.reset();
    setMessage("Dhru supplier saved with encrypted credentials.");
    setRefreshKey((value) => value + 1);
  }

  return (
    <div>
      <div className="write-card">
        <p className="eyebrow">Dhru Fusion adapter</p>
        <h3>Add supplier connection</h3>
        <p className="muted-copy">
          API credentials are sent only to the Laravel backend and stored encrypted.
        </p>

        <form className="inline-form supplier-form" onSubmit={createSupplier}>
          <input name="name" placeholder="Supplier name" required />
          <input name="code" placeholder="Internal code" required />
          <input name="base_url" type="url" placeholder="https://supplier.example" required />
          <input name="username" placeholder="API username" required />
          <input name="api_key" type="password" placeholder="API access key" required />
          <input name="priority" type="number" min="1" defaultValue="100" required />
          <button className="primary-button" type="submit">Save supplier</button>
        </form>

        {message && <p className="success-note">{message}</p>}
      </div>

      <ResourceTable
        key={refreshKey}
        title="Suppliers"
        description="Supplier adapters, routing priority and runtime configuration."
        endpoint="/api/v1/admin/suppliers"
        columns={[
          { key: "name", label: "Supplier" },
          { key: "code", label: "Code" },
          { key: "driver", label: "Driver" },
          { key: "status", label: "Status" },
          { key: "priority", label: "Priority" },
          { key: "last_healthcheck_at", label: "Last health check" },
        ]}
      />
    </div>
  );
}