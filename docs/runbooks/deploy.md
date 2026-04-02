# Deploy Runbook

## Purpose

Document how to deploy `crm.i-portal.me` using the supported installer flow.

## Supported modes

- Docker mode
- Regular mode

## Primary installer

```bash
sudo bash scripts/install_crm.sh
```

## Required decisions

During install you choose:
- mode: `docker` or `regular`
- domain
- app directory
- repo and branch
- database credentials
- web server (`nginx` or `apache`)
- SSL mode (`none`, `existing`, `letsencrypt`)
- backup target (`local`, `s3`, `both`)

## Docker mode summary

Docker mode:
- bootstraps Docker if missing
- writes `.env.docker`
- writes `docker-compose.override.yml`
- starts app + db containers
- preserves data through named volumes
- can configure reverse proxy and SSL

### Important caution

Do not run:

```bash
docker compose down -v
```

unless you intentionally want to destroy DB/storage volumes.

## Regular mode summary

Regular mode:
- installs host prerequisites if needed
- writes `.env`
- installs Composer and npm dependencies
- runs migrations and seeders
- configures web server vhost
- can configure SSL

## Production-safe expectations

Use at least:
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://your-domain`
- `SESSION_DOMAIN=your-domain`
- `SESSION_SECURE_COOKIE=true`
- `SESSION_SAME_SITE=lax`

## Validation after deployment

Verify:
- app loads over expected domain
- login works
- dashboard loads
- DB-backed flows work
- storage is writable
- backup script exists
- SSL works if configured

## Follow-up

After deployment, also review:
- `backup.md`
- `restore.md`
- `redeploy.md`
