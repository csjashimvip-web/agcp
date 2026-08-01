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

type AdminContextValue = {
  tenantId: number | null;
  tenants: TenantOption[];
  loading: boolean;
};

const AdminContext = createContext<AdminContextValue>({
  tenantId: null,
  tenants: [],
  loading: true,
});

export function useAdminContext() {
  return useContext(AdminContext);
}

const nav = [
  ["/admin", "Overview"],
  ["/admin/products", "Products"],
  ["/admin/orders", "Orders"],
  ["/admin/wallets", "Wallets"],
  ["/admin/deposits", "Deposits"],
  ["/admin/suppliers", "Suppliers"],
  ["/admin/routing", "Routing"],
  ["/admin/supplier-orders", "Supplier Orders"],
  ["/admin/operations", "Operations"],
  ["/admin/reseller-api", "Reseller API"],
];

export default function AdminShell({ children }: { children: ReactNode }) {
  const pathname = usePathname();
  const router = useRouter();
  const [tenants, setTenants] = useState<TenantOption[]>([]);
  const [tenantId, setTenantId] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);
  const [userName, setUserName] = useState("Administrator");

  useEffect(() => {
    let active = true;

    async function initialize() {
      try {
        const me = await apiFetch<
          ApiEnvelope<{ id: number; name: string; email: string }>
        >("/api/v1/auth/me");

        if (!active) return;
        setUserName(me.data.name || me.data.email);

        const result = await apiFetch<ApiEnvelope<TenantOption[]>>(
          "/api/v1/tenants",
        );

        if (!active) return;

        setTenants(result.data);

        const stored = Number(localStorage.getItem("agcp_tenant_id"));
        const selected =
          result.data.find((tenant) => tenant.id === stored)?.id ??
          result.data[0]?.id ??
          null;

        setTenantId(selected);

        if (selected) {
          localStorage.setItem("agcp_tenant_id", String(selected));
        }
      } catch (error) {
        if (error instanceof Error && error.message === "UNAUTHENTICATED") {
          router.replace("/login");
          return;
        }

        console.error(error);
      } finally {
        if (active) setLoading(false);
      }
    }

    initialize();

    return () => {
      active = false;
    };
  }, [router]);

  const context = useMemo(
    () => ({ tenantId, tenants, loading }),
    [tenantId, tenants, loading],
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
    <AdminContext.Provider value={context}>
      <div className="admin-app">
        <aside className="sidebar">
          <div className="brand">
            <span className="brand-mark">A</span>
            <div>
              <strong>AGCP</strong>
              <small>Command Center</small>
            </div>
          </div>

          <nav className="nav-list">
            {nav.map(([href, label]) => {
              const active =
                href === "/admin"
                  ? pathname === href
                  : pathname.startsWith(href);

              return (
                <Link
                  key={href}
                  className={active ? "nav-link active" : "nav-link"}
                  href={href}
                >
                  {label}
                </Link>
              );
            })}
          </nav>

          <div className="sidebar-footer">
            <small>Signed in as</small>
            <strong>{userName}</strong>
            <button className="ghost-button" onClick={signOut}>
              Sign out
            </button>
          </div>
        </aside>

        <main className="admin-main">
          <header className="topbar">
            <div>
              <small>AGCP 2026â€“2027</small>
              <h1>Admin Command Center</h1>
            </div>

            <label className="tenant-picker">
              <span>Active tenant</span>
              <select
                value={tenantId ?? ""}
                disabled={loading || tenants.length === 0}
                onChange={(event) => {
                  const value = Number(event.target.value);
                  setTenantId(value);
                  localStorage.setItem("agcp_tenant_id", String(value));
                }}
              >
                {tenants.length === 0 && <option value="">No tenant</option>}
                {tenants.map((tenant) => (
                  <option key={tenant.id} value={tenant.id}>
                    {tenant.name}
                  </option>
                ))}
              </select>
            </label>
          </header>

          <section className="content-shell">{children}</section>
        </main>
      </div>
    </AdminContext.Provider>
  );
}