"use client";

import { FormEvent, useEffect, useState } from "react";
import { useAdminContext } from "@/components/admin-shell";
import { apiFetch, ApiEnvelope } from "@/lib/agcp-api";

type Plan = {
  id: number;
  name: string;
  billing_period: string;
  price_minor: number;
  currency: string;
};

type Data = {
  plans: Plan[];
  subscriptions: Array<{
    id: number;
    plan_name?: string | null;
    mode: string;
    status: string;
  }>;
};

export default function SaaSPage() {
  const { tenantId } = useAdminContext();
  const [data, setData] = useState<Data>({
    plans: [],
    subscriptions: [],
  });

  async function load() {
    if (!tenantId) return;
    const result = await apiFetch<ApiEnvelope<Data>>(
      "/api/v1/admin/saas",
      {},
      tenantId,
    );
    setData(result.data);
  }

  useEffect(() => {
    load().catch(() => undefined);
  }, [tenantId]);

  async function createPlan(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!tenantId) return;
    const form = new FormData(event.currentTarget);

    await apiFetch(
      "/api/v1/admin/saas/plans",
      {
        method: "POST",
        body: JSON.stringify({
          name: String(form.get("name") || ""),
          billing_period: String(form.get("billing_period") || "monthly"),
          price_minor: Number(form.get("price_minor") || 0),
          currency: String(form.get("currency") || "USD"),
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
          <p className="eyebrow">Cloud & self-hosted</p>
          <h2>SaaS Plans</h2>
          <p>Plan catalog and tenant subscription foundation.</p>
        </div>
      </div>

      <form className="write-card form-stack" onSubmit={createPlan}>
        <input name="name" placeholder="Enterprise" required />
        <select name="billing_period" defaultValue="monthly">
          <option value="monthly">Monthly</option>
          <option value="yearly">Yearly</option>
          <option value="one_time">One time</option>
        </select>
        <input
          name="price_minor"
          type="number"
          min="0"
          placeholder="Price minor units"
          required
        />
        <input name="currency" defaultValue="USD" maxLength={3} required />
        <button className="primary-button" type="submit">
          Create plan
        </button>
      </form>

      <div className="metric-grid spaced-card">
        <article className="metric-card">
          <span>Plans</span>
          <strong>{data.plans.length}</strong>
        </article>
        <article className="metric-card">
          <span>Subscription records</span>
          <strong>{data.subscriptions.length}</strong>
        </article>
      </div>
    </div>
  );
}