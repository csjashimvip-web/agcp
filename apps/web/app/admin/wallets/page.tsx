"use client";
import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { PortalHeader } from "@/components/PortalHeader";
import { ApiUser, authApi, errorMessage } from "@/lib/auth-api";
import { Deposit, walletApi } from "@/lib/wallet-api";
export default function AdminWalletsPage(){
 const router=useRouter(); const [user,setUser]=useState<ApiUser|null>(null); const [items,setItems]=useState<Deposit[]>([]); const [message,setMessage]=useState(""); const [busy,setBusy]=useState<string|null>(null);
 async function load(){const [u,d]=await Promise.all([authApi.me(),walletApi.adminDeposits('pending')]);setUser(u.data);setItems(d.data)}
 useEffect(()=>{load().catch(e=>{if((e as {status?:number}).status===401)router.replace('/login');else setMessage(errorMessage(e))})},[router]);
 async function approve(id:string){setBusy(id);try{await walletApi.approveDeposit(id);await load();setMessage('Deposit approved and wallet credited.')}catch(e){setMessage(errorMessage(e))}finally{setBusy(null)}}
 async function reject(id:string){const note=window.prompt('Rejection reason');if(!note)return;setBusy(id);try{await walletApi.rejectDeposit(id,note);await load();setMessage('Deposit rejected.')}catch(e){setMessage(errorMessage(e))}finally{setBusy(null)}}
 if(!user)return <main className="centerPage"><div className="loader"/><p>{message||"Loading wallet administration…"}</p></main>;
 return <main className="portal"><PortalHeader name={user.name} admin/><section className="shell portalHero"><div><p className="eyebrow">Wallet administration</p><h1>Pending deposit review</h1><p>Approve only after independently confirming the payment reference and amount.</p></div></section>{message?<div className="shell notice success">{message}</div>:null}<section className="shell actionPanel ledgerPanel"><div className="tableWrap"><table><thead><tr><th>Customer</th><th>Amount</th><th>Method</th><th>Reference</th><th>Action</th></tr></thead><tbody>{items.length?items.map(d=><tr key={d.id}><td>{d.user?.email||'Customer'}</td><td>{d.amount} {d.currency}</td><td>{d.method}</td><td>{d.external_reference||'—'}</td><td><div className="tableActions"><button className="primary small" disabled={busy===d.id} onClick={()=>approve(d.id)}>Approve</button><button className="secondary small" disabled={busy===d.id} onClick={()=>reject(d.id)}>Reject</button></div></td></tr>):<tr><td colSpan={5}>No pending deposits.</td></tr>}</tbody></table></div></section></main>
}
