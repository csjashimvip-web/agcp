# AGCP Phase 7 Upgrade Patch

This patch upgrades the verified AGCP Phase 6 project to Phase 7: Multi-Tenant SaaS Control Plane and Plugin Marketplace Foundation.

1. Preserve the existing `.env` file.
2. Copy all patch contents into the AGCP project root and choose **Replace All**.
3. Run `scripts/upgrade-phase7.ps1` from PowerShell.
4. Run `scripts/verify-phase7.ps1` after the upgrade.

The upgrade does not run `migrate:fresh`, `db:wipe`, or `docker compose down -v`.
