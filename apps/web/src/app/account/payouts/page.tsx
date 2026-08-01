"use client";

import { FormEvent, useEffect, useState } from "react";
import { useCustomerContext } from "@/components/customer-shell";
import { apiFetch, ApiEnvelope } from "@/lib/agcp-api";

type Wallet = {
  id: number;
  currency: string;
  available_balance_minor: number;
};

type Payout = {
  id: number;
  amount_minor: number;
  currency: string;
  destination_label: string;
  status: string;
};

export default function CustomerPayoutsPage() {
  const { tenant } = useCustomerContext();
  const [wallets, setWallets] = useState<Wallet[]>([]);
  const [rows, setRows] = useState<Payout[]>([]);

  async function load() {
    if (!tenant) return;

    const [walletResult, payoutResult] = await Promise.all([
      apiFetch<ApiEnvelope<Wallet[]>>(
        "/api/v1/customer/wallets",
        {},
        tenant,
      ),
      apiFetch<ApiEnvelope<Payout[]>>(
        "/api/v1/customer/payouts",
        {},
        tenant,
      ),
    ]);

    setWallets(walletResult.data);
    setRows(payoutResult.data);
  }

  useEffect(() => {
    load().catch(() => undefined);
  }, [tenant]);

  async function create(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!tenant) return;

    const form = new FormData(event.currentTarget);

    await apiFetch(
      "/api/v1/customer/payouts",
      {
        method: "POST",
        body: JSON.stringify({
          wallet_id: Number(form.get("wallet_id")),
          amount_minor: Number(form.get("amount_minor")),
          method: "manual_bank",
          destination_label: String(
            form.get("destination_label") || "",
          ),
          destination: {
            reference: String(form.get("reference") || ""),
          },
        }),
      },
      tenant,
    );

    event.currentTarget.reset();
    await load();
  }

  return (
    <div>
      <div className="page-heading">
        <div>
          <p className="eyebrow">Wallet operations</p>
          <h2>Payouts</h2>
          <p>Requested funds are held until review and payment confirmation.</p>
        </div>
      </div>

      <form className="write-card form-stack" onSubmit={create}>
        <select name="wallet_id" defaultValue="" required>
          <option value="" disabled>
            Select wallet
          </option>
          {wallets.map((wallet) => (
            <option key={wallet.id} value={wallet.id}>
              {wallet.currency} Â· {wallet.available_balance_minor}
            </option>
          ))}
        </select>
        <input
          name="amount_minor"
          type="number"
          min="1"
          placeholder="Amount in minor units"
          required
        />
        <input
          name="destination_label"
          placeholder="Destination label"
          required
        />
        <input
          name="reference"
          placeholder="Destination reference"
          required
        />
        <button className="primary-button" type="submit">
          Request payout
        </button>
      </form>

      <div className="table-card spaced-card">
        <div className="table-status">{rows.length} payout requests</div>
        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Amount</th>
                <th>Destination</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id}>
                  <td>
                    {row.amount_minor} {row.currency}
                  </td>
                  <td>{row.destination_label}</td>
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