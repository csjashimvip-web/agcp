"use client";

import { FormEvent, useState } from "react";
import ResourceTable from "@/components/resource-table";
import { useAdminContext } from "@/components/admin-shell";
import { apiFetch } from "@/lib/agcp-api";

export default function ProductsPage() {
  const { tenantId } = useAdminContext();
  const [notice, setNotice] = useState("");
  const [refreshKey, setRefreshKey] = useState(0);

  async function createProduct(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!tenantId) return;

    const form = new FormData(event.currentTarget);

    await apiFetch(
      "/api/v1/admin/products",
      {
        method: "POST",
        body: JSON.stringify({
          sku: String(form.get("sku") || ""),
          name: String(form.get("name") || ""),
          type: "service",
          status: "active",
          currency: String(form.get("currency") || "USD").toUpperCase(),
          price_minor: Number(form.get("price_minor") || 0),
          cost_minor: Number(form.get("cost_minor") || 0),
        }),
      },
      tenantId,
    );

    event.currentTarget.reset();
    setNotice("Product created.");
    setRefreshKey((value) => value + 1);
  }

  return (
    <div>
      <div className="write-card">
        <p className="eyebrow">Catalog write operation</p>
        <h3>Create product/service</h3>
        <form className="inline-form" onSubmit={createProduct}>
          <input name="sku" placeholder="SKU" required />
          <input name="name" placeholder="Service name" required />
          <input name="currency" placeholder="USD" defaultValue="USD" required />
          <input name="price_minor" type="number" min="0" placeholder="Price minor" required />
          <input name="cost_minor" type="number" min="0" placeholder="Cost minor" required />
          <button className="primary-button" type="submit">Create</button>
        </form>
        {notice && <p className="success-note">{notice}</p>}
      </div>

      <ResourceTable
        key={refreshKey}
        title="Products"
        description="Catalog services and commerce pricing for the active tenant."
        endpoint="/api/v1/admin/products"
        columns={[
          { key: "sku", label: "SKU" },
          { key: "name", label: "Name" },
          { key: "type", label: "Type" },
          { key: "status", label: "Status" },
          { key: "currency", label: "Currency" },
          { key: "price_minor", label: "Price" },
          { key: "cost_minor", label: "Cost" },
        ]}
      />
    </div>
  );
}