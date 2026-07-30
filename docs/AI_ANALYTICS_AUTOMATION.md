# AGCP Explainable AI and Analytics Architecture

Phase 8 adds a tenant-isolated decision-support layer. The default provider is deliberately deterministic and local: tenant order, supplier and fraud data is not sent to an external model.

## Pipeline

```text
Orders + Customers + Suppliers + Fraud Signals
                    ↓
          Analytics Pipeline Run
                    ↓
├── 30-day KPI snapshot
├── RFM-style customer segmentation
├── Weighted moving-average sales forecast
├── Explainable supplier recommendations
├── Operational insight generation
└── Immutable model-run and audit records
```

## Explainability

Every supplier recommendation includes candidate scores, cost, health, success rate and latency evidence. Forecasts include the historical basis period, method, confidence and daily points. Insights store evidence and recommended actions rather than an opaque answer.

## Tables

- `analytics_snapshots`
- `customer_segments`
- `sales_forecasts`
- `supplier_recommendations`
- `ai_insights`
- `ai_model_runs`

All tenant-owned records include `tenant_id` and are queried through the active tenant context.

## Default models

- Forecast: `weighted-moving-average-v1`
- Insight provider: `deterministic`
- Insight model version: `agcp-explainable-v1`
- Supplier ranking: `explainable-balanced-v1`

These are decision-support tools. Fraud blocks, wallet movements and supplier routing remain controlled by the existing deterministic transaction, rule and approval systems.

## Automation

- Daily scheduler: `analytics:refresh` at 02:10
- Queue job: `RefreshTenantAnalytics` on the `reports` queue
- Manual synchronous refresh: `POST /api/v1/admin/analytics/refresh`
- Manual CLI refresh: `php artisan analytics:refresh --tenant=araabi-global`

## External AI providers

The `AiInsightProvider` contract allows a reviewed provider to be added later. Production use must add data-minimization, tenant consent, provider allow-listing, secret management, retention controls and human review. Phase 8 does not bundle or call a third-party AI API.
