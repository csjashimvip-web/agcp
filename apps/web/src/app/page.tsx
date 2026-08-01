import Link from "next/link";

export default function Home() {
  return (
    <main className="landing-page">
      <section className="landing-hero">
        <div className="brand-mark large">A</div>
        <p className="eyebrow">Araabi Global Commerce Platform</p>
        <h1>AGCP 2026â€“2027</h1>
        <p>
          Enterprise commerce, wallet, supplier orchestration and reseller API
          infrastructure in one platform.
        </p>
        <div className="landing-actions">
          <Link className="primary-link" href="/login">
            Sign in
          </Link>
          <Link className="ghost-link" href="/account">
            Customer account
          </Link>
          <Link className="ghost-link" href="/admin">
            Admin
          </Link>
        </div>
      </section>
    </main>
  );
}