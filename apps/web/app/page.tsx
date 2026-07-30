import Link from "next/link";

const features = [
  ["01", "Unified catalog", "Physical products, digital items and structured services share one tenant-aware catalog."],
  ["02", "Dynamic pricing", "Currency price lists and quantity tiers are resolved again during checkout."],
  ["03", "Inventory reservations", "Tracked stock is locked and reserved atomically before wallet payment posts."],
  ["04", "Wallet checkout", "Orders debit the customer wallet and credit commerce revenue in one balanced journal."],
  ["05", "Order lifecycle", "Confirmed, processing, completed and canceled states preserve a full status history."],
  ["06", "Headless commerce", "Versioned APIs power the website today and mobile or reseller clients later."],
];

export default function Home() {
  return (
    <main>
      <section className="hero shell">
        <nav className="landingNav"><Link className="brand" href="/"><span>A</span>AGCP</Link><div><Link href="/catalog">Catalog</Link><Link href="/login">Sign in</Link><Link className="navCta" href="/register">Create account</Link></div></nav>
        <div className="heroGrid">
          <div><p className="eyebrow">Phase 4 · Commerce Core</p><h1>Products, services and wallet checkout in one platform.</h1><p className="lead">A tenant-aware commerce foundation with pricing, stock reservation, structured service inputs and traceable orders.</p><div className="actions"><Link className="primary" href="/catalog">Browse catalog</Link><Link className="secondary" href="/cart">Open cart</Link></div></div>
          <aside className="statusCard"><header><i />Commerce core ready</header>{[["Catalog","Three item types"],["Pricing","Currency and tiers"],["Inventory","Atomic reservation"],["Checkout","Wallet journal"]].map(([a,b])=><div className="metric" key={a}><span>{a}</span><strong>{b}</strong></div>)}</aside>
        </div>
      </section>
      <section className="shell section"><p className="eyebrow">Commerce capabilities</p><h2>Operational controls behind a simple customer journey.</h2><div className="featureGrid">{features.map(([n,t,d])=><article key={n}><span>{n}</span><h3>{t}</h3><p>{d}</p></article>)}</div></section>
      <footer className="shell"><b>AGCP</b><span>Commerce Core · Phase 4</span></footer>
    </main>
  );
}
