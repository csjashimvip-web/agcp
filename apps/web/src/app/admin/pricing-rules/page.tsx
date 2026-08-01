"use client";

import { FormEvent, useEffect, useState } from "react";
import { useAdminContext } from "@/components/admin-shell";
import { apiFetch, ApiEnvelope } from "@/lib/agcp-api";

type Rule = {
  id: number;
  name: string;
  code: string;
  effect: string;
  value_type: string;
  amount_minor?: number | null;
  rate_bps?: number | null;
  status: string;
};

export default function PricingRulesPage() {
  const { tenantId } = useAdminContext();
  const [rows, setRows] = useState<Rule[]>([]);

  async function load() {
    if (!tenantId) return;

    const result = await apiFetch<ApiEnvelope<Rule[]>>(
      "/api/v1/admin/pricing-rules",
      {},
      tenantId,
    );

    setRows(result.data);
  }

  useEffect(() => {
    load().catch(() => undefined);
  }, [tenantId]);

  async function create(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!tenantId) return;

    const form = new FormData(event.currentTarget);
    const valueType = String(form.get("value_type") || "percent");

    await apiFetch(
      "/api/v1/admin/pricing-rules",
      {
        method: "POST",
        body: JSON.stringify({
          name: String(form.get("name") || ""),
          code: String(form.get("code") || ""),
          effect: String(form.get("effect") || "discount"),
          value_type: valueType,
          amount_minor:
            valueType === "fixed"
              ? Number(form.get("value") || 0)
              : null,
          rate_bps:
            valueType === "percent"
              ? Number(form.get("value") || 0)
              : null,
          min_subtotal_minor: 0,
          priority: 100,
          stackable: true,
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
          <p className="eyebrow">Advanced pricing</p>
          <h2>Pricing Rules</h2>
          <p>Prioritized fixed or basis-point discounts and surcharges.</p>
        </div>
      </div>

      <form className="write-card form-stack" onSubmit={create}>
        <input name="name" placeholder="Rule name" required />
        <input name="code" placeholder="RULE-CODE" required />
        <select name="effect" defaultValue="discount">
          <option value="discount">Discount</option>
          <option value="surcharge">Surcharge</option>
        </select>
        <select name="value_type" defaultValue="percent">
          <option value="percent">Percent (basis points)</option>
          <option value="fixed">Fixed minor units</option>
        </select>
        <input name="value" type="number" min="0" required />
        <button className="primary-button" type="submit">
          Create rule
        </button>
      </form>

      <div className="table-card spaced-card">
        <div className="table-status">{rows.length} pricing rules</div>
        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Rule</th>
                <th>Effect</th>
                <th>Value</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id}>
                  <td>
                    {row.name}
                    <div className="subtle">{row.code}</div>
                  </td>
                  <td>{row.effect}</td>
                  <td>
                    {row.value_type === "percent"
                      ? `${row.rate_bps ?? 0} bps`
                      : `${row.amount_minor ?? 0} minor`}
                  </td>
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