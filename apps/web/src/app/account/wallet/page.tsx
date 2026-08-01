"use client";

import { FormEvent, useEffect, useState } from "react";
import { useCustomerContext } from "@/components/customer-shell";
import { apiFetch, ApiEnvelope } from "@/lib/agcp-api";

type Wallet = {
  id: number;
  currency: string;
  status: string;
  available_balance_minor: number;
  held_balance_minor: number;
};

type Deposit = {
  id: number;
  amount_minor: number;
  currency: string;
  method: string;
  status: string;
  created_at: string;
};

export default function CustomerWalletPage() {
  const { tenant } = useCustomerContext();
  const [wallets, setWallets] = useState<Wallet[]>([]);
  const [deposits, setDeposits] = useState<Deposit[]>([]);
  const [message, setMessage] = useState("");

  async function load() {
    if (!tenant) return;

    const [walletResult, depositResult] = await Promise.all([
      apiFetch<ApiEnvelope<Wallet[]>>(
        "/api/v1/customer/wallets",
        {},
        tenant,
      ),
      apiFetch<ApiEnvelope<Deposit[]>>(
        "/api/v1/customer/deposits",
        {},
        tenant,
      ),
    ]);

    setWallets(walletResult.data);
    setDeposits(depositResult.data);
  }

  useEffect(() => {
    load().catch(() => undefined);
  }, [tenant]);

  async function requestDeposit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!tenant) return;

    const form = new FormData(event.currentTarget);

    await apiFetch(
      "/api/v1/customer/deposits",
      {
        method: "POST",
        body: JSON.stringify({
          wallet_id: Number(form.get("wallet_id")),
          amount_minor: Number(form.get("amount_minor")),
          method: String(form.get("method") || "manual"),
        }),
      },
      tenant,
    );

    setMessage("Deposit request submitted for review.");
    event.currentTarget.reset();
    await load();
  }

  return (
    <div>
      <div className="page-heading">
        <div>
          <p className="eyebrow">Wallet</p>
          <h2>Balance & Deposits</h2>
          <p>Deposit requests do not credit your wallet until approved.</p>
        </div>
      </div>

      {message && <div className="info-banner">{message}</div>}

      <div className="wallet-grid">
        {wallets.map((wallet) => (
          <article className="metric-card" key={wallet.id}>
            <span>{wallet.currency} wallet</span>
            <strong>{wallet.available_balance_minor.toLocaleString()}</strong>
            <small>Held: {wallet.held_balance_minor.toLocaleString()}</small>
          </article>
        ))}
      </div>

      {wallets.length > 0 && (
        <div className="write-card">
          <h3>Request deposit</h3>
          <form className="inline-form" onSubmit={requestDeposit}>
            <select name="wallet_id" required defaultValue="">
              <option value="" disabled>
                Choose wallet
              </option>
              {wallets.map((wallet) => (
                <option key={wallet.id} value={wallet.id}>
                  {wallet.currency}
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
            <select name="method" defaultValue="manual">
              <option value="manual">Manual</option>
              <option value="bank_transfer">Bank transfer</option>
            </select>
            <button className="primary-button" type="submit">
              Submit request
            </button>
          </form>
        </div>
      )}

      <div className="table-card">
        <div className="table-status">{deposits.length} deposit requests</div>
        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Amount</th>
                <th>Method</th>
                <th>Status</th>
                <th>Created</th>
              </tr>
            </thead>
            <tbody>
              {deposits.map((deposit) => (
                <tr key={deposit.id}>
                  <td>
                    {deposit.amount_minor} {deposit.currency}
                  </td>
                  <td>{deposit.method}</td>
                  <td>{deposit.status}</td>
                  <td>{deposit.created_at}</td>
                </tr>
              ))}
              {deposits.length === 0 && (
                <tr>
                  <td colSpan={4} className="empty-cell">
                    No deposit requests.
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