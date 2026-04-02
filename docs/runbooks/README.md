# Runbooks

This folder contains operator-facing runbooks for crm.i-portal.me.

## Runbooks

- `deploy.md` — deployment modes and initial install flow
- `backup.md` — database backup operations and retention
- `restore.md` — safe restore procedure and cautions
- `redeploy.md` — branch-based redeploy procedure

## Why this exists

The installer script already knows how to perform these tasks, but operational knowledge should not live only inside shell scripts. These runbooks make the expected operational steps explicit and reviewable.
