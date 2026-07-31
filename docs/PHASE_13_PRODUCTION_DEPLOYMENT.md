# Phase 13 — Production Deployment & DevOps

## Deployment architecture

The supported production layout is:

```text
Internet
  → CyberPanel/OpenLiteSpeed HTTPS virtual host
  → reverse proxy to 127.0.0.1:8080
  → AGCP Nginx container
  → Next.js and Laravel containers
  → private MySQL and Redis network
```

MySQL and Redis are not published to the public internet.

## Server preparation

Install:

- Docker Engine
- Docker Compose v2
- Git
- Curl
- A CyberPanel website and HTTPS certificate

Clone the repository into a deployment directory owned by a non-root deployment user.

## Environment

```bash
cp .env.production.example .env.production
chmod 600 .env.production
```

Replace all `CHANGE_ME_...` values. Use independent high-entropy values for:

- `APP_KEY`
- database password
- MySQL root password
- Redis password
- passkey secret
- payment webhook secret
- backup encryption key
- SMTP password

Do not reuse local development secrets.

## CyberPanel reverse proxy

Configure the website to proxy requests to:

```text
http://127.0.0.1:8080
```

Preserve these headers:

```text
Host
X-Real-IP
X-Forwarded-For
X-Forwarded-Proto
```

The public URL must use HTTPS. The container port remains bound to loopback only.

## First deployment

```bash
chmod +x scripts/server/*.sh
./scripts/server/preflight.sh
./scripts/server/deploy.sh
```

After deployment:

```bash
docker compose --env-file .env.production -f docker-compose.production.yml ps
curl -fsS https://YOUR_DOMAIN/api/v1/health/live
curl -fsS https://YOUR_DOMAIN/api/v1/health/ready
```

## Queue and scheduler

These services must stay running:

- `queue-critical`
- `queue-default`
- `scheduler`

Docker restart policies automatically restart them after server reboot.

## CI/CD

The bundle includes:

```text
.github/workflows/ci.yml
.github/workflows/release.yml
```

CI runs backend tests, frontend lint/typecheck/build, and production Compose validation. Release tags create a clean source archive and SHA-256 checksum.

## Rollback

```bash
./scripts/server/rollback.sh v1.0.0
```

Application rollback does not automatically reverse database migrations. Database recovery must follow the disaster recovery runbook.
