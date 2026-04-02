# Backup Runbook

## Purpose

Document the database backup flow created by the installer.

## Generated script

Installer creates:

- `scripts/backup_db.sh`

## Default local backup location

- `/var/backups/crm`

## Default retention

- 14 days

## Default schedule

If cron is enabled by installer:
- `/etc/cron.d/crm-db-backup`
- runs daily at `02:15`

## What the backup does

- creates a compressed SQL dump
- verifies output is non-empty
- rotates old local backups
- optionally uploads to S3 if configured

## Manual execution

Run:

```bash
bash scripts/backup_db.sh
```

## S3 mode

If backup target is `s3` or `both`, credentials are stored in:

- `/etc/crm-backup.env`

Expected protections:
- root-owned
- `chmod 600`

## Validate backup health

Check:
- a new `.sql.gz` file exists
- file is non-zero in size
- cron log does not show errors
- S3 object appears when S3 is enabled

## Operational recommendation

Periodically test restore from a real backup. A backup that has never been restored is only a hopeful theory.
