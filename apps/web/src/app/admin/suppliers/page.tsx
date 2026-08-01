"use client";

import { FormEvent, useEffect, useState } from "react";
import { useAdminContext } from "@/components/admin-shell";
import { apiFetch, ApiEnvelope } from "@/lib/agcp-api";

type Supplier = {
  id: number;
  name: string;
  code: string;
  driver: string;
  status: string;
  priority: number;
  last_healthcheck_at?: string | null;
};

export default function SuppliersPage() {
  const { tenantId } = useAdminContext();
  const [suppliers, setSuppliers] = useState<Supplier[]>([]);
  const [message, setMessage] = useState("");
  const [busyId, setBusyId] = useState<number | null>(null);

  async function load() {
    if (!tenantId) return;

    const result = await apiFetch<ApiEnvelope<Supplier[]>>(
      "/api/v1/admin/suppliers",
      {},
      tenantId,
    );

    setSuppliers(result.data);
  }

  useEffect(() => {
    load().catch((error) =>
      setMessage(error instanceof Error ? error.message : "Unable to load."),
    );
  }, [tenantId]);

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
    setMessage("Supplier saved with encrypted credentials.");
    await load();
  }

  async function action(id: number, kind: "test" | "sync") {
    if (!tenantId) return;

    setBusyId(id);
    setMessage("");

    try {
      const result = await apiFetch<ApiEnvelope<Record<string, unknown>>>(
        `/api/v1/admin/suppliers/${id}/${kind}`,
        { method: "POST" },
        tenantId,
      );

      setMessage(
        kind === "test"
          ? "Connection test completed."
          : `Sync completed: ${String(result.data.discovered ?? 0)} services discovered.`,
      );

      await load();
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "Operation failed.");
    } finally {
      setBusyId(null);
    }
  }

  return (
    <div>
      <div className="write-card">
        <p className="eyebrow">Provider adapter</p>
        <h3>Add Dhru-compatible supplier</h3>
        <p className="muted-copy">
          Credentials are submitted only to Laravel and stored encrypted.
        </p>

        <form className="inline-form supplier-form" onSubmit={createSupplier}>
          <input name="name" placeholder="Supplier name" required />
          <input name="code" placeholder="Internal code" required />
          <input
            name="base_url"
            type="url"
            placeholder="https://supplier.example"
            required
          />
          <input name="username" placeholder="API username" required />
          <input
            name="api_key"
            type="password"
            placeholder="API access key"
            required
          />
          <input
            name="priority"
            type="number"
            min="1"
            defaultValue="100"
            required
          />
          <button className="primary-button" type="submit">
            Save supplier
          </button>
        </form>
      </div>

      {message && <div className="info-banner">{message}</div>}

      <div className="table-card">
        <div className="table-status">{suppliers.length} suppliers</div>
        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Supplier</th>
                <th>Driver</th>
                <th>Status</th>
                <th>Priority</th>
                <th>Health check</th>
                <th>Operations</th>
              </tr>
            </thead>
            <tbody>
              {suppliers.map((supplier) => (
                <tr key={supplier.id}>
                  <td>
                    <strong>{supplier.name}</strong>
                    <div className="subtle">{supplier.code}</div>
                  </td>
                  <td>{supplier.driver}</td>
                  <td>{supplier.status}</td>
                  <td>{supplier.priority}</td>
                  <td>{supplier.last_healthcheck_at ?? "â€”"}</td>
                  <td>
                    <div className="row-actions">
                      <button
                        className="small-button"
                        disabled={busyId === supplier.id}
                        onClick={() => action(supplier.id, "test")}
                      >
                        Test
                      </button>
                      <button
                        className="small-button"
                        disabled={busyId === supplier.id}
                        onClick={() => action(supplier.id, "sync")}
                      >
                        Sync services
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
              {suppliers.length === 0 && (
                <tr>
                  <td colSpan={6} className="empty-cell">
                    No suppliers configured.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}