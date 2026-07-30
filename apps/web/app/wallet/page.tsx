"use client";
import { useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { PortalHeader } from "@/components/PortalHeader";
import { authApi, ApiUser, errorMessage } from "@/lib/auth-api";
import { LedgerTransaction, Wallet, walletApi } from "@/lib/wallet-api";
export default function WalletPage(){
 const router=useRouter(); const [user,setUser]=useState<ApiUser|null>(null); const [wallets,setWallets]=useState<Wallet[]>([]); const [txs,setTxs]=useState<LedgerTransaction[]>([]); const [message,setMessage]=useState("");
 useEffect(()=>{Promise.all([authApi.me(),walletApi.wallets()]).then(async([u,w])=>{setUser(u.data);setWallets(w.data);if(w.data[0])setTxs((await walletApi.transactions(w.data[0].id)).data)}).catch(e=>{if((e as {status?:number}).status===401)router.replace('/login');else setMessage(errorMessage(e))})},[router]);
 if(!user)return <main className="centerPage"><div className="loader"/><p>{message||"Loading wallets…"}</p></main>;
 return <main className="portal"><PortalHeader name={user.name} admin={user.permissions.includes('identity.admin.access')}/><section className="shell portalHero"><div><p className="eyebrow">Enterprise wallet</p><h1>Your account balances</h1><p>Every movement is recorded as an immutable, balanced ledger transaction.</p></div><Link className="primary" href="/deposits">Add balance</Link></section>{message?<div className="shell notice">{message}</div>:null}<section className="shell portalGrid">{wallets.map(w=><article className="infoCard" key={w.id}><small>{w.type.toUpperCase()} · {w.currency}</small><h3>{w.available} {w.currency}</h3><p>Total {w.balance}; held {Number(w.held_minor/100).toFixed(2)}.</p></article>)}</section><section className="shell actionPanel ledgerPanel"><div><p className="eyebrow">Recent ledger activity</p><h2>Transaction history</h2></div><div className="tableWrap"><table><thead><tr><th>Date</th><th>Type</th><th>Description</th><th>Status</th></tr></thead><tbody>{txs.length?txs.map(t=><tr key={t.id}><td>{t.posted_at?new Date(t.posted_at).toLocaleString():"—"}</td><td>{t.event_type}</td><td>{t.description}</td><td><span className="statusPill">{t.status}</span></td></tr>):<tr><td colSpan={4}>No ledger activity yet.</td></tr>}</tbody></table></div></section></main>
}
