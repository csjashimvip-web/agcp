import Link from "next/link";
import type { ReactNode } from "react";

export function AuthShell({ title, subtitle, children, footer }: { title: string; subtitle: string; children: ReactNode; footer?: ReactNode }) {
  return (
    <main className="authPage">
      <div className="authBrand"><Link href="/"><span>A</span>AGCP</Link></div>
      <section className="authCard">
        <div className="authCardHead"><p className="eyebrow">Identity & Access</p><h1>{title}</h1><p>{subtitle}</p></div>
        {children}
        {footer ? <div className="authFooter">{footer}</div> : null}
      </section>
    </main>
  );
}
