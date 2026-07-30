# AGCP Phase 11 Payment Hotfix 1

This hotfix corrects Eloquent relationship foreign keys for payment provider accounts.

The database uses `provider_account_id`, while Laravel's default relationship convention inferred `payment_provider_account_id` from the model name. No migration or data reset is required.

Apply the patch files over the existing project and run:

```powershell
cd C:\Projects\agcp
Set-ExecutionPolicy -Scope Process Bypass -Force
.\scripts\repair-phase11-payment-hotfix1.ps1
```

Then run:

```powershell
.\scripts\verify-phase11.ps1
```
