# Phase 8 Completion Report

## Scope

Phase 8 delivers explainable analytics, sales forecasting, customer segmentation, supplier recommendation and AI-assisted operational insight foundations.

## Delivered

- Tenant-scoped analytics schema and models
- 30-day commerce KPI snapshots
- Deterministic 14-day sales forecasts
- RFM-style customer segmentation
- Explainable supplier ranking with confidence and candidate evidence
- Local deterministic insight provider
- Model-run status, errors and metrics
- Reports queue job and daily scheduler command
- Admin API and responsive `/admin/analytics` dashboard
- Analytics permissions and SaaS feature entitlements
- Feature tests and prior-phase regression verification script

## Safety boundaries

- No automatic wallet movement is performed by analytics.
- No supplier route is changed automatically from a recommendation.
- No external AI API receives tenant data.
- Fraud and high-risk decisions remain under the Phase 6 rule/review controls.
- Every calculation is reproducible from stored evidence.

## Upgrade

Use `scripts/upgrade-phase8.ps1`, followed by `scripts/verify-phase8.ps1`.
