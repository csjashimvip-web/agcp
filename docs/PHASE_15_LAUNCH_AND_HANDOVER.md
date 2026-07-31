# Phase 15 — Launch, Documentation & Handover

## Launch gate

Do not launch until all items are complete:

- Production `.env.production` has no placeholders.
- Domain and HTTPS work.
- SMTP sends real email.
- Payment provider credentials are live and webhook signatures pass.
- Admin password is private and 2FA is confirmed.
- Latest database backup is completed and verified.
- Readiness endpoint passes.
- Queue workers and scheduler are running.
- Phase 14 UAT is signed off.
- Monitoring and incident contacts are assigned.

## Release preparation

```powershell
git status
git add .
git commit -m "release: complete AGCP core platform phases 13-15"
git push
```

After the worktree is clean:

```powershell
.\scripts\release-phase15.ps1 -Version 1.0.0 -CreateTag
git push origin v1.0.0
```

## Handover package

The handover must include:

- repository access
- production server access
- domain and DNS ownership
- CyberPanel access
- SMTP provider ownership
- payment provider ownership
- offline backup encryption key
- database and Redis credentials
- monitoring contacts
- operations and disaster recovery runbooks

Secrets must be transferred through a secure password manager, not email or chat.

## Post-launch checks

Immediately after launch:

1. Confirm home, login, security and admin pages.
2. Perform one controlled customer transaction.
3. Confirm notification delivery.
4. Verify queue jobs complete.
5. Create and verify an encrypted backup.
6. Confirm readiness remains passed.
7. Review application, Nginx and database logs.
