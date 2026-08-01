"use client";

import { FormEvent, useEffect, useState } from "react";
import { useAdminContext } from "@/components/admin-shell";
import { apiFetch, ApiEnvelope } from "@/lib/agcp-api";

type License = {
  id: number;
  public_id: string;
  edition: string;
  status: string;
  bound_domain?: string | null;
};

type Data = {
  entitlements: Record<string, unknown>;
  licenses: License[];
};

export default function LicensingPage() {
  const { tenantId } = useAdminContext();
  const [data, setData] = useState<Data>({
    entitlements: {},
    licenses: [],
  });
  const [token, setToken] = useState("");

  async function load() {
    if (!tenantId) return;

    const result = await apiFetch<ApiEnvelope<Data>>(
      "/api/v1/admin/licensing",
      {},
      tenantId,
    );

    setData(result.data);
  }

  useEffect(() => {
    load().catch(() => undefined);
  }, [tenantId]);

  async function issue(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!tenantId) return;

    const form = new FormData(event.currentTarget);

    const result = await apiFetch<
      ApiEnvelope<{ token: string }>
    >(
      "/api/v1/admin/licensing/licenses",
      {
        method: "POST",
        body: JSON.stringify({
          edition: "enterprise-self-hosted",
          bound_domain: String(form.get("bound_domain") || "") || null,
        }),
      },
      tenantId,
    );

    setToken(result.data.token);
    event.currentTarget.reset();
    await load();
  }

  return (
    <div>
      <div className="page-heading">
        <div>
          <p className="eyebrow">Enterprise self-hosted</p>
          <h2>Licensing & Entitlements</h2>
          <p>
            Hashed one-time license secrets and tenant entitlement snapshots.
          </p>
        </div>
      </div>

      <form className="write-card form-stack" onSubmit={issue}>
        <input name="bound_domain" placeholder="Optional bound domain" />
        <button className="primary-button" type="submit">
          Issue self-hosted license
        </button>
      </form>

      {token && (
        <div className="secret-card spaced-card">
          <strong>One-time license token</strong>
          <code>{token}</code>
          <p>Store it securely. AGCP keeps only the secret hash.</p>
        </div>
      )}

      <div className="metric-grid spaced-card">
        <article className="metric-card">
          <span>Licenses</span>
          <strong>{data.licenses.length}</strong>
        </article>
        <article className="metric-card">
          <span>Resolved entitlements</span>
          <strong>{Object.keys(data.entitlements).length}</strong>
        </article>
      </div>
    </div>
  );
}