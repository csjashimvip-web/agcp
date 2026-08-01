"use client";

import { FormEvent, useEffect, useState } from "react";
import { useAdminContext } from "@/components/admin-shell";
import { apiFetch, ApiEnvelope } from "@/lib/agcp-api";

type Client = {
  id: number;
  public_id: string;
  name: string;
  user_name: string;
  user_email: string;
  abilities: string;
  status: string;
  rate_limit_per_minute: number;
  last_used_at?: string | null;
};

export default function ResellerApiPage() {
  const { tenantId } = useAdminContext();
  const [rows, setRows] = useState<Client[]>([]);
  const [token, setToken] = useState("");
  const [message, setMessage] = useState("");

  async function load() {
    if (!tenantId) return;

    const result = await apiFetch<ApiEnvelope<Client[]>>(
      "/api/v1/admin/reseller-api-clients",
      {},
      tenantId,
    );

    setRows(result.data);
  }

  useEffect(() => {
    load().catch(() => undefined);
  }, [tenantId]);

  async function create(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!tenantId) return;

    const form = new FormData(event.currentTarget);

    const result = await apiFetch<
      ApiEnvelope<{ token: string }>
    >(
      "/api/v1/admin/reseller-api-clients",
      {
        method: "POST",
        body: JSON.stringify({
          name: String(form.get("name") || ""),
          user_email: String(form.get("user_email") || ""),
          abilities: [
            "services:read",
            "wallet:read",
            "orders:create",
            "orders:read",
          ],
          rate_limit_per_minute: Number(
            form.get("rate_limit_per_minute") || 120,
          ),
        }),
      },
      tenantId,
    );

    setToken(result.data.token);
    setMessage(
      "API client created. Copy the token now; AGCP stores only its hash.",
    );

    event.currentTarget.reset();
    await load();
  }

  async function revoke(id: number) {
    if (!tenantId) return;

    await apiFetch(
      `/api/v1/admin/reseller-api-clients/${id}/revoke`,
      { method: "POST" },
      tenantId,
    );

    setMessage("API client revoked.");
    await load();
  }

  return (
    <div>
      <div className="page-heading">
        <div>
          <p className="eyebrow">Smart API Gateway</p>
          <h2>Reseller API</h2>
          <p>
            Issue revocable, hashed API credentials for downstream AGCP
            resellers and integrations.
          </p>
        </div>
      </div>

      <div className="write-card">
        <h3>Create API client</h3>
        <form className="inline-form" onSubmit={create}>
          <input name="name" placeholder="Client name" required />
          <input
            name="user_email"
            type="email"
            placeholder="Tenant member email"
            required
          />
          <input
            name="rate_limit_per_minute"
            type="number"
            min="1"
            max="3000"
            defaultValue="120"
            required
          />
          <button className="primary-button" type="submit">
            Create credential
          </button>
        </form>
      </div>

      {message && <div className="info-banner">{message}</div>}

      {token && (
        <div className="secret-card">
          <strong>One-time API token</strong>
          <code>{token}</code>
          <p>
            Save this token securely. It cannot be recovered from AGCP after
            this page is closed.
          </p>
        </div>
      )}

      <div className="table-card">
        <div className="table-status">{rows.length} API clients</div>
        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Client</th>
                <th>User</th>
                <th>Status</th>
                <th>Rate/min</th>
                <th>Last used</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((client) => (
                <tr key={client.id}>
                  <td>
                    <strong>{client.name}</strong>
                    <div className="subtle">{client.public_id}</div>
                  </td>
                  <td>
                    {client.user_name}
                    <div className="subtle">{client.user_email}</div>
                  </td>
                  <td>{client.status}</td>
                  <td>{client.rate_limit_per_minute}</td>
                  <td>{client.last_used_at ?? "â€”"}</td>
                  <td>
                    <button
                      className="small-button"
                      disabled={client.status !== "active"}
                      onClick={() => revoke(client.id)}
                    >
                      Revoke
                    </button>
                  </td>
                </tr>
              ))}

              {rows.length === 0 && (
                <tr>
                  <td colSpan={6} className="empty-cell">
                    No reseller API clients.
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