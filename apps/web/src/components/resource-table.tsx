"use client";

import { useEffect, useState } from "react";
import { useAdminContext } from "@/components/admin-shell";
import { apiFetch, ApiEnvelope } from "@/lib/agcp-api";

type Column = {
  key: string;
  label: string;
  render?: (value: unknown, row: Record<string, unknown>) => string;
};

export default function ResourceTable({
  title,
  description,
  endpoint,
  columns,
}: {
  title: string;
  description: string;
  endpoint: string;
  columns: Column[];
}) {
  const { tenantId, loading: contextLoading } = useAdminContext();
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  useEffect(() => {
    if (!tenantId) {
      setRows([]);
      return;
    }

    let active = true;
    setLoading(true);
    setError("");

    apiFetch<ApiEnvelope<Record<string, unknown>[]>>(endpoint, {}, tenantId)
      .then((result) => {
        if (active) setRows(result.data);
      })
      .catch((reason) => {
        if (active) {
          setError(reason instanceof Error ? reason.message : "Request failed.");
        }
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
  }, [endpoint, tenantId]);

  return (
    <div>
      <div className="page-heading">
        <div>
          <p className="eyebrow">Tenant-scoped operations</p>
          <h2>{title}</h2>
          <p>{description}</p>
        </div>
      </div>

      {!contextLoading && !tenantId && (
        <div className="empty-state">No active tenant membership is available.</div>
      )}

      {error && <div className="error-banner">{error}</div>}

      <div className="table-card">
        <div className="table-status">
          {loading ? "Loadingâ€¦" : `${rows.length} record${rows.length === 1 ? "" : "s"}`}
        </div>

        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                {columns.map((column) => (
                  <th key={column.key}>{column.label}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {!loading && rows.length === 0 ? (
                <tr>
                  <td colSpan={columns.length} className="empty-cell">
                    No records found.
                  </td>
                </tr>
              ) : (
                rows.map((row, index) => (
                  <tr key={String(row.id ?? index)}>
                    {columns.map((column) => {
                      const value = row[column.key];
                      return (
                        <td key={column.key}>
                          {column.render
                            ? column.render(value, row)
                            : String(value ?? "â€”")}
                        </td>
                      );
                    })}
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}