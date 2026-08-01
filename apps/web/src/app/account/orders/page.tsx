"use client";

import { useEffect, useState } from "react";
import { useCustomerContext } from "@/components/customer-shell";
import { apiFetch, ApiEnvelope } from "@/lib/agcp-api";

type Order = {
  id: number;
  order_number: string;
  status: string;
  currency: string;
  total_minor: number;
  created_at: string;
};

export default function CustomerOrdersPage() {
  const { tenant } = useCustomerContext();
  const [rows, setRows] = useState<Order[]>([]);

  useEffect(() => {
    if (!tenant) return;

    apiFetch<ApiEnvelope<Order[]>>(
      "/api/v1/customer/orders",
      {},
      tenant,
    ).then((result) => setRows(result.data));
  }, [tenant]);

  return (
    <div>
      <div className="page-heading">
        <div>
          <p className="eyebrow">Customer commerce</p>
          <h2>My Orders</h2>
          <p>Current order and fulfillment status.</p>
        </div>
      </div>

      <div className="table-card">
        <div className="table-status">{rows.length} orders</div>
        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Order</th>
                <th>Status</th>
                <th>Total</th>
                <th>Created</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((order) => (
                <tr key={order.id}>
                  <td>{order.order_number}</td>
                  <td>{order.status}</td>
                  <td>
                    {order.total_minor} {order.currency}
                  </td>
                  <td>{order.created_at}</td>
                </tr>
              ))}
              {rows.length === 0 && (
                <tr>
                  <td colSpan={4} className="empty-cell">
                    No orders yet.
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