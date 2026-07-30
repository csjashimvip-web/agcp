# Contributing to AGCP

## Branch and commit policy

- Create a focused branch for each change.
- Keep module boundaries intact.
- Add or update tests for security and behavior changes.
- Never commit `.env`, credentials, API tokens, recovery codes, or customer data.

## Required checks

```bash
make test
make lint
make verify
```

Identity changes must test tenant isolation, inactive-account behavior, permission checks, token abilities, and any required authentication assurance.

## Commit examples

```text
feat(identity): add tenant membership invitation flow
fix(auth): reject revoked sessions before controller execution
test(identity): cover platform role assignment boundary
docs(security): document passkey origin requirements
```
