# Restore Runbook

## Purpose

Document the safe restore procedure for database backups.

## Generated script

Installer creates:

- `scripts/restore_db.sh`

## Manual execution

```bash
bash scripts/restore_db.sh /path/to/backup.sql.gz
```

## Safety behavior

The restore script requires an explicit confirmation:
- type `RESTORE`

This is intentional because restore overwrites live database state.

## Before restoring

Checklist:
- confirm you selected the correct backup file
- confirm target environment (local, staging, production)
- confirm current state is backed up before overwrite
- confirm expected downtime/impact

## Docker mode behavior

Restore is piped into the Docker MySQL container.

## Regular mode behavior

Restore is piped directly into MySQL using configured credentials.

## After restore

Validate:
- app can connect
- login works
- expected records exist
- no migration/schema mismatch exists
- audit any follow-up operational actions needed

## Recommendation

Use restore in staging/test first when validating backup strategy. Do not make production the first place you discover restore problems.
