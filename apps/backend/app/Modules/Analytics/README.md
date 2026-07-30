# Analytics Module

The Analytics module is a tenant-scoped, read-oriented decision-support boundary.

It owns:

- KPI snapshots
- customer segmentation
- sales forecasts
- supplier recommendations
- AI-assisted insights
- model-run history

It does not own wallet mutation, order state transitions, fraud approvals or supplier routing changes. Those remain in their source modules.
