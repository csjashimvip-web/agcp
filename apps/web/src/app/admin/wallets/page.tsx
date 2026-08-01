import ResourceTable from "@/components/resource-table";

export default function WalletsPage() {
  return (
    <ResourceTable
      title="Wallets"
      description="Customer balances backed by the enterprise ledger."
      endpoint="/api/v1/admin/wallets"
      columns={[
        { key: "owner_name", label: "Owner" },
        { key: "owner_email", label: "Email" },
        { key: "currency", label: "Currency" },
        { key: "status", label: "Status" },
        { key: "available_balance_minor", label: "Available" },
        { key: "held_balance_minor", label: "Held" },
      ]}
    />
  );
}