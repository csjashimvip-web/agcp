"use client";

import Link from "next/link";
import { FormEvent, useState } from "react";
import { useRouter } from "next/navigation";
import { AuthShell } from "@/components/AuthShell";
import { authApi, csrf, errorMessage } from "@/lib/auth-api";

export default function LoginPage() {
  const router = useRouter();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [remember, setRemember] = useState(false);
  const [challenge, setChallenge] = useState(false);
  const [code, setCode] = useState("");
  const [recoveryMode, setRecoveryMode] = useState(false);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");

  async function submit(event: FormEvent) {
    event.preventDefault(); setBusy(true); setMessage("");
    try {
      if (challenge) {
        await authApi.twoFactorChallenge(recoveryMode ? "" : code, recoveryMode ? code : undefined);
      } else {
        const result = await authApi.login(email, password, remember);
        if (result && "two_factor" in result && result.two_factor) {
          setChallenge(true); setCode(""); return;
        }
      }
      router.replace("/dashboard"); router.refresh();
    } catch (error) { setMessage(errorMessage(error)); }
    finally { setBusy(false); }
  }

  async function passkeyLogin() {
    setBusy(true); setMessage("");
    try {
      await csrf();
      const { Passkeys } = await import("@laravel/passkeys");
      await Passkeys.verify();
      router.replace("/dashboard"); router.refresh();
    } catch (error) { setMessage(errorMessage(error)); }
    finally { setBusy(false); }
  }

  return <AuthShell title={challenge ? "Security challenge" : "Welcome back"} subtitle={challenge ? "Enter the six-digit code from your authenticator." : "Sign in to your secure AGCP account."} footer={<><span>New to AGCP?</span> <Link href="/register">Create an account</Link></>}>
    {message ? <div className="notice error">{message}</div> : null}
    <form className="formStack" onSubmit={submit}>
      {!challenge ? <>
        <label>Email address<input type="email" autoComplete="email" value={email} onChange={(e)=>setEmail(e.target.value)} required /></label>
        <label>Password<input type="password" autoComplete="current-password" value={password} onChange={(e)=>setPassword(e.target.value)} required /></label>
        <div className="formRow"><label className="check"><input type="checkbox" checked={remember} onChange={(e)=>setRemember(e.target.checked)} />Keep me signed in</label><Link href="/forgot-password">Forgot password?</Link></div>
      </> : <>
        <label>{recoveryMode ? "Recovery code" : "Authenticator code"}<input inputMode={recoveryMode ? "text" : "numeric"} autoComplete="one-time-code" value={code} onChange={(e)=>setCode(e.target.value)} required /></label>
        <button className="textButton" type="button" onClick={()=>{setRecoveryMode(!recoveryMode);setCode("")}}>{recoveryMode ? "Use an authenticator code" : "Use a recovery code"}</button>
      </>}
      <button className="primary full" disabled={busy}>{busy ? "Please wait…" : challenge ? "Verify and continue" : "Sign in"}</button>
    </form>
    {!challenge ? <><div className="divider"><span>or</span></div><button className="passkeyButton" type="button" disabled={busy} onClick={passkeyLogin}>Sign in with a passkey</button></> : null}
  </AuthShell>;
}
