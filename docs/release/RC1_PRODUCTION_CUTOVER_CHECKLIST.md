# AGCP RC1 Production Cutover Checklist

Production traffic must not be opened merely because a build compiles.

## Automated gates

- The exact Git commit has an accepted staging run.
- The exact Git commit has a zero-critical security audit.
- API contract audit passes.
- Laravel full tests pass.
- Next.js TypeScript and production build pass.
- A performance baseline exists.
- A backup has been verified by a restore drill.
- Runtime readiness is green.

## Manual critical gates

- DNS and TLS are verified.
- Queue worker and scheduler are running under process supervision.
- Payment-provider credentials are verified in production.
- Supplier credentials and routing are verified.
- An encrypted off-server backup is confirmed.
- Monitoring and alerting are armed.
- A controlled checkout smoke test succeeds.
- A controlled refund/compensation smoke test succeeds.
- A rollback owner is assigned and reachable.

## Financial cutover rules

Do not modify wallet or ledger balances manually to repair cutover issues.
Use transactional services and explicit compensation/reconciliation workflows.

## Database rollback rule

Do not automatically run `php artisan migrate:rollback` after a failed
production deployment. Review whether data migrations are reversible and choose
a forward-fix or verified restore when appropriate.

## External delivery rule

Real outbound email/webhooks require both:

- `AGCP_EXTERNAL_DELIVERY_ENABLED=true`
- the specific provider/subscription enabled

Keep outbound delivery disabled until destination ownership, secrets, and
monitoring are confirmed.

## Traffic opening

AGCP's production cutover gate must report `traffic_open_allowed=true`.
Only then may the operator mark the cutover as traffic-open.