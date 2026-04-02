# Redeploy Runbook

## Purpose

Document branch-based redeploy behavior for crm.i-portal.me.

## Generated script

Installer creates:

- `scripts/redeploy_crm.sh`

## Manual execution

Redeploy current default branch:

```bash
bash scripts/redeploy_crm.sh
```

Redeploy explicit branch:

```bash
bash scripts/redeploy_crm.sh feature-branch-name
```

## What redeploy does

### Common behavior
- fetches target branch from origin
- checks out the branch
- hard-resets to `origin/<branch>`

### Docker mode
- rebuilds and recreates the app container
- rewrites `.env.docker` from protected env source
- validates host/container env alignment
- clears Laravel caches in container

### Regular mode
- runs Composer install
- runs `npm ci`
- runs asset build
- runs migrations
- runs seeders used by deployment flow

## Operational cautions

- Do not redeploy blindly to production without knowing which branch is intended.
- Confirm backups are working before major redeploys.
- In Docker mode, do not destroy volumes during normal redeploy.

## Recommended workflow

1. merge reviewed branch to master
2. pull/redeploy master in target environment
3. verify app health after deploy
4. confirm no env mismatch or migration issue occurred
