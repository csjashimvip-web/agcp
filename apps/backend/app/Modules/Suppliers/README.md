# Suppliers Module

Phase 5 owns supplier provider contracts, accounts, service mappings, deterministic routing, health scoring, queue submission, polling, failover and terminal-failure refunds.

Commerce publishes committed order events. Supplier-specific APIs remain isolated behind `SupplierProvider` adapters.
