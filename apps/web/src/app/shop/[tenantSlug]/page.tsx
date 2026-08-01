"use client";

import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { apiFetch, ApiEnvelope } from "@/lib/agcp-api";

type Product = {
  id: number;
  sku: string;
  name: string;
  type: string;
  description?: string | null;
  currency: string;
  price_minor: number;
};

type Catalog = {
  tenant: {
    id: number;
    name: string;
    slug: string;
    currency: string;
  };
  products: Product[];
};

type Wallet = {
  id: number;
  currency: string;
  available_balance_minor: number;
};

export default function StorefrontPage() {
  const params = useParams<{ tenantSlug: string }>();
  const router = useRouter();
  const tenantSlug = params.tenantSlug;
  const [catalog, setCatalog] = useState<Catalog | null>(null);
  const [message, setMessage] = useState("");
  const [busy, setBusy] = useState<number | null>(null);

  useEffect(() => {
    apiFetch<ApiEnvelope<Catalog>>(
      `/api/v1/storefront/${encodeURIComponent(tenantSlug)}/catalog`,
    )
      .then((result) => setCatalog(result.data))
      .catch((error) =>
        setMessage(error instanceof Error ? error.message : "Unable to load."),
      );
  }, [tenantSlug]);

  async function buy(product: Product) {
    setBusy(product.id);
    setMessage("");

    try {
      const wallets = await apiFetch<ApiEnvelope<Wallet[]>>(
        "/api/v1/customer/wallets",
        {},
        tenantSlug,
      );

      const wallet = wallets.data.find(
        (candidate) => candidate.currency === product.currency,
      );

      if (!wallet) {
        router.push(
          `/account/wallet?tenant=${encodeURIComponent(tenantSlug)}`,
        );
        return;
      }

      const result = await apiFetch<
        ApiEnvelope<{ id: number; order_number: string }>
      >(
        "/api/v1/checkout",
        {
          method: "POST",
          body: JSON.stringify({
            wallet_id: wallet.id,
            idempotency_key: `store-${product.id}-${crypto.randomUUID()}`,
            items: [
              {
                product_id: product.id,
                quantity: 1,
              },
            ],
          }),
        },
        tenantSlug,
      );

      setMessage(`Order ${result.data.order_number} created successfully.`);
    } catch (error) {
      if (error instanceof Error && error.message === "UNAUTHENTICATED") {
        router.push(
          `/login?next=${encodeURIComponent(`/shop/${tenantSlug}`)}`,
        );
        return;
      }

      setMessage(error instanceof Error ? error.message : "Checkout failed.");
    } finally {
      setBusy(null);
    }
  }

  return (
    <main className="store-page">
      <header className="store-header">
        <div>
          <p className="eyebrow">AGCP Storefront</p>
          <h1>{catalog?.tenant.name ?? "Loading storeâ€¦"}</h1>
        </div>

        <div className="store-actions">
          <Link href={`/account?tenant=${tenantSlug}`} className="ghost-link">
            My account
          </Link>
          <Link href="/login" className="ghost-link">
            Sign in
          </Link>
        </div>
      </header>

      {message && <div className="info-banner">{message}</div>}

      <section className="store-grid">
        {catalog?.products.map((product) => (
          <article className="product-card" key={product.id}>
            <span className="product-sku">{product.sku}</span>
            <h2>{product.name}</h2>
            <p>{product.description || "Digital commerce service."}</p>
            <div className="product-footer">
              <strong>
                {product.price_minor.toLocaleString()} {product.currency}
              </strong>
              <button
                className="primary-button"
                disabled={busy === product.id}
                onClick={() => buy(product)}
              >
                {busy === product.id ? "Processingâ€¦" : "Buy now"}
              </button>
            </div>
          </article>
        ))}

        {catalog && catalog.products.length === 0 && (
          <div className="empty-state">No active products are available.</div>
        )}
      </section>
    </main>
  );
}