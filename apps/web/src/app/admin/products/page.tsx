import ResourceTable from "@/components/resource-table";

export default function ProductsPage() {
  return (
    <ResourceTable
      title="Products"
      description="Catalog services and commerce pricing for the active tenant."
      endpoint="/api/v1/admin/products"
      columns={[
        { key: "sku", label: "SKU" },
        { key: "name", label: "Name" },
        { key: "type", label: "Type" },
        { key: "status", label: "Status" },
        { key: "currency", label: "Currency" },
        { key: "price_minor", label: "Price" },
        { key: "cost_minor", label: "Cost" },
      ]}
    />
  );
}