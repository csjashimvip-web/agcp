"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { authApi } from "@/lib/auth-api";

export function PortalHeader({ name, admin }: { name?: string; admin?: boolean }) {
  const router = useRouter();
  async function logout() {
    await authApi.logout().catch(() => undefined);
    router.replace("/login");
    router.refresh();
  }

  return (
    <header className="portalHeader shell">
      <Link className="brand" href="/dashboard"><span>A</span>AGCP</Link>
      <nav className="portalNav">
        <Link href="/dashboard">Dashboard</Link>
        <Link href="/catalog">Catalog</Link>
        <Link href="/cart">Cart</Link>
        <Link href="/orders">Orders</Link>
        <Link href="/wallet">Wallet</Link>
        <Link href="/deposits">Deposits</Link>
        <Link href="/security">Security</Link>
        {admin ? <><Link href="/admin">Admin</Link><Link href="/admin/wallets">Wallet admin</Link><Link href="/admin/commerce">Commerce admin</Link></> : null}
        <button onClick={logout}>Sign out{name ? ` · ${name}` : ""}</button>
      </nav>
    </header>
  );
}
