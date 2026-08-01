"use client";

import { useEffect, useState } from "react";
import { useAdminContext } from "@/components/admin-shell";
import { apiFetch, ApiEnvelope } from "@/lib/agcp-api";

type Analytics = {
  orders_total: number;
  gmv_minor: number;
  completed_gmv_minor: number;
  discount_minor: number;
  tax_minor: number;
  coupon_redemptions: number;
  commission_accrued_minor: number;
  marketplace_sellers: number;
  marketplace_listings: number;
  tier_members: number;
  order_status_breakdown: Array<{
    status: string;
    total: number;
  }>;
  top_products: Array<{
    sku: string;
    name: string;
    quantity: number;
    gross_minor: number;
  }>;
  generated_at: string;
};

export default function AnalyticsPage() {
  const { tenantId } = useAdminContext();
  const [data, setData] = useState<Analytics | null>(null);

  async function load() {
    if (!tenantId) return;

    const result = await apiFetch<ApiEnvelope<Analytics>>(
      "/api/v1/admin/analytics",
      {},
      tenantId,
    );

    setData(result.data);
  }

  useEffect(() => {
    load().catch(() => undefined);
  }, [tenantId]);

  const metrics = [
    ["Orders", data?.orders_total ?? 0],
    ["GMV", data?.gmv_minor ?? 0],
    ["Completed GMV", data?.completed_gmv_minor ?? 0],
    ["Discounts", data?.discount_minor ?? 0],
    ["Tax", data?.tax_minor ?? 0],
    ["Coupon uses", data?.coupon_redemptions ?? 0],
    ["Commission accrued", data?.commission_accrued_minor ?? 0],
    ["Marketplace sellers", data?.marketplace_sellers ?? 0],
    ["Marketplace listings", data?.marketplace_listings ?? 0],
    ["Tier members", data?.tier_members ?? 0],
  ];

  return (
    <div>
      <div className="page-heading">
        <div>
          <p className="eyebrow">Reporting analytics</p>
          <h2>Commerce Analytics</h2>
          <p>
            Transactional reporting derived from orders, pricing, coupons and
            marketplace accruals.
          </p>
        </div>

        <button className="small-button" onClick={() => load()}>
          Refresh
        </button>
      </div>

      <div className="metric-grid operations-grid">
        {metrics.map(([label, value]) => (
          <article className="metric-card" key={String(label)}>
            <span>{label}</span>
            <strong>{Number(value).toLocaleString()}</strong>
          </article>
        ))}
      </div>

      <div className="table-card spaced-card">
        <div className="table-status">Top products</div>
        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                <th>SKU</th>
                <th>Product</th>
                <th>Quantity</th>
                <th>Gross minor</th>
              </tr>
            </thead>
            <tbody>
              {data?.top_products.map((row) => (
                <tr key={row.sku}>
                  <td>{row.sku}</td>
                  <td>{row.name}</td>
                  <td>{row.quantity}</td>
                  <td>{row.gross_minor}</td>
                </tr>
              ))}
              {!data?.top_products.length && (
                <tr>
                  <td colSpan={4} className="empty-cell">
                    No sales analytics yet.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      <p className="subtle spaced-card">
        Generated: {data?.generated_at ?? "â€”"}
      </p>
    </div>
  );
}