"use client";

import { useEffect, useState } from "react";
import { useAdminContext } from "@/components/admin-shell";
import { apiFetch, ApiEnvelope } from "@/lib/agcp-api";

type Order = {
  id: number;
  order_number: string;
  customer_name?: string | null;
  customer_email?: string | null;
  status: string;
  currency: string;
  total_minor: number;
  created_at: string;
};

export default function OrdersPage() {
  const { tenantId } = useAdminContext();
  const [rows, setRows] = useState<Order[]>([]);
  const [message, setMessage] = useState("");

  async function load() {
    if (!tenantId) return;

    const result = await apiFetch<ApiEnvelope<Order[]>>(
      "/api/v1/admin/orders",
      {},
      tenantId,
    );

    setRows(result.data);
  }

  useEffect(() => {
    load().catch((reason) =>
      setMessage(reason instanceof Error ? reason.message : "Unable to load."),
    );
  }, [tenantId]);

  async function cancel(order: Order) {
    if (!tenantId) return;

    const reason = window.prompt(
      `Reason for cancelling ${order.order_number}:`,
    );

    if (!reason) return;

    try {
      await apiFetch(
        `/api/v1/admin/orders/${order.id}/cancel`,
        {
          method: "POST",
          body: JSON.stringify({ reason }),
        },
        tenantId,
      );

      setMessage("Order cancelled and eligible wallet funds compensated.");
      await load();
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "Cancellation failed.");
    }
  }

  async function retry(orderId: number) {
    if (!tenantId) return;

    try {
      await apiFetch(
        `/api/v1/admin/orders/${orderId}/retry`,
        { method: "POST" },
        tenantId,
      );

      setMessage("Failed fulfillment items were queued for retry.");
      await load();
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "Retry failed.");
    }
  }

  return (
    <div>
      <div className="page-heading">
        <div>
          <p className="eyebrow">Order operations</p>
          <h2>Orders</h2>
          <p>
            Cancellation is automatically blocked once external supplier
            fulfillment has started.
          </p>
        </div>
      </div>

      {message && <div className="info-banner">{message}</div>}

      <div className="table-card">
        <div className="table-status">{rows.length} orders</div>
        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Status</th>
                <th>Total</th>
                <th>Created</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((order) => (
                <tr key={order.id}>
                  <td>{order.order_number}</td>
                  <td>
                    <strong>{order.customer_name ?? "Customer"}</strong>
                    <div className="subtle">{order.customer_email ?? "â€”"}</div>
                  </td>
                  <td>{order.status}</td>
                  <td>
                    {order.total_minor} {order.currency}
                  </td>
                  <td>{order.created_at}</td>
                  <td>
                    <div className="row-actions">
                      <button
                        className="small-button"
                        disabled={["cancelled", "completed"].includes(order.status)}
                        onClick={() => cancel(order)}
                      >
                        Cancel
                      </button>
                      <button
                        className="small-button"
                        disabled={["cancelled", "completed"].includes(order.status)}
                        onClick={() => retry(order.id)}
                      >
                        Retry failed
                      </button>
                    </div>
                  </td>
                </tr>
              ))}

              {rows.length === 0 && (
                <tr>
                  <td colSpan={6} className="empty-cell">
                    No orders found.
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