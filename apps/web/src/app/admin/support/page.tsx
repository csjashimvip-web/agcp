"use client";

import { useEffect, useState } from "react";
import { useAdminContext } from "@/components/admin-shell";
import { apiFetch, ApiEnvelope } from "@/lib/agcp-api";

type Ticket = {
  id: number;
  ticket_number: string;
  subject: string;
  user_name: string;
  user_email: string;
  priority: string;
  status: string;
};

export default function AdminSupportPage() {
  const { tenantId } = useAdminContext();
  const [rows, setRows] = useState<Ticket[]>([]);

  useEffect(() => {
    if (!tenantId) return;

    apiFetch<ApiEnvelope<Ticket[]>>(
      "/api/v1/admin/support",
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
          <p className="eyebrow">Customer operations</p>
          <h2>Support Tickets</h2>
          <p>Tenant-scoped support queue.</p>
        </div>
      </div>

      <div className="table-card">
        <div className="table-status">{rows.length} tickets</div>
        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Ticket</th>
                <th>User</th>
                <th>Priority</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id}>
                  <td>
                    {row.subject}
                    <div className="subtle">{row.ticket_number}</div>
                  </td>
                  <td>
                    {row.user_name}
                    <div className="subtle">{row.user_email}</div>
                  </td>
                  <td>{row.priority}</td>
                  <td>{row.status}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}