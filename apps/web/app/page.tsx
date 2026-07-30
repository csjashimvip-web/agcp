import Link from "next/link";

const features = [
  ["01", "Versioned rules", "Publish pricing and fraud decisions as immutable versions with checksums and a complete execution trail."],
  ["02", "Dynamic pricing", "Apply server-side quantity, segment and context adjustments while preserving the original catalog price."],
  ["03", "Risk scoring", "Combine deterministic signals with tenant rules to produce allow, step-up, review or block decisions."],
  ["04", "Manual review", "Hold risky orders away from supplier fulfillment until an authorized reviewer approves or rejects them."],
  ["05", "Explainable decisions", "Every matched condition, action, signal and pricing adjustment remains visible for audit and support."],
  ["06", "Safe automation", "Rules change business behavior without embedding tenant policy inside checkout, wallet or supplier code."],
];

export default function Home() {
  return (
    <main>
      <section className="hero shell">
        <nav className="landingNav"><Link className="brand" href="/"><span>A</span>AGCP</Link><div><Link href="/catalog">Catalog</Link><Link href="/login">Sign in</Link><Link className="navCta" href="/register">Create account</Link></div></nav>
        <div className="heroGrid">
          <div><p className="eyebrow">Phase 6 · Rules, Fraud & Dynamic Pricing</p><h1>Decisions that are adaptive, explainable and controlled.</h1><p className="lead">A deterministic policy layer for dynamic prices, fraud scoring, review holds and auditable automation across commerce and supplier fulfillment.</p><div className="actions"><Link className="primary" href="/catalog">Explore catalog</Link><Link className="secondary" href="/admin/rules">Rules & risk dashboard</Link></div></div>
          <aside className="statusCard"><header><i />Decision engine ready</header>{[["Rules","Versioned publishing"],["Pricing","Server-side adjustment"],["Fraud","Explainable score"],["Review","Hold and release"]].map(([a,b])=><div className="metric" key={a}><span>{a}</span><strong>{b}</strong></div>)}</aside>
        </div>
      </section>
      <section className="shell section"><p className="eyebrow">Policy automation</p><h2>Change business decisions without rewriting the transaction core.</h2><div className="featureGrid">{features.map(([n,t,d])=><article key={n}><span>{n}</span><h3>{t}</h3><p>{d}</p></article>)}</div></section>
      <footer className="shell"><b>AGCP</b><span>Rules, Fraud & Dynamic Pricing · Phase 6</span></footer>
    </main>
  );
}
