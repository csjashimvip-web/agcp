"use client";

import { FormEvent, useEffect, useState } from "react";
import { useAdminContext } from "@/components/admin-shell";
import { apiFetch, ApiEnvelope } from "@/lib/agcp-api";

type Data = {
  channels: Array<{
    id: number;
    name: string;
    channel_type: string;
    status: string;
    external_delivery_enabled: boolean;
  }>;
  deliveries: Array<{
    id: number;
    channel_type: string;
    status: string;
    subject?: string | null;
  }>;
};

export default function NotificationChannelsPage() {
  const { tenantId } = useAdminContext();
  const [data, setData] = useState<Data>({
    channels: [],
    deliveries: [],
  });

  async function load() {
    if (!tenantId) return;

    const result = await apiFetch<ApiEnvelope<Data>>(
      "/api/v1/admin/notification-channels",
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
    const type = String(form.get("channel_type") || "in_app");

    await apiFetch(
      "/api/v1/admin/notification-channels",
      {
        method: "POST",
        body: JSON.stringify({
          name: String(form.get("name") || ""),
          channel_type: type,
          config: {},
          external_delivery_enabled: false,
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
          <p className="eyebrow">Notification infrastructure</p>
          <h2>Channels</h2>
          <p>
            External email/webhook delivery stays disabled until explicitly
            configured and enabled.
          </p>
        </div>
      </div>

      <form className="write-card form-stack" onSubmit={create}>
        <input name="name" placeholder="Channel name" required />
        <select name="channel_type" defaultValue="in_app">
          <option value="in_app">In-app</option>
          <option value="email">Email</option>
          <option value="webhook">Webhook</option>
        </select>
        <button className="primary-button" type="submit">
          Add channel
        </button>
      </form>

      <div className="metric-grid spaced-card">
        <article className="metric-card">
          <span>Channels</span>
          <strong>{data.channels.length}</strong>
        </article>
        <article className="metric-card">
          <span>Deliveries</span>
          <strong>{data.deliveries.length}</strong>
        </article>
      </div>
    </div>
  );
}