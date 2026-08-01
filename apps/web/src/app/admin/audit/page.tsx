"use client";

import { useEffect, useState } from "react";
import { useAdminContext } from "@/components/admin-shell";
import { apiFetch, ApiEnvelope } from "@/lib/agcp-api";

type Audit = {
  id: number;
  action: string;
  resource_type: string;
  resource_id?: string | null;
  user_name?: string | null;
  created_at: string;
};

export default function AuditPage() {
  const { tenantId } = useAdminContext();
  const [rows, setRows] = useState<Audit[]>([]);

  useEffect(() => {
    if (!tenantId) return;

    apiFetch<ApiEnvelope<Audit[]>>(
      "/api/v1/admin/audit",
      {},
      tenantId,
    )
      .then((result) => setRows(result.data))
      .catch(() => undefined);
  }, [tenantId]);

  return (
    <div>
      <div className="page-heading">
        <div>
          <p className="eyebrow">Reliability</p>
          <h2>Audit Explorer</h2>
          <p>Recent tenant administrative actions.</p>
        </div>
      </div>

      <div className="table-card">
        <div className="table-status">{rows.length} audit events</div>
        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Action</th>
                <th>Resource</th>
                <th>User</th>
                <th>Time</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id}>
                  <td>{row.action}</td>
                  <td>
                    {row.resource_type}
                    <div className="subtle">{row.resource_id ?? "â€”"}</div>
                  </td>
                  <td>{row.user_name ?? "System"}</td>
                  <td>{row.created_at}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}