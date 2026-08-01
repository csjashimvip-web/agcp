"use client";

import { useEffect, useState } from "react";
import { useAdminContext } from "@/components/admin-shell";
import { apiFetch, ApiEnvelope } from "@/lib/agcp-api";

type Payout = {
  id: number;
  user_name: string;
  user_email: string;
  amount_minor: number;
  currency: string;
  destination_label: string;
  status: string;
};

export default function AdminPayoutsPage() {
  const { tenantId } = useAdminContext();
  const [rows, setRows] = useState<Payout[]>([]);
  const [message, setMessage] = useState("");

  async function load() {
    if (!tenantId) return;

    const result = await apiFetch<ApiEnvelope<Payout[]>>(
      "/api/v1/admin/payouts",
      {},
      tenantId,
    );

    setRows(result.data);
  }

  useEffect(() => {
    load().catch(() => undefined);
  }, [tenantId]);

  async function action(id: number, kind: "approve" | "reject" | "paid") {
    if (!tenantId) return;

    let note: string | null = null;

    if (kind === "reject") {
      note = window.prompt("Reason for rejection:");
      if (!note) return;
    }

    if (kind === "paid") {
      const ok = window.confirm(
        "Mark paid only after the external transfer is actually confirmed. Continue?",
      );
      if (!ok) return;
    }

    await apiFetch(
      `/api/v1/admin/payouts/${id}/${kind}`,
      {
        method: "POST",
        body: JSON.stringify({ note }),
      },
      tenantId,
    );

    setMessage(`Payout ${kind} completed.`);
    await load();
  }

  return (
    <div>
      <div className="page-heading">
        <div>
          <p className="eyebrow">Financial operations</p>
          <h2>Payouts</h2>
          <p>
            Funds remain held until an approved external transfer is confirmed.
          </p>
        </div>
      </div>

      {message && <div className="info-banner">{message}</div>}

      <div className="table-card">
        <div className="table-status">{rows.length} payout requests</div>
        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                <th>User</th>
                <th>Amount</th>
                <th>Destination</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id}>
                  <td>
                    {row.user_name}
                    <div className="subtle">{row.user_email}</div>
                  </td>
                  <td>
                    {row.amount_minor} {row.currency}
                  </td>
                  <td>{row.destination_label}</td>
                  <td>{row.status}</td>
                  <td>
                    <div className="row-actions">
                      <button
                        className="small-button"
                        disabled={row.status !== "pending_review"}
                        onClick={() => action(row.id, "approve")}
                      >
                        Approve
                      </button>
                      <button
                        className="small-button"
                        disabled={
                          !["pending_review", "approved"].includes(row.status)
                        }
                        onClick={() => action(row.id, "reject")}
                      >
                        Reject
                      </button>
                      <button
                        className="small-button"
                        disabled={row.status !== "approved"}
                        onClick={() => action(row.id, "paid")}
                      >
                        Mark paid
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
              {rows.length === 0 && (
                <tr>
                  <td colSpan={5} className="empty-cell">
                    No payout requests.
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