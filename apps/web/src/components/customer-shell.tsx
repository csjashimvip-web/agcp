"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import {
  createContext,
  ReactNode,
  useContext,
  useEffect,
  useMemo,
  useState,
} from "react";
import {
  apiFetch,
  ApiEnvelope,
  logout,
  TenantOption,
} from "@/lib/agcp-api";

type CustomerContextValue = {
  tenant: number | string | null;
  tenantOption: TenantOption | null;
  tenants: TenantOption[];
};

const CustomerContext = createContext<CustomerContextValue>({
  tenant: null,
  tenantOption: null,
  tenants: [],
});

export function useCustomerContext() {
  return useContext(CustomerContext);
}

const nav = [
  ["/account", "Overview"],
  ["/account/orders", "Orders"],
  ["/account/wallet", "Wallet"],
  ["/account/notifications", "Notifications"],
];

function requestedTenantFromBrowser(): string | null {
  if (typeof window === "undefined") {
    return null;
  }

  return new URLSearchParams(window.location.search).get("tenant");
}

export default function CustomerShell({
  children,
}: {
  children: ReactNode;
}) {
  const pathname = usePathname();
  const router = useRouter();
  const [tenants, setTenants] = useState<TenantOption[]>([]);
  const [tenantId, setTenantId] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let active = true;

    async function initialize() {
      try {
        const result = await apiFetch<ApiEnvelope<TenantOption[]>>(
          "/api/v1/tenants",
        );

        if (!active) {
          return;
        }

        setTenants(result.data);

        const requested = requestedTenantFromBrowser();

        const selected =
          result.data.find(
            (tenant) =>
              String(tenant.id) === requested ||
              tenant.slug === requested,
          ) ??
          result.data[0] ??
          null;

        setTenantId(selected?.id ?? null);
      } catch (error) {
        if (
          error instanceof Error &&
          error.message === "UNAUTHENTICATED"
        ) {
          router.replace(
            `/login?next=${encodeURIComponent(pathname)}`,
          );

          return;
        }

        console.error(error);
      } finally {
        if (active) {
          setLoading(false);
        }
      }
    }

    initialize();

    return () => {
      active = false;
    };
  }, [pathname, router]);

  const selectedTenant =
    tenants.find((tenant) => tenant.id === tenantId) ?? null;

  const context = useMemo(
    () => ({
      tenant: tenantId,
      tenantOption: selectedTenant,
      tenants,
    }),
    [tenantId, selectedTenant, tenants],
  );

  async function signOut() {
    try {
      await logout();
    } finally {
      router.replace("/login");
      router.refresh();
    }
  }

  return (
    <CustomerContext.Provider value={context}>
      <div className="customer-app">
        <header className="customer-header">
          <div>
            <Link href="/" className="brand">
              <span className="brand-mark">A</span>
              <div>
                <strong>AGCP</strong>
                <small>Customer Portal</small>
              </div>
            </Link>
          </div>

          <div className="customer-header-actions">
            <select
              value={tenantId ?? ""}
              disabled={loading || tenants.length === 0}
              onChange={(event) =>
                setTenantId(Number(event.target.value))
              }
            >
              {tenants.length === 0 && (
                <option value="">No tenant</option>
              )}

              {tenants.map((tenant) => (
                <option key={tenant.id} value={tenant.id}>
                  {tenant.name}
                </option>
              ))}
            </select>

            {selectedTenant && (
              <Link
                className="ghost-link"
                href={`/shop/${selectedTenant.slug}`}
              >
                Store
              </Link>
            )}

            <button className="ghost-button" onClick={signOut}>
              Sign out
            </button>
          </div>
        </header>

        <div className="customer-body">
          <nav className="customer-nav">
            {nav.map(([href, label]) => (
              <Link
                key={href}
                href={href}
                className={
                  pathname === href
                    ? "nav-link active"
                    : "nav-link"
                }
              >
                {label}
              </Link>
            ))}
          </nav>

          <main className="customer-content">{children}</main>
        </div>
      </div>
    </CustomerContext.Provider>
  );
}