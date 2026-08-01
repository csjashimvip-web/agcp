"use client";

import { useEffect, useState } from "react";
import { useCustomerContext } from "@/components/customer-shell";
import { apiFetch, ApiEnvelope } from "@/lib/agcp-api";

type Wallet = {
  id: number;
  currency: string;
  available_balance_minor: number;
};

type Order = {
  id: number;
  status: string;
};

export default function AccountPage() {
  const { tenant, tenantOption } = useCustomerContext();
  const [wallets, setWallets] = useState<Wallet[]>([]);
  const [orders, setOrders] = useState<Order[]>([]);

  useEffect(() => {
    if (!tenant) return;

    Promise.all([
      apiFetch<ApiEnvelope<Wallet[]>>(
        "/api/v1/customer/wallets",
        {},
        tenant,
      ),
      apiFetch<ApiEnvelope<Order[]>>(
        "/api/v1/customer/orders",
        {},
        tenant,
      ),
    ]).then(([walletResult, orderResult]) => {
      setWallets(walletResult.data);
      setOrders(orderResult.data);
    });
  }, [tenant]);

  return (
    <div>
      <div className="page-heading">
        <div>
          <p className="eyebrow">Customer account</p>
          <h2>{tenantOption?.name ?? "Overview"}</h2>
          <p>Your wallet and order activity for the selected tenant.</p>
        </div>
      </div>

      <div className="metric-grid">
        <article className="metric-card">
          <span>Wallets</span>
          <strong>{wallets.length}</strong>
        </article>
        <article className="metric-card">
          <span>Orders</span>
          <strong>{orders.length}</strong>
        </article>
        <article className="metric-card wide">
          <span>Available balance (all minor units)</span>
          <strong>
            {wallets
              .reduce(
                (sum, wallet) => sum + wallet.available_balance_minor,
                0,
              )
              .toLocaleString()}
          </strong>
        </article>
      </div>
    </div>
  );
}