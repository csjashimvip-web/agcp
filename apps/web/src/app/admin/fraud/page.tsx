"use client";

import { FormEvent, useEffect, useState } from "react";
import { useAdminContext } from "@/components/admin-shell";
import { apiFetch, ApiEnvelope } from "@/lib/agcp-api";

type FraudData = {
  rules: Array<{
    id: number;
    name: string;
    metric: string;
    threshold_value: number;
    action: string;
    status: string;
  }>;
  assessments: Array<{
    id: number;
    user_name: string;
    risk_score: number;
    decision: string;
    quote_total_minor: number;
  }>;
};

export default function FraudPage() {
  const { tenantId } = useAdminContext();
  const [data, setData] = useState<FraudData>({
    rules: [],
    assessments: [],
  });

  async function load() {
    if (!tenantId) return;
    const result = await apiFetch<ApiEnvelope<FraudData>>(
      "/api/v1/admin/fraud",
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
      "/api/v1/admin/fraud/rules",
      {
        method: "POST",
        body: JSON.stringify({
          name: String(form.get("name") || ""),
          code: String(form.get("code") || ""),
          metric: String(form.get("metric") || "order_total_minor"),
          threshold_value: Number(form.get("threshold_value") || 0),
          risk_points: Number(form.get("risk_points") || 25),
          action: String(form.get("action") || "review"),
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
          <p className="eyebrow">Risk controls</p>
          <h2>Fraud Guard</h2>
          <p>Rules execute before checkout mutates wallet or order state.</p>
        </div>
      </div>

      <form className="write-card form-stack" onSubmit={create}>
        <input name="name" placeholder="Rule name" required />
        <input name="code" placeholder="RISK-CODE" required />
        <select name="metric" defaultValue="order_total_minor">
          <option value="order_total_minor">Order total</option>
          <option value="orders_10m">Orders in 10 minutes</option>
          <option value="cancelled_orders_24h">
            Cancelled orders in 24 hours
          </option>
        </select>
        <input
          name="threshold_value"
          type="number"
          min="0"
          placeholder="Threshold"
          required
        />
        <input
          name="risk_points"
          type="number"
          min="1"
          max="100"
          defaultValue="25"
          required
        />
        <select name="action" defaultValue="review">
          <option value="review">Manual review</option>
          <option value="block">Block</option>
        </select>
        <button className="primary-button" type="submit">
          Create rule
        </button>
      </form>

      <div className="metric-grid spaced-card">
        <article className="metric-card">
          <span>Rules</span>
          <strong>{data.rules.length}</strong>
        </article>
        <article className="metric-card">
          <span>Assessments</span>
          <strong>{data.assessments.length}</strong>
        </article>
      </div>
    </div>
  );
}