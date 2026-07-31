# AGCP Phase 14 Identity Test Helper Hotfix V3

Root cause: the production User model intentionally does not mass-assign
`email_verified_at` or `two_factor_confirmed_at`. The test helper passed both
fields to `User::create()`, so Laravel discarded them. The request then stopped
at the email-verification middleware and returned a generic 403 before the
admin 2FA middleware could run.

Run:

```powershell
cd C:\Projects\agcp
Set-ExecutionPolicy -Scope Process Bypass -Force
.\fix-agcp-phase14-identity-test-helper-v3.ps1
```

Then:

```powershell
.\scripts\verify-phase14.ps1
.\scripts\verify-phase15.ps1
```
