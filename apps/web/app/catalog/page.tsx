"use client";
import Link from "next/link";
import { useEffect, useState } from "react";
import { PortalHeader } from "@/components/PortalHeader";
import { ApiUser, authApi, errorMessage } from "@/lib/auth-api";
import { CatalogItem, commerceApi } from "@/lib/commerce-api";

export default function CatalogPage(){
 const [user,setUser]=useState<ApiUser|null>(null);const [items,setItems]=useState<CatalogItem[]>([]);const [message,setMessage]=useState("");const [busy,setBusy]=useState("");const [serviceValues,setServiceValues]=useState<Record<string,string>>({});
 useEffect(()=>{authApi.me().then(r=>setUser(r.data)).catch(()=>undefined);commerceApi.catalog().then(r=>setItems(r.data)).catch(e=>setMessage(errorMessage(e)))},[]);
 async function add(item:CatalogItem){
  if(!user){window.location.href="/login";return} const variant=item.variants[0];if(!variant)return;
  setBusy(item.id);setMessage("");try{const configuration:Record<string,string>={};for(const field of item.service_schema?.fields??[])configuration[field.name]=serviceValues[`${item.id}:${field.name}`]??"";await commerceApi.addToCart({variant_id:variant.id,quantity:1,configuration,currency:variant.price?.currency??"USD"});setMessage(`${item.name} added to cart.`)}catch(e){setMessage(errorMessage(e))}finally{setBusy("")}
 }
 return <main className="portal">{user?<PortalHeader name={user.name} admin={user.permissions.includes("identity.admin.access")}/>:<header className="portalHeader shell"><Link className="brand" href="/"><span>A</span>AGCP</Link><nav className="portalNav"><Link href="/login">Sign in</Link><Link href="/register">Create account</Link></nav></header>}<section className="shell portalTitle"><p className="eyebrow">Commerce core</p><h1>Products and digital services</h1><p>Tenant-scoped pricing, inventory-aware products and structured service inputs.</p></section>{message?<div className="shell notice success">{message}</div>:null}<section className="shell commerceGrid">{items.map(item=>{const variant=item.variants[0];return <article className="commerceCard" key={item.id}><div className="cardHead"><div><small>{item.type}</small><h2>{item.name}</h2></div><span className="pill good">{variant?.price?`${variant.price.amount} ${variant.price.currency}`:"Price unavailable"}</span></div><p>{item.summary}</p>{item.inventory_tracking?<p className="stockLine">Available: {variant?.available_quantity??0}</p>:null}{(item.service_schema?.fields??[]).map(field=><label className="serviceField" key={field.name}>{field.label??field.name}<input value={serviceValues[`${item.id}:${field.name}`]??""} onChange={e=>setServiceValues(v=>({...v,[`${item.id}:${field.name}`]:e.target.value}))} placeholder={field.required?"Required":"Optional"}/></label>)}<div className="actions"><button className="primary" disabled={busy===item.id||!variant?.price} onClick={()=>add(item)}>{busy===item.id?"Adding…":"Add to cart"}</button><Link className="secondary" href="/cart">Open cart</Link></div></article>})}</section></main>
}
