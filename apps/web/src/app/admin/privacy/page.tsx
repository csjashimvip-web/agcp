"use client";

import { useEffect, useState } from "react";
import { useAdminContext } from "@/components/admin-shell";
import { apiFetch, ApiEnvelope } from "@/lib/agcp-api";

type PrivacyRequest = {
  id: number;
  user_name: string;
  user_email: string;
  type: string;
  status: string;
  requested_at: string;
};

export default function PrivacyPage() {
  const { tenantId } = useAdminContext();
  const [rows, setRows] = useState<PrivacyRequest[]>([]);

  async function load() {
    if (!tenantId) return;

    const result = await apiFetch<ApiEnvelope<PrivacyRequest[]>>(
      "/api/v1/admin/privacy",
      {},
      tenantId,
    );

    setRows(result.data);
  }

  useEffect(() => {
    load().catch(() => undefined);
  }, [tenantId]);

  async function review(id: number, status: "approved" | "rejected" | "completed") {
    if (!tenantId) return;

    await apiFetch(
      `/api/v1/admin/privacy/${id}`,
      {
        method: "PATCH",
        body: JSON.stringify({ status }),
      },
      tenantId,
    );

    await load();
  }

  return (
    <div>
      <div className="page-heading">
        <div>
          <p className="eyebrow">Data governance</p>
          <h2>Privacy Requests</h2>
          <p>
            Deletion is a review workflow; financial and audit records are not
            silently erased.
          </p>
        </div>
      </div>

      <div className="table-card">
        <div className="table-status">{rows.length} requests</div>
        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                <th>User</th>
                <th>Type</th>
                <th>Status</th>
                <th>Requested</th>
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
                  <td>{row.type}</td>
                  <td>{row.status}</td>
                  <td>{row.requested_at}</td>
                  <td>
                    <div className="row-actions">
                      <button className="small-button" onClick={() => review(row.id, "approved")}>
                        Approve
                      </button>
                      <button className="small-button" onClick={() => review(row.id, "rejected")}>
                        Reject
                      </button>
                      <button className="small-button" onClick={() => review(row.id, "completed")}>
                        Complete
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}