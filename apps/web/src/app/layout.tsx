import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "AGCP Command Center",
  description: "Araabi Global Commerce Platform administration",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}