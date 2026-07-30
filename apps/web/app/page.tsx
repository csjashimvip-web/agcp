import Link from "next/link";

const features = [
  ["01", "Verified payment intents", "Create add-balance requests with provider limits, fees, expiry and strict idempotency without trusting browser redirects."],
  ["02", "Signed webhook inbox", "Validate HMAC signatures, timestamps and event identifiers before any customer wallet balance is credited."],
  ["03", "Automatic ledger settlement", "Convert a verified capture into an approved deposit and balanced double-entry journal inside controlled transactions."],
  ["04", "Replay-safe processing", "Reject stale signatures and deduplicate external event IDs so repeated provider delivery cannot double-credit a wallet."],
  ["05", "Controlled refunds", "Reverse customer credit only after provider refund confirmation and only when available wallet balance can support it."],
  ["06", "Financial reconciliation", "Compare payment intents, deposits, ledger records and refunds to surface stale, orphaned or mismatched transactions."],
];

export default function Home() {
  return (
    <main>
      <section className="hero shell">
        <nav className="landingNav"><Link className="brand" href="/"><span>A</span>AGCP</Link><div><Link href="/catalog">Catalog</Link><Link href="/login">Sign in</Link><Link className="navCta" href="/register">Create account</Link></div></nav>
        <div className="heroGrid">
          <div><p className="eyebrow">Phase 9 · Payments & Reconciliation</p><h1>Credit balance only after the payment can be proven.</h1><p className="lead">A secure payment-orchestration boundary with signed webhook verification, replay protection, automatic ledger settlement, controlled refunds and auditable reconciliation.</p><div className="actions"><Link className="primary" href="/payments">Add balance</Link><Link className="secondary" href="/admin/payments">Payment control center</Link></div></div>
          <aside className="statusCard"><header><i />Payment orchestration ready</header>{[["Wallet credit","Verified webhook only"],["Replay defense","Event ID + timestamp"],["Refunds","Provider + ledger reversal"],["Reconciliation","Stored mismatch evidence"]].map(([a,b])=><div className="metric" key={a}><span>{a}</span><strong>{b}</strong></div>)}</aside>
        </div>
      </section>
      <section className="shell section"><p className="eyebrow">Financial integrity</p><h2>Never treat a success page as proof of money.</h2><div className="featureGrid">{features.map(([n,t,d])=><article key={n}><span>{n}</span><h3>{t}</h3><p>{d}</p></article>)}</div></section>
      <footer className="shell"><b>AGCP</b><span>Payment Orchestration & Financial Reconciliation · Phase 9</span></footer>
    </main>
  );
}
