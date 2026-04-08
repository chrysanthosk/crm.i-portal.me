# Local Docker Test Environment

## Purpose

Document the non-production Docker-based test workspace used for safer local validation of `crm.i-portal.me`.

## Current test machine

A dedicated CRM test machine is used for Docker validation.

Workspace path on the test machine:
- `/opt/crm-test/crm.i-portal.me`

## Why this exists

This environment is meant to reduce blind CI-first debugging and allow faster validation of:
- Docker boot issues
- Laravel/runtime behavior
- route/controller bugs
- branch-based validation before merge

## Expected usage

### Start the stack

```bash
cd /opt/crm-test/crm.i-portal.me
sudo docker compose up -d --build
```

### Stop the stack

```bash
cd /opt/crm-test/crm.i-portal.me
sudo docker compose down
```

### View logs

```bash
cd /opt/crm-test/crm.i-portal.me
sudo docker compose logs --tail=200
```

### Open app shell

```bash
cd /opt/crm-test/crm.i-portal.me
sudo docker compose exec crm-app sh
```

## Safety rules

- This environment is for testing, not production.
- Do not bind real production DNS to it unless intentionally promoting it to a formal environment.
- Do not casually destroy named volumes if you want to preserve local test data.
- Keep secrets local to the test machine; do not commit runtime secrets.

## Recommendation

Use this environment for branch validation when behavior is hard to diagnose through static code review alone.
