"use client";

import { useEffect, useState } from "react";
import { useAdminContext } from "@/components/admin-shell";
import { apiFetch, ApiEnvelope } from "@/lib/agcp-api";

type Deposit = {
  id: number;
  deposit_uuid: string;
  customer_name?: string | null;
  customer_email?: string | null;
  amount_minor: number;
  currency: string;
  method: string;
  status: string;
  created_at: string;
};

export default function DepositsPage() {
  const { tenantId } = useAdminContext();
  const [rows, setRows] = useState<Deposit[]>([]);
  const [message, setMessage] = useState("");

  async function load() {
    if (!tenantId) return;

    const result = await apiFetch<ApiEnvelope<Deposit[]>>(
      "/api/v1/admin/deposits",
      {},
      tenantId,
    );

    setRows(result.data);
  }

  useEffect(() => {
    load().catch((error) =>
      setMessage(error instanceof Error ? error.message : "Unable to load."),
    );
  }, [tenantId]);

  async function approve(id: number) {
    if (!tenantId) return;

    await apiFetch(
      `/api/v1/admin/deposits/${id}/approve`,
      { method: "POST" },
      tenantId,
    );

    setMessage("Deposit approved and posted to the ledger.");
    await load();
  }

  return (
    <div>
      <div className="page-heading">
        <div>
          <p className="eyebrow">Financial operations</p>
          <h2>Deposits</h2>
          <p>
            Review pending deposits. Approval uses the idempotent double-entry
            settlement service.
          </p>
        </div>
      </div>

      {message && <div className="info-banner">{message}</div>}

      <div className="table-card">
        <div className="table-status">{rows.length} deposits</div>
        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Customer</th>
                <th>Amount</th>
                <th>Method</th>
                <th>Status</th>
                <th>Created</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id}>
                  <td>
                    <strong>{row.customer_name ?? "Customer"}</strong>
                    <div className="subtle">{row.customer_email ?? "â€”"}</div>
                  </td>
                  <td>
                    {row.amount_minor} {row.currency}
                  </td>
                  <td>{row.method}</td>
                  <td>{row.status}</td>
                  <td>{row.created_at}</td>
                  <td>
                    <button
                      className="small-button"
                      disabled={row.status !== "pending"}
                      onClick={() => approve(row.id)}
                    >
                      Approve
                    </button>
                  </td>
                </tr>
              ))}
              {rows.length === 0 && (
                <tr>
                  <td colSpan={6} className="empty-cell">
                    No deposits found.
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