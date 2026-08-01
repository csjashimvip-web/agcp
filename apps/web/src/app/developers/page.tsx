import Link from "next/link";

const endpoints = [
  ["GET", "/api/reseller/v1/services", "services:read"],
  ["GET", "/api/reseller/v1/balance", "wallet:read"],
  ["POST", "/api/reseller/v1/orders", "orders:create"],
  ["GET", "/api/reseller/v1/orders", "orders:read"],
  ["GET", "/api/reseller/v1/orders/{orderId}", "orders:read"],
];

export default function DeveloperPortalPage() {
  return (
    <main className="store-page">
      <div className="page-heading">
        <div>
          <p className="eyebrow">AGCP Developer Platform</p>
          <h1>Reseller API</h1>
          <p>
            Bearer-token API with per-client abilities, rate limits,
            idempotent order creation and request audit logs.
          </p>
        </div>

        <Link className="ghost-link" href="/developer/openapi.json">
          OpenAPI JSON
        </Link>
      </div>

      <div className="table-card">
        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Method</th>
                <th>Endpoint</th>
                <th>Ability</th>
              </tr>
            </thead>
            <tbody>
              {endpoints.map(([method, endpoint, ability]) => (
                <tr key={`${method}:${endpoint}`}>
                  <td>{method}</td>
                  <td>
                    <code>{endpoint}</code>
                  </td>
                  <td>{ability}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </main>
  );
}