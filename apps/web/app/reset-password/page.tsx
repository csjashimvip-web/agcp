"use client";

import { FormEvent, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { AuthShell } from "@/components/AuthShell";
import { apiFetch, errorMessage } from "@/lib/auth-api";

export default function ResetPasswordPage() {
  const router=useRouter(); const [email,setEmail]=useState(""); const [token,setToken]=useState(""); const [password,setPassword]=useState(""); const [confirmation,setConfirmation]=useState(""); const [message,setMessage]=useState(""); const [busy,setBusy]=useState(false);
  useEffect(()=>{const q=new URLSearchParams(window.location.search);setEmail(q.get("email")??"");setToken(q.get("token")??"")},[]);
  async function submit(event:FormEvent){event.preventDefault();setBusy(true);setMessage("");try{await apiFetch("/api/v1/auth/reset-password",{method:"POST",body:JSON.stringify({email,token,password,password_confirmation:confirmation})});router.replace("/login")}catch(error){setMessage(errorMessage(error))}finally{setBusy(false)}}
  return <AuthShell title="Choose a new password" subtitle="Resetting the password revokes active API tokens and tracked sessions.">{message?<div className="notice error">{message}</div>:null}<form className="formStack" onSubmit={submit}><label>Email address<input type="email" value={email} onChange={(e)=>setEmail(e.target.value)} required /></label><label>New password<input type="password" autoComplete="new-password" minLength={12} value={password} onChange={(e)=>setPassword(e.target.value)} required /></label><label>Confirm password<input type="password" autoComplete="new-password" value={confirmation} onChange={(e)=>setConfirmation(e.target.value)} required /></label><button className="primary full" disabled={busy}>{busy?"Resetting…":"Reset password"}</button></form></AuthShell>;
}
