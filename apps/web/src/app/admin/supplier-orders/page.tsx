"use client";

import { useEffect, useState } from "react";
import { useAdminContext } from "@/components/admin-shell";
import { apiFetch, ApiEnvelope } from "@/lib/agcp-api";

type SupplierOrder = {
  id: number;
  order_number: string;
  supplier_name: string;
  item_name: string;
  external_order_id?: string | null;
  status: string;
  failure_reason?: string | null;
  updated_at: string;
};

export default function SupplierOrdersPage() {
  const { tenantId } = useAdminContext();
  const [rows, setRows] = useState<SupplierOrder[]>([]);
  const [message, setMessage] = useState("");

  async function load() {
    if (!tenantId) return;

    const result = await apiFetch<ApiEnvelope<SupplierOrder[]>>(
      "/api/v1/admin/supplier-orders",
      {},
      tenantId,
    );

    setRows(result.data);
  }

  useEffect(() => {
    load().catch((error) =>
      setMessage(error instanceof Error ? error.message : "Unable to load."),
    );
  }, [tenantId]);

  async function reconcile(id: number) {
    if (!tenantId) return;

    await apiFetch(
      `/api/v1/admin/supplier-orders/${id}/reconcile`,
      { method: "POST" },
      tenantId,
    );

    setMessage("Reconciliation queued on the supplier worker.");
    await load();
  }

  return (
    <div>
      <div className="page-heading">
        <div>
          <p className="eyebrow">Fulfillment reconciliation</p>
          <h2>Supplier Orders</h2>
          <p>
            Inspect provider submissions and queue manual status reconciliation
            when required.
          </p>
        </div>
      </div>

      {message && <div className="info-banner">{message}</div>}

      <div className="table-card">
        <div className="table-status">{rows.length} supplier orders</div>
        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                <th>AGCP Order</th>
                <th>Item</th>
                <th>Supplier</th>
                <th>External ID</th>
                <th>Status</th>
                <th>Error</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id}>
                  <td>{row.order_number}</td>
                  <td>{row.item_name}</td>
                  <td>{row.supplier_name}</td>
                  <td>{row.external_order_id ?? "â€”"}</td>
                  <td>{row.status}</td>
                  <td>{row.failure_reason ?? "â€”"}</td>
                  <td>
                    <button
                      className="small-button"
                      onClick={() => reconcile(row.id)}
                      disabled={
                        !row.external_order_id ||
                        ["completed", "failed"].includes(row.status)
                      }
                    >
                      Reconcile
                    </button>
                  </td>
                </tr>
              ))}
              {rows.length === 0 && (
                <tr>
                  <td colSpan={7} className="empty-cell">
                    No supplier orders yet.
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