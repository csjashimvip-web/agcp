"use client";

import { FormEvent, useEffect, useState } from "react";
import { useCustomerContext } from "@/components/customer-shell";
import { apiFetch, ApiEnvelope } from "@/lib/agcp-api";

type Ticket = {
  id: number;
  ticket_number: string;
  subject: string;
  priority: string;
  status: string;
};

export default function CustomerSupportPage() {
  const { tenant } = useCustomerContext();
  const [rows, setRows] = useState<Ticket[]>([]);

  async function load() {
    if (!tenant) return;

    const result = await apiFetch<ApiEnvelope<Ticket[]>>(
      "/api/v1/customer/support",
      {},
      tenant,
    );

    setRows(result.data);
  }

  useEffect(() => {
    load().catch(() => undefined);
  }, [tenant]);

  async function create(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!tenant) return;

    const form = new FormData(event.currentTarget);

    await apiFetch(
      "/api/v1/customer/support",
      {
        method: "POST",
        body: JSON.stringify({
          subject: String(form.get("subject") || ""),
          category: "general",
          priority: "normal",
          message: String(form.get("message") || ""),
        }),
      },
      tenant,
    );

    event.currentTarget.reset();
    await load();
  }

  return (
    <div>
      <div className="page-heading">
        <div>
          <p className="eyebrow">Help center</p>
          <h2>Support</h2>
          <p>Create and track support requests.</p>
        </div>
      </div>

      <form className="write-card form-stack" onSubmit={create}>
        <input name="subject" placeholder="Subject" required />
        <textarea
          name="message"
          rows={5}
          placeholder="Describe the issue"
          required
        />
        <button className="primary-button" type="submit">
          Create ticket
        </button>
      </form>

      <div className="table-card spaced-card">
        <div className="table-status">{rows.length} tickets</div>
        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Ticket</th>
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