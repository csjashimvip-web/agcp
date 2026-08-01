"use client";

import { FormEvent, useEffect, useState } from "react";
import { useAdminContext } from "@/components/admin-shell";
import { apiFetch, ApiEnvelope } from "@/lib/agcp-api";

type Seller = {
  id: number;
  display_name: string;
  user_email: string;
  status: string;
};

type Listing = {
  id: number;
  seller_name: string;
  product_name: string;
  sku: string;
  seller_commission_bps: number;
  status: string;
};

type Accrual = {
  id: number;
  seller_name: string;
  order_number: string;
  amount_minor: number;
  currency: string;
  rate_bps: number;
  status: string;
};

type Product = {
  id: number;
  sku: string;
  name: string;
};

type MarketplaceData = {
  sellers: Seller[];
  listings: Listing[];
  accruals: Accrual[];
};

export default function MarketplacePage() {
  const { tenantId } = useAdminContext();
  const [data, setData] = useState<MarketplaceData>({
    sellers: [],
    listings: [],
    accruals: [],
  });
  const [products, setProducts] = useState<Product[]>([]);
  const [message, setMessage] = useState("");

  async function load() {
    if (!tenantId) return;

    const [marketplace, productResult] = await Promise.all([
      apiFetch<ApiEnvelope<MarketplaceData>>(
        "/api/v1/admin/marketplace",
        {},
        tenantId,
      ),
      apiFetch<ApiEnvelope<Product[]>>(
        "/api/v1/admin/products?limit=100",
        {},
        tenantId,
      ),
    ]);

    setData(marketplace.data);
    setProducts(productResult.data);
  }

  useEffect(() => {
    load().catch(() => undefined);
  }, [tenantId]);

  async function createSeller(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!tenantId) return;

    const form = new FormData(event.currentTarget);

    await apiFetch(
      "/api/v1/admin/marketplace/sellers",
      {
        method: "POST",
        body: JSON.stringify({
          user_email: String(form.get("user_email") || ""),
          display_name: String(form.get("display_name") || ""),
        }),
      },
      tenantId,
    );

    setMessage("Marketplace seller saved.");
    event.currentTarget.reset();
    await load();
  }

  async function createListing(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!tenantId) return;

    const form = new FormData(event.currentTarget);

    await apiFetch(
      "/api/v1/admin/marketplace/listings",
      {
        method: "POST",
        body: JSON.stringify({
          seller_id: Number(form.get("seller_id")),
          product_id: Number(form.get("product_id")),
          seller_commission_bps: Number(
            form.get("seller_commission_bps") || 0,
          ),
        }),
      },
      tenantId,
    );

    setMessage("Marketplace listing saved.");
    event.currentTarget.reset();
    await load();
  }

  return (
    <div>
      <div className="page-heading">
        <div>
          <p className="eyebrow">Marketplace foundation</p>
          <h2>Marketplace</h2>
          <p>
            Map tenant products to sellers and accrue commissions only after
            order completion.
          </p>
        </div>
      </div>

      {message && <div className="info-banner">{message}</div>}

      <div className="config-grid">
        <form className="write-card" onSubmit={createSeller}>
          <h3>Add seller</h3>
          <div className="form-stack">
            <input
              name="user_email"
              type="email"
              placeholder="Tenant member email"
              required
            />
            <input
              name="display_name"
              placeholder="Seller display name"
              required
            />
            <button className="primary-button" type="submit">
              Save seller
            </button>
          </div>
        </form>

        <form className="write-card" onSubmit={createListing}>
          <h3>Create listing</h3>
          <div className="form-stack">
            <select name="seller_id" required defaultValue="">
              <option value="" disabled>
                Seller
              </option>
              {data.sellers.map((seller) => (
                <option key={seller.id} value={seller.id}>
                  {seller.display_name}
                </option>
              ))}
            </select>

            <select name="product_id" required defaultValue="">
              <option value="" disabled>
                Product
              </option>
              {products.map((product) => (
                <option key={product.id} value={product.id}>
                  {product.sku} â€” {product.name}
                </option>
              ))}
            </select>

            <input
              name="seller_commission_bps"
              type="number"
              min="0"
              max="10000"
              placeholder="Commission bps"
              required
            />

            <button className="primary-button" type="submit">
              Save listing
            </button>
          </div>
        </form>
      </div>

      <div className="table-card">
        <div className="table-status">{data.listings.length} listings</div>
        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Product</th>
                <th>Seller</th>
                <th>Commission</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              {data.listings.map((listing) => (
                <tr key={listing.id}>
                  <td>
                    {listing.product_name}
                    <div className="subtle">{listing.sku}</div>
                  </td>
                  <td>{listing.seller_name}</td>
                  <td>{listing.seller_commission_bps} bps</td>
                  <td>{listing.status}</td>
                </tr>
              ))}
              {data.listings.length === 0 && (
                <tr>
                  <td colSpan={4} className="empty-cell">
                    No marketplace listings yet.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      <div className="table-card spaced-card">
        <div className="table-status">
          {data.accruals.length} recent commission accruals
        </div>
        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Seller</th>
                <th>Order</th>
                <th>Amount</th>
                <th>Rate</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              {data.accruals.map((row) => (
                <tr key={row.id}>
                  <td>{row.seller_name}</td>
                  <td>{row.order_number}</td>
                  <td>
                    {row.amount_minor} {row.currency}
                  </td>
                  <td>{row.rate_bps} bps</td>
                  <td>{row.status}</td>
                </tr>
              ))}
              {data.accruals.length === 0 && (
                <tr>
                  <td colSpan={5} className="empty-cell">
                    No commission accruals yet.
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