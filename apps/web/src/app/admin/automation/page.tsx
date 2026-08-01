"use client";

import { FormEvent, useEffect, useState } from "react";
import { useAdminContext } from "@/components/admin-shell";
import { apiFetch, ApiEnvelope } from "@/lib/agcp-api";

type Data = {
  rules: Array<{
    id: number;
    name: string;
    event_type: string;
    action_type: string;
    status: string;
  }>;
  runs: Array<{
    id: number;
    event_type: string;
    status: string;
  }>;
};

export default function AutomationPage() {
  const { tenantId } = useAdminContext();
  const [data, setData] = useState<Data>({
    rules: [],
    runs: [],
  });

  async function load() {
    if (!tenantId) return;

    const result = await apiFetch<ApiEnvelope<Data>>(
      "/api/v1/admin/automation",
      {},
      tenantId,
    );

    setData(result.data);
  }

  useEffect(() => {
    load().catch(() => undefined);
  }, [tenantId]);

  async function create(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!tenantId) return;
    const form = new FormData(event.currentTarget);

    await apiFetch(
      "/api/v1/admin/automation/rules",
      {
        method: "POST",
        body: JSON.stringify({
          name: String(form.get("name") || ""),
          event_type: String(form.get("event_type") || ""),
          action_type: "notify",
          action_config: {
            channel: "in_app",
            subject: String(form.get("subject") || ""),
            body: String(form.get("body") || ""),
          },
          priority: 100,
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
          <p className="eyebrow">Automation</p>
          <h2>Rules Engine</h2>
          <p>Event-to-action rules with auditable run history.</p>
        </div>
      </div>

      <form className="write-card form-stack" onSubmit={create}>
        <input name="name" placeholder="Rule name" required />
        <input
          name="event_type"
          placeholder="commerce.order.completed.v1"
          required
        />
        <input name="subject" placeholder="Notification subject" />
        <textarea name="body" rows={4} placeholder="Notification body" required />
        <button className="primary-button" type="submit">
          Create automation
        </button>
      </form>

      <div className="metric-grid spaced-card">
        <article className="metric-card">
          <span>Rules</span>
          <strong>{data.rules.length}</strong>
        </article>
        <article className="metric-card">
          <span>Runs</span>
          <strong>{data.runs.length}</strong>
        </article>
      </div>
    </div>
  );
}