import CustomerShell from "@/components/customer-shell";

export default function AccountLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return <CustomerShell>{children}</CustomerShell>;
}