"use client";

import { FormEvent, useEffect, useState } from "react";
import { useAdminContext } from "@/components/admin-shell";
import { apiFetch, ApiEnvelope } from "@/lib/agcp-api";

type Tier = {
  id: number;
  name: string;
  slug: string;
  default_discount_bps: number;
  priority: number;
};

type Membership = {
  id: number;
  user_name: string;
  user_email: string;
  tier_name: string;
  status: string;
};

type Coupon = {
  id: number;
  code: string;
  name: string;
  type: string;
  amount_minor?: number | null;
  rate_bps?: number | null;
  status: string;
};

type TaxRule = {
  id: number;
  name: string;
  rate_bps: number;
  priority: number;
  status: string;
};

type PricingData = {
  tiers: Tier[];
  tier_memberships: Membership[];
  coupons: Coupon[];
  tax_rules: TaxRule[];
};

export default function PricingPage() {
  const { tenantId } = useAdminContext();
  const [data, setData] = useState<PricingData>({
    tiers: [],
    tier_memberships: [],
    coupons: [],
    tax_rules: [],
  });
  const [message, setMessage] = useState("");

  async function load() {
    if (!tenantId) return;

    const result = await apiFetch<ApiEnvelope<PricingData>>(
      "/api/v1/admin/pricing",
      {},
      tenantId,
    );

    setData(result.data);
  }

  useEffect(() => {
    load().catch(() => undefined);
  }, [tenantId]);

  async function post(path: string, payload: Record<string, unknown>) {
    if (!tenantId) return;

    await apiFetch(
      path,
      {
        method: "POST",
        body: JSON.stringify(payload),
      },
      tenantId,
    );

    setMessage("Pricing configuration saved.");
    await load();
  }

  async function createTier(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);

    await post("/api/v1/admin/pricing/tiers", {
      name: String(form.get("name") || ""),
      default_discount_bps: Number(form.get("default_discount_bps") || 0),
      priority: Number(form.get("priority") || 100),
    });

    event.currentTarget.reset();
  }

  async function assignTier(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);

    await post("/api/v1/admin/pricing/tiers/assign", {
      user_email: String(form.get("user_email") || ""),
      tier_id: Number(form.get("tier_id")),
    });

    event.currentTarget.reset();
  }

  async function createCoupon(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const type = String(form.get("type") || "percent");

    await post("/api/v1/admin/pricing/coupons", {
      code: String(form.get("code") || ""),
      name: String(form.get("name") || ""),
      type,
      amount_minor:
        type === "fixed" ? Number(form.get("value") || 0) : null,
      rate_bps:
        type === "percent" ? Number(form.get("value") || 0) : null,
      min_subtotal_minor: 0,
    });

    event.currentTarget.reset();
  }

  async function createTax(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);

    await post("/api/v1/admin/pricing/tax-rules", {
      name: String(form.get("name") || ""),
      rate_bps: Number(form.get("rate_bps") || 0),
      priority: Number(form.get("priority") || 100),
    });

    event.currentTarget.reset();
  }

  return (
    <div>
      <div className="page-heading">
        <div>
          <p className="eyebrow">Commerce pricing</p>
          <h2>Pricing Engine</h2>
          <p>
            Reseller tiers, coupons and tax rules are evaluated inside the
            checkout transaction.
          </p>
        </div>
      </div>

      {message && <div className="info-banner">{message}</div>}

      <div className="config-grid">
        <form className="write-card" onSubmit={createTier}>
          <h3>Create reseller tier</h3>
          <div className="form-stack">
            <input name="name" placeholder="Gold Reseller" required />
            <input
              name="default_discount_bps"
              type="number"
              min="0"
              max="10000"
              placeholder="Discount bps (1000 = 10%)"
              required
            />
            <input
              name="priority"
              type="number"
              min="1"
              defaultValue="100"
              required
            />
            <button className="primary-button" type="submit">
              Create tier
            </button>
          </div>
        </form>

        <form className="write-card" onSubmit={assignTier}>
          <h3>Assign tier</h3>
          <div className="form-stack">
            <input
              name="user_email"
              type="email"
              placeholder="Tenant member email"
              required
            />
            <select name="tier_id" required defaultValue="">
              <option value="" disabled>
                Select tier
              </option>
              {data.tiers.map((tier) => (
                <option key={tier.id} value={tier.id}>
                  {tier.name}
                </option>
              ))}
            </select>
            <button className="primary-button" type="submit">
              Assign
            </button>
          </div>
        </form>

        <form className="write-card" onSubmit={createCoupon}>
          <h3>Create coupon</h3>
          <div className="form-stack">
            <input name="code" placeholder="SAVE10" required />
            <input name="name" placeholder="Launch discount" required />
            <select name="type" defaultValue="percent">
              <option value="percent">Percent (bps)</option>
              <option value="fixed">Fixed minor units</option>
            </select>
            <input
              name="value"
              type="number"
              min="0"
              placeholder="Value"
              required
            />
            <button className="primary-button" type="submit">
              Create coupon
            </button>
          </div>
        </form>

        <form className="write-card" onSubmit={createTax}>
          <h3>Create tax rule</h3>
          <div className="form-stack">
            <input name="name" placeholder="VAT" required />
            <input
              name="rate_bps"
              type="number"
              min="0"
              max="10000"
              placeholder="Tax bps"
              required
            />
            <input
              name="priority"
              type="number"
              min="1"
              defaultValue="100"
              required
            />
            <button className="primary-button" type="submit">
              Create tax rule
            </button>
          </div>
        </form>
      </div>

      <div className="table-card">
        <div className="table-status">
          {data.tiers.length} tiers Â· {data.coupons.length} coupons Â·{" "}
          {data.tax_rules.length} tax rules
        </div>
        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Tier</th>
                <th>Default discount</th>
                <th>Priority</th>
              </tr>
            </thead>
            <tbody>
              {data.tiers.map((tier) => (
                <tr key={tier.id}>
                  <td>{tier.name}</td>
                  <td>{tier.default_discount_bps} bps</td>
                  <td>{tier.priority}</td>
                </tr>
              ))}
              {data.tiers.length === 0 && (
                <tr>
                  <td colSpan={3} className="empty-cell">
                    No reseller tiers yet.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}