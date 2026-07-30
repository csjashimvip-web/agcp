"use client";

import Link from "next/link";
import { FormEvent, useState } from "react";
import { AuthShell } from "@/components/AuthShell";
import { apiFetch, errorMessage } from "@/lib/auth-api";

export default function ForgotPasswordPage() {
  const [email,setEmail]=useState(""); const [busy,setBusy]=useState(false); const [message,setMessage]=useState(""); const [ok,setOk]=useState(false);
  async function submit(event:FormEvent){event.preventDefault();setBusy(true);setMessage("");try{const result=await apiFetch<{message?:string}>("/api/v1/auth/forgot-password",{method:"POST",body:JSON.stringify({email})});setMessage(result?.message??"If the account exists, a reset link has been sent.");setOk(true)}catch(error){setMessage(errorMessage(error));setOk(false)}finally{setBusy(false)}}
  return <AuthShell title="Reset your password" subtitle="Enter your account email. Reset instructions are written to the configured mail channel." footer={<Link href="/login">Return to sign in</Link>}>
    {message?<div className={`notice ${ok?"success":"error"}`}>{message}</div>:null}
    <form className="formStack" onSubmit={submit}><label>Email address<input type="email" autoComplete="email" value={email} onChange={(e)=>setEmail(e.target.value)} required /></label><button className="primary full" disabled={busy}>{busy?"Sending…":"Send reset link"}</button></form>
  </AuthShell>;
}
