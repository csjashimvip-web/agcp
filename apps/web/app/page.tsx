import Link from "next/link";

const features = [
  ["01", "Auditable routing", "Every candidate receives a stored score with cost, success, health, latency and priority evidence."],
  ["02", "Automatic failover", "Failed providers are penalized and excluded while the next eligible mapping is selected automatically."],
  ["03", "Provider adapters", "Supplier-specific API code stays behind a stable contract instead of leaking into checkout or order logic."],
  ["04", "Health scoring", "Scheduled probes and live request outcomes update latency, success rate and temporary circuit breaking."],
  ["05", "Background fulfillment", "Outbox events, supplier queues and polling workers keep slow external APIs away from customer requests."],
  ["06", "Automatic refund", "Terminal fulfillment failure posts a balanced item-level wallet refund with an immutable ledger reference."],
];

export default function Home() {
  return (
    <main>
      <section className="hero shell">
        <nav className="landingNav"><Link className="brand" href="/"><span>A</span>AGCP</Link><div><Link href="/catalog">Catalog</Link><Link href="/login">Sign in</Link><Link className="navCta" href="/register">Create account</Link></div></nav>
        <div className="heroGrid">
          <div><p className="eyebrow">Phase 5 · Smart Supplier Engine</p><h1>External fulfillment without surrendering control.</h1><p className="lead">A deterministic supplier router with provider isolation, health scoring, queue processing, failover and ledger-backed refunds.</p><div className="actions"><Link className="primary" href="/catalog">Test automated service</Link><Link className="secondary" href="/admin/suppliers">Supplier dashboard</Link></div></div>
          <aside className="statusCard"><header><i />Supplier engine ready</header>{[["Routing","Balanced scoring"],["Failure","Automatic failover"],["Health","Circuit protection"],["Refund","Item-level journal"]].map(([a,b])=><div className="metric" key={a}><span>{a}</span><strong>{b}</strong></div>)}</aside>
        </div>
      </section>
      <section className="shell section"><p className="eyebrow">Supplier automation</p><h2>Fast customer journeys backed by explainable operations.</h2><div className="featureGrid">{features.map(([n,t,d])=><article key={n}><span>{n}</span><h3>{t}</h3><p>{d}</p></article>)}</div></section>
      <footer className="shell"><b>AGCP</b><span>Smart Supplier Engine · Phase 5</span></footer>
    </main>
  );
}
