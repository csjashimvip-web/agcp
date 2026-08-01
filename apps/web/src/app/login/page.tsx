"use client";

import { FormEvent, useState } from "react";
import { useRouter } from "next/navigation";
import { login } from "@/lib/agcp-api";

function safeNextPath(): string {
  if (typeof window === "undefined") return "/admin";

  const value = new URLSearchParams(window.location.search).get("next");

  if (!value || !value.startsWith("/") || value.startsWith("//")) {
    return "/admin";
  }

  return value;
}

export default function LoginPage() {
  const router = useRouter();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setBusy(true);
    setError("");

    try {
      await login(email, password);
      router.replace(safeNextPath());
      router.refresh();
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : "Login failed.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <main className="login-page">
      <section className="login-panel">
        <div className="login-brand">
          <span className="brand-mark large">A</span>
          <div>
            <p className="eyebrow">AGCP 2026â€“2027</p>
            <h1>Secure Sign In</h1>
          </div>
        </div>

        <p className="login-copy">
          Access your customer account or authorized administration workspace.
        </p>

        <form onSubmit={submit} className="login-form">
          <label>
            <span>Email</span>
            <input
              type="email"
              autoComplete="email"
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              required
            />
          </label>

          <label>
            <span>Password</span>
            <input
              type="password"
              autoComplete="current-password"
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              required
            />
          </label>

          {error && <div className="error-banner">{error}</div>}

          <button className="primary-button" disabled={busy} type="submit">
            {busy ? "Signing inâ€¦" : "Sign in"}
          </button>
        </form>
      </section>
    </main>
  );
}