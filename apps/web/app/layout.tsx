import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "AGCP — Secure Commerce Identity",
  description: "Enterprise identity and access foundation for Araabi Global Commerce Platform.",
};

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return <html lang="en"><body>{children}</body></html>;
}
