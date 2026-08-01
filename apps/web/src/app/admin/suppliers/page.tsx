import ResourceTable from "@/components/resource-table";

export default function SuppliersPage() {
  return (
    <ResourceTable
      title="Suppliers"
      description="Supplier adapters, routing priority and runtime configuration."
      endpoint="/api/v1/admin/suppliers"
      columns={[
        { key: "name", label: "Supplier" },
        { key: "code", label: "Code" },
        { key: "driver", label: "Driver" },
        { key: "status", label: "Status" },
        { key: "priority", label: "Priority" },
        { key: "timeout_seconds", label: "Timeout" },
        { key: "max_retries", label: "Retries" },
      ]}
    />
  );
}