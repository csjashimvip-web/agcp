# AGCP Phase 14 Final Fix

This patch fixes the actual uploaded project source:

1. The identity test helper now persists email verification and 2FA timestamps
   with `forceFill()` instead of mass assignment.
2. The Phase 14 backend regression runs each feature-test file in a separate
   PHP process, preventing process-level test state leakage.
3. The Next.js 16 / React 19 lint compatibility rules are scoped to the
   existing legacy client pages and API client files; the rest of ESLint stays enabled.

## Apply

Extract this ZIP into `C:\Projects\agcp` and allow replacement, then run:

```powershell
cd C:\Projects\agcp
Set-ExecutionPolicy -Scope Process Bypass -Force
.\apply-agcp-phase14-final-fix.ps1
```

After Phase 14 passes:

```powershell
.\scripts\verify-phase15.ps1
.\scripts\verify-phase13-15.ps1
```
