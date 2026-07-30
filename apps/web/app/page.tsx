import Link from "next/link";

const features = [
  ["01", "Tenant control plane", "Provision isolated companies with dedicated memberships, roles, subscriptions and domain identities."],
  ["02", "Plan entitlements", "Resolve nested feature flags and quota limits from the tenant's active subscription without hard-coding plan logic."],
  ["03", "White-label branding", "Give every tenant its own name, legal identity, colors, support contacts, logo references and locale."],
  ["04", "Custom domains", "Track domain ownership verification, primary-domain selection and managed SSL lifecycle metadata."],
  ["05", "Approved plugins", "Install reviewed provider manifests with encrypted per-tenant secrets and auditable lifecycle events."],
  ["06", "Safe extensibility", "Extend payments, suppliers and notifications without accepting arbitrary executable plugin uploads."],
];

export default function Home() {
  return (
    <main>
      <section className="hero shell">
        <nav className="landingNav"><Link className="brand" href="/"><span>A</span>AGCP</Link><div><Link href="/catalog">Catalog</Link><Link href="/login">Sign in</Link><Link className="navCta" href="/register">Create account</Link></div></nav>
        <div className="heroGrid">
          <div><p className="eyebrow">Phase 7 · Multi-Tenant SaaS & Plugins</p><h1>One commerce platform. Many securely isolated businesses.</h1><p className="lead">A subscription-aware SaaS control plane for tenant provisioning, white-label branding, quotas, custom domains and approved provider extensions.</p><div className="actions"><Link className="primary" href="/catalog">Explore catalog</Link><Link className="secondary" href="/admin/saas">SaaS control plane</Link></div></div>
          <aside className="statusCard"><header><i />SaaS control plane ready</header>{[["Tenancy","Isolated context"],["Plans","Feature entitlements"],["Domains","Verification lifecycle"],["Plugins","Encrypted configuration"]].map(([a,b])=><div className="metric" key={a}><span>{a}</span><strong>{b}</strong></div>)}</aside>
        </div>
      </section>
      <section className="shell section"><p className="eyebrow">Platform operations</p><h2>Launch new brands without rebuilding the transaction core.</h2><div className="featureGrid">{features.map(([n,t,d])=><article key={n}><span>{n}</span><h3>{t}</h3><p>{d}</p></article>)}</div></section>
      <footer className="shell"><b>AGCP</b><span>Multi-Tenant SaaS & Plugin Marketplace · Phase 7</span></footer>
    </main>
  );
}
