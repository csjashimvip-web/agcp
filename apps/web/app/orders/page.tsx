"use client";
import Link from "next/link";
import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { PortalHeader } from "@/components/PortalHeader";
import { ApiUser, authApi, errorMessage } from "@/lib/auth-api";
import { commerceApi, Order } from "@/lib/commerce-api";
export default function OrdersPage(){
 const router=useRouter();const [user,setUser]=useState<ApiUser|null>(null);const [orders,setOrders]=useState<Order[]>([]);const [message,setMessage]=useState("");
 async function load(){const [u,o]=await Promise.all([authApi.me(),commerceApi.orders()]);setUser(u.data);setOrders(o.data)}
 useEffect(()=>{load().catch(e=>{if((e as {status?:number}).status===401)router.replace('/login');else setMessage(errorMessage(e))})},[router]);
 async function cancel(order:Order){if(!confirm(`Cancel ${order.number} and refund the wallet?`))return;try{await commerceApi.cancelOrder(order.id);setMessage("Order canceled and wallet refund posted.");await load()}catch(e){setMessage(errorMessage(e))}}
 if(!user)return <main className="centerPage"><div className="loader"/><p>{message||"Loading orders…"}</p></main>;
 return <main className="portal"><PortalHeader name={user.name} admin={user.permissions.includes('identity.admin.access')}/><section className="shell portalTitle"><p className="eyebrow">Commerce orders</p><h1>Order history</h1><p>Wallet payments, fulfillment states and cancellations are fully traceable.</p><div className="actions"><Link className="primary" href="/catalog">Shop catalog</Link><Link className="secondary" href="/cart">Open cart</Link></div></section>{message?<div className="shell notice success">{message}</div>:null}<section className="shell actionPanel ledgerPanel"><div className="tableWrap"><table><thead><tr><th>Order</th><th>Date</th><th>Total</th><th>Payment</th><th>Fulfillment</th><th>Action</th></tr></thead><tbody>{orders.length?orders.map(order=><tr key={order.id}><td><strong>{order.number}</strong><small>{order.status}</small></td><td>{order.placed_at?new Date(order.placed_at).toLocaleString():"—"}</td><td>{order.total} {order.currency}</td><td><span className="statusPill">{order.payment_status}</span></td><td><span className="statusPill">{order.fulfillment_status}</span></td><td>{["pending","confirmed"].includes(order.status)?<button className="dangerButton small" onClick={()=>cancel(order)}>Cancel</button>:"—"}</td></tr>):<tr><td colSpan={6}>No orders yet.</td></tr>}</tbody></table></div></section></main>
}
