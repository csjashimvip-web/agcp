"use client";

import { FormEvent, useEffect, useState } from "react";
import { useAdminContext } from "@/components/admin-shell";
import { apiFetch, ApiEnvelope } from "@/lib/agcp-api";

type Data = {
  subscriptions: Array<{
    id: number;
    name: string;
    endpoint_url: string;
    external_delivery_enabled: boolean;
    status: string;
    consecutive_failures: number;
  }>;
  deliveries: Array<{
    id: number;
    event_type: string;
    status: string;
    attempts: number;
    response_code?: number | null;
  }>;
};

export default function WebhooksPage() {
  const { tenantId } = useAdminContext();
  const [data, setData] = useState<Data>({
    subscriptions: [],
    deliveries: [],
  });
  const [secret, setSecret] = useState("");

  async function load() {
    if (!tenantId) return;

    const result = await apiFetch<ApiEnvelope<Data>>(
      "/api/v1/admin/webhooks",
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

    const result = await apiFetch<
      ApiEnvelope<{ signing_secret: string }>
    >(
      "/api/v1/admin/webhooks",
      {
        method: "POST",
        body: JSON.stringify({
          name: String(form.get("name") || ""),
          endpoint_url: String(form.get("endpoint_url") || ""),
          event_types: String(form.get("event_type") || "")
            .split(",")
            .map((value) => value.trim())
            .filter(Boolean),
          external_delivery_enabled: false,
        }),
      },
      tenantId,
    );

    setSecret(result.data.signing_secret);
    event.currentTarget.reset();
    await load();
  }

  return (
    <div>
      <div className="page-heading">
        <div>
          <p className="eyebrow">Outbound integrations</p>
          <h2>Webhooks</h2>
          <p>
            HTTPS only, HMAC signed, private/reserved network destinations
            blocked, and external delivery disabled by default.
          </p>
        </div>
      </div>

      <form className="write-card form-stack" onSubmit={create}>
        <input name="name" placeholder="Order webhook" required />
        <input
          name="endpoint_url"
          type="url"
          placeholder="https://example.com/webhook"
          required
        />
        <input
          name="event_type"
          placeholder="commerce.order.confirmed.v1"
          required
        />
        <button className="primary-button" type="submit">
          Create webhook
        </button>
      </form>

      {secret && (
        <div className="secret-card spaced-card">
          <strong>One-time signing secret</strong>
          <code>{secret}</code>
          <p>Store it securely. It is not returned again.</p>
        </div>
      )}

      <div className="metric-grid spaced-card">
        <article className="metric-card">
          <span>Subscriptions</span>
          <strong>{data.subscriptions.length}</strong>
        </article>
        <article className="metric-card">
          <span>Deliveries</span>
          <strong>{data.deliveries.length}</strong>
        </article>
      </div>
    </div>
  );
}