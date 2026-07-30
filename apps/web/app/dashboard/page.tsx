"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { PortalHeader } from "@/components/PortalHeader";
import { ApiUser, apiFetch, authApi, errorMessage } from "@/lib/auth-api";

export default function DashboardPage() {
  const router=useRouter(); const [user,setUser]=useState<ApiUser|null>(null); const [message,setMessage]=useState("");
  useEffect(()=>{authApi.me().then((r)=>setUser(r.data)).catch((error)=>{if((error as {status?:number}).status===401)router.replace("/login");else setMessage(errorMessage(error))})},[router]);
  async function resend(){try{const r=await apiFetch<{message?:string}>("/api/v1/auth/email/verification-notification",{method:"POST"});setMessage(r?.message??"Verification email sent.")}catch(error){setMessage(errorMessage(error))}}
  if(!user)return <main className="centerPage"><div className="loader"/><p>{message||"Loading your secure workspace…"}</p></main>;
  const isAdmin=user.permissions.includes("identity.admin.access");
  const cards=[
    ["Wallet","Ready","Tenant-scoped balances are backed by an immutable double-entry ledger."],
    ["Email",user.email_verified?"Verified":"Action required",user.email_verified?"Your primary identity is verified.":"Verify your address before sensitive operations."],
    ["Two-factor",user.two_factor_enabled?"Enabled":"Not enabled",user.two_factor_enabled?"Authenticator challenge protects this account.":"Required before administrative access."],
    ["Passkeys",user.passkeys_enabled?"Registered":"Available",user.passkeys_enabled?"Passwordless sign-in is configured.":"Add Windows Hello or a security key."],
    ["Access",user.roles.map(r=>r.name).join(", ")||"Customer","Permissions are resolved for the active tenant."],
  ];
  return <main className="portal"><PortalHeader name={user.name} admin={isAdmin}/><section className="shell portalHero"><div><p className="eyebrow">Secure workspace</p><h1>Good to see you, {user.name.split(" ")[0]}.</h1><p>Manage identity, verified payment deposits, wallet balances, catalog shopping and orders from one place.</p></div><div className="accountBadge"><span>{user.name.slice(0,1).toUpperCase()}</span><div><strong>{user.email}</strong><small>{user.status}</small></div></div></section>{message?<div className="shell notice success">{message}</div>:null}<section className="shell portalGrid five">{cards.map(([title,value,desc])=><article className="infoCard" key={title}><small>{title}</small><h3>{value}</h3><p>{desc}</p></article>)}</section><section className="shell actionPanel"><div><p className="eyebrow">Recommended next step</p><h2>{user.two_factor_enabled?"Review active sessions":"Protect the account with 2FA"}</h2><p>{user.two_factor_enabled?"Remove any device or session that you do not recognize.":"Administrative access remains locked until two-factor authentication is confirmed."}</p></div><div className="actions"><Link className="primary" href="/catalog">Browse catalog</Link><Link className="secondary" href="/orders">View orders</Link><Link className="secondary" href="/wallet">Open wallet</Link><Link className="secondary" href="/payments">Add balance securely</Link><Link className="secondary" href="/security">Security center</Link>{!user.email_verified?<button className="secondary" onClick={resend}>Resend verification</button>:null}{isAdmin&&user.two_factor_enabled?<Link className="secondary" href="/admin">Open administration</Link>:null}</div></section></main>;
}
