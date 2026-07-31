# AGCP Phases 13–15 Completion Bundle

This bundle completes the remaining core roadmap after Phase 12:

- **Phase 13:** Production Deployment & DevOps
- **Phase 14:** Security, Performance & UAT
- **Phase 15:** Launch, Documentation & Handover

## Important safety rules

- This patch does **not** delete Docker volumes or reset the database.
- It does **not** overwrite `.env`.
- Production deployment requires a separately prepared `.env.production`.
- Run the local completion script first, then perform server deployment only after verification.
- Never commit `.env`, `.env.production`, database dumps, encryption keys, or live credentials.

## Quick start on Windows

Extract this ZIP into the AGCP project root:

```text
C:\Projects\agcp
```

Then run:

```powershell
cd C:\Projects\agcp
Set-ExecutionPolicy -Scope Process Bypass -Force
.\scripts\upgrade-phase13-15.ps1
```

Verify all remaining phases:

```powershell
.\scripts\verify-phase13-15.ps1
```

## Production deployment

1. Copy `.env.production.example` to `.env.production`.
2. Replace every `CHANGE_ME_...` value.
3. Follow `docs/PHASE_13_PRODUCTION_DEPLOYMENT.md`.
4. On the Linux server run:

```bash
chmod +x scripts/server/*.sh
./scripts/server/preflight.sh
./scripts/server/deploy.sh
```

## Final release

After production UAT passes:

```powershell
.\scripts\release-phase15.ps1 -Version 1.0.0
```
