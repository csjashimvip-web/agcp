import ResourceTable from "@/components/resource-table";

export default function OrdersPage() {
  return (
    <ResourceTable
      title="Orders"
      description="Recent tenant orders and their transactional state."
      endpoint="/api/v1/admin/orders"
      columns={[
        { key: "order_number", label: "Order" },
        { key: "customer_name", label: "Customer" },
        { key: "status", label: "Status" },
        { key: "currency", label: "Currency" },
        { key: "total_minor", label: "Total" },
        { key: "created_at", label: "Created" },
      ]}
    />
  );
}