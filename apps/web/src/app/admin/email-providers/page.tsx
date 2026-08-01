"use client";

import { FormEvent, useEffect, useState } from "react";
import { useAdminContext } from "@/components/admin-shell";
import { apiFetch, ApiEnvelope } from "@/lib/agcp-api";

type Data = {
  providers: Array<{
    id: number;
    name: string;
    driver: string;
    external_delivery_enabled: boolean;
    status: string;
  }>;
  attempts: Array<{
    id: number;
    recipient: string;
    status: string;
    subject?: string | null;
  }>;
};

export default function EmailProvidersPage() {
  const { tenantId } = useAdminContext();
  const [data, setData] = useState<Data>({
    providers: [],
    attempts: [],
  });

  async function load() {
    if (!tenantId) return;

    const result = await apiFetch<ApiEnvelope<Data>>(
      "/api/v1/admin/email-providers",
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
      "/api/v1/admin/email-providers",
      {
        method: "POST",
        body: JSON.stringify({
          name: String(form.get("name") || ""),
          driver: String(form.get("driver") || "null"),
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
          <p className="eyebrow">Notification delivery</p>
          <h2>Email Providers</h2>
          <p>External mail remains disabled until explicitly enabled.</p>
        </div>
      </div>

      <form className="write-card form-stack" onSubmit={create}>
        <input name="name" placeholder="Primary Mail" required />
        <select name="driver" defaultValue="null">
          <option value="null">Null / no external delivery</option>
          <option value="laravel_mail">Laravel Mail</option>
        </select>
        <button className="primary-button" type="submit">
          Add provider
        </button>
      </form>

      <div className="metric-grid spaced-card">
        <article className="metric-card">
          <span>Providers</span>
          <strong>{data.providers.length}</strong>
        </article>
        <article className="metric-card">
          <span>Attempts</span>
          <strong>{data.attempts.length}</strong>
        </article>
      </div>
    </div>
  );
}