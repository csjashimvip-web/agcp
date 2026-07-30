# Phase 8 Build Manifest

## Runtime

- Laravel 13 / PHP 8.4 backend
- Next.js 16 / React 19 frontend
- MySQL 8.4
- Redis queues and cache
- Nginx gateway

## Phase 8 modules

- `Modules/Analytics/Application/Services/AnalyticsPipelineService`
- KPI snapshot, segmentation and forecasting services
- Explainable supplier recommendation service
- Local deterministic insight provider
- Reports-queue refresh job
- Daily `analytics:refresh` command
- Admin analytics API and page

## Phase 8 automated tests included

- `AnalyticsAiTest`
- Phase 7 SaaS/plugin regression
- Phase 6 rules/fraud regression
- Phase 5 supplier regression
- Frontend TypeScript validation

## Migration

- `2026_07_30_700001_create_ai_analytics_tables.php`
