import Link from "next/link";

const features = [
  ["01", "Revenue intelligence", "Create tenant-isolated KPI snapshots with gross, net, refunds, customers, completion and supplier performance."],
  ["02", "Explainable forecasts", "Project near-term revenue with a stored basis window, confidence score, daily points and a reproducible method."],
  ["03", "Customer segments", "Classify customer value and churn risk from recency, frequency and monetary evidence without exporting tenant data."],
  ["04", "Supplier recommendations", "Rank mapped suppliers using health, success, cost and latency while preserving every candidate score."],
  ["05", "AI-assisted insights", "Generate actionable sales, fraud, customer and operations insights using a local deterministic provider."],
  ["06", "Human control", "Keep wallet movement, fraud review and production routing under existing permissions, rules and approval workflows."],
];

export default function Home() {
  return (
    <main>
      <section className="hero shell">
        <nav className="landingNav"><Link className="brand" href="/"><span>A</span>AGCP</Link><div><Link href="/catalog">Catalog</Link><Link href="/login">Sign in</Link><Link className="navCta" href="/register">Create account</Link></div></nav>
        <div className="heroGrid">
          <div><p className="eyebrow">Phase 8 · Explainable AI & Analytics</p><h1>See what changed. Understand why. Act with control.</h1><p className="lead">A tenant-safe intelligence layer for commerce KPIs, forecasting, customer segmentation, supplier recommendations and auditable operational insights.</p><div className="actions"><Link className="primary" href="/catalog">Explore catalog</Link><Link className="secondary" href="/admin/analytics">Analytics control center</Link></div></div>
          <aside className="statusCard"><header><i />Explainable analytics ready</header>{[["Forecasting","Stored evidence"],["Segments","RFM-style scoring"],["Suppliers","Ranked candidates"],["AI provider","Local deterministic"]].map(([a,b])=><div className="metric" key={a}><span>{a}</span><strong>{b}</strong></div>)}</aside>
        </div>
      </section>
      <section className="shell section"><p className="eyebrow">Decision support</p><h2>Use intelligence without giving an opaque model control of money or fulfillment.</h2><div className="featureGrid">{features.map(([n,t,d])=><article key={n}><span>{n}</span><h3>{t}</h3><p>{d}</p></article>)}</div></section>
      <footer className="shell"><b>AGCP</b><span>Explainable AI & Advanced Analytics · Phase 8</span></footer>
    </main>
  );
}
