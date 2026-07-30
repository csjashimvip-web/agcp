"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { PortalHeader } from "@/components/PortalHeader";
import { ApiError, ApiUser, authApi, errorMessage } from "@/lib/auth-api";
import { reliabilityApi, ReliabilityDashboard } from "@/lib/reliability-api";

function bytes(value: number | null): string {
  if (!value) return "—";
  const units = ["B", "KB", "MB", "GB"];
  let size = value;
  let unit = 0;
  while (size >= 1024 && unit < units.length - 1) { size /= 1024; unit++; }
  return `${size.toFixed(unit === 0 ? 0 : 1)} ${units[unit]}`;
}

export default function ReliabilityPage() {
  const router = useRouter();
  const [user, setUser] = useState<ApiUser | null>(null);
  const [data, setData] = useState<ReliabilityDashboard | null>(null);
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    try {
      const [me, dashboard] = await Promise.all([authApi.me(), reliabilityApi.dashboard()]);
      setUser(me.data);
      setData(dashboard.data);
      setError("");
    } catch (exception) {
      if (exception instanceof ApiError && exception.status === 401) router.replace("/login");
      else setError(errorMessage(exception));
    }
  }, [router]);

  useEffect(() => { void load(); }, [load]);

  async function createBackup() {
    if (!window.confirm("Create an encrypted database backup now?")) return;
    setBusy(true); setError(""); setMessage("");
    try { await reliabilityApi.backup(); setMessage("Encrypted database backup completed."); await load(); }
    catch (exception) { setError(errorMessage(exception)); }
    finally { setBusy(false); }
  }

  async function verifyLatest() {
    const backup = data?.backups.find((item) => item.status === "completed");
    if (!backup) { setError("No completed backup is available to verify."); return; }
    setBusy(true); setError(""); setMessage("");
    try { await reliabilityApi.verify(backup.id); setMessage("Backup integrity drill passed."); await load(); }
    catch (exception) { setError(errorMessage(exception)); }
    finally { setBusy(false); }
  }

  async function runCheck() {
    setBusy(true); setError(""); setMessage("");
    try { await reliabilityApi.check(); setMessage("Production-readiness check recorded."); await load(); }
    catch (exception) { setError(errorMessage(exception)); }
    finally { setBusy(false); }
  }

  if (!data && !error) return <main className="centerPage"><div className="loader"/><p>Loading reliability controls…</p></main>;
  const readiness = data?.readiness;
  const latest = data?.backups[0];

  return <main className="portal">
    <PortalHeader name={user?.name} admin />
    <section className="shell portalTitle">
      <p className="eyebrow">Phase 12 · Production readiness</p>
      <h1>Reliability, backups and release assurance</h1>
      <p>Encrypted MySQL backups, authenticated restore drills, scheduler heartbeats and deploy-time checks are controlled without exposing database artifacts to the browser.</p>
      <div className="actions">
        <button className="primary" disabled={busy} onClick={createBackup}>Create encrypted backup</button>
        <button className="secondary" disabled={busy} onClick={verifyLatest}>Verify latest backup</button>
        <button className="secondary" disabled={busy} onClick={runCheck}>Run readiness check</button>
        <Link className="secondary" href="/admin/operations">Operations center</Link>
      </div>
    </section>
    {error ? <div className="shell notice error">{error}</div> : null}
    {message ? <div className="shell notice success">{message}</div> : null}
    <section className="shell metricGrid">
      <article><small>Readiness</small><strong>{readiness?.status ?? "unknown"}</strong><span>{readiness?.summary.failed ?? 0} failed · {readiness?.summary.warnings ?? 0} warnings</span></article>
      <article><small>Latest backup</small><strong>{latest?.status ?? "none"}</strong><span>{bytes(latest?.file_size ?? null)}</span></article>
      <article><small>Integrity</small><strong>{latest?.verified_at ? "verified" : "pending"}</strong><span>Checksum + decryption + gzip</span></article>
      <article><small>Retention</small><strong>{latest?.expires_at?.slice(0, 10) ?? "—"}</strong><span>Private encrypted artifact</span></article>
    </section>
    <section className="shell adminLayout">
      <article className="adminCard wide">
        <div className="cardHead"><div><small>Deployment gate</small><h2>Readiness checks</h2></div><span className={`pill ${readiness?.status === "passed" ? "good" : "warn"}`}>{readiness?.status}</span></div>
        <div className="tableWrap"><table><thead><tr><th>Check</th><th>Status</th><th>Critical</th><th>Evidence</th></tr></thead><tbody>{readiness?.checks.map((check) => <tr key={check.key}><td><strong>{check.key.replaceAll("_", " ")}</strong></td><td><span className={`pill ${check.status === "passed" ? "good" : "warn"}`}>{check.status}</span></td><td>{check.critical ? "Yes" : "No"}</td><td>{check.message}</td></tr>)}</tbody></table></div>
      </article>
      <article className="adminCard wide">
        <div className="cardHead"><div><small>Private artifacts</small><h2>Database backups</h2></div><span className="pill">{data?.backups.length ?? 0} records</span></div>
        <div className="tableWrap"><table><thead><tr><th>Started</th><th>Status</th><th>Size</th><th>Encrypted</th><th>Verified</th><th>Expires</th></tr></thead><tbody>{data?.backups.map((backup) => <tr key={backup.id}><td>{backup.started_at?.slice(0,19).replace("T"," ")}</td><td><span className={`pill ${backup.status === "completed" ? "good" : "warn"}`}>{backup.status}</span></td><td>{bytes(backup.file_size)}</td><td>{backup.encrypted ? "Yes" : "No"}</td><td>{backup.verified_at ? backup.verified_at.slice(0, 10) : "Pending"}</td><td>{backup.expires_at?.slice(0, 10) ?? "—"}</td></tr>)}</tbody></table></div>
      </article>
      <article className="adminCard"><h2>Restore drills</h2><div className="roleList">{data?.restore_drills.slice(0, 8).map((drill) => <div key={drill.id}><strong>{drill.status}</strong><small>{drill.started_at?.slice(0,19).replace("T"," ")} · {bytes(drill.inspected_bytes)}</small></div>)}</div></article>
      <article className="adminCard"><h2>Release checks</h2><div className="roleList">{data?.release_checks.slice(0, 8).map((check) => <div key={check.id}><strong>{check.status} · {check.version}</strong><small>{check.summary.failed} failed · {check.summary.warnings} warnings</small></div>)}</div></article>
    </section>
  </main>;
}
