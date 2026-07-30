"use client";

import Link from "next/link";
import { FormEvent, useState } from "react";
import { useRouter } from "next/navigation";
import { AuthShell } from "@/components/AuthShell";
import { authApi, errorMessage } from "@/lib/auth-api";

export default function RegisterPage() {
  const router = useRouter();
  const [form, setForm] = useState({ name:"", email:"", password:"", password_confirmation:"", terms:false });
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");
  function field(name: keyof typeof form, value: string | boolean) { setForm((current)=>({...current,[name]:value})); }

  async function submit(event: FormEvent) {
    event.preventDefault(); setBusy(true); setMessage("");
    try { await authApi.register(form); router.replace("/dashboard"); router.refresh(); }
    catch (error) { setMessage(errorMessage(error)); }
    finally { setBusy(false); }
  }

  return <AuthShell title="Create your account" subtitle="Join the Araabi Global platform with enterprise-grade account protection." footer={<><span>Already registered?</span> <Link href="/login">Sign in</Link></>}>
    {message ? <div className="notice error">{message}</div> : null}
    <form className="formStack" onSubmit={submit}>
      <label>Full name<input autoComplete="name" value={form.name} onChange={(e)=>field("name",e.target.value)} required /></label>
      <label>Email address<input type="email" autoComplete="email" value={form.email} onChange={(e)=>field("email",e.target.value)} required /></label>
      <label>Password<input type="password" autoComplete="new-password" value={form.password} onChange={(e)=>field("password",e.target.value)} minLength={12} required /><small>At least 12 characters with upper/lowercase, number and symbol.</small></label>
      <label>Confirm password<input type="password" autoComplete="new-password" value={form.password_confirmation} onChange={(e)=>field("password_confirmation",e.target.value)} required /></label>
      <label className="check"><input type="checkbox" checked={form.terms} onChange={(e)=>field("terms",e.target.checked)} required />I agree to the platform terms and privacy policy.</label>
      <button className="primary full" disabled={busy}>{busy ? "Creating securely…" : "Create secure account"}</button>
    </form>
  </AuthShell>;
}
