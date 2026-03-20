# CRM Installation Guide

This repo includes an interactive-first installer:

- `scripts/install_crm.sh`

It supports:
- **Docker installation**
- **Regular installation**
- **local backups**
- **private S3 backups**
- **local + S3 backups together**
- helper scripts for **backup**, **restore**, and **redeploy**

## Installer style

Default behavior is **interactive**.

Run:

```bash
sudo bash scripts/install_crm.sh
```

The installer will ask for:
- install mode (`docker` or `regular`)
- domain
- app directory
- repo / branch
- DB credentials
- web server choice
- backup target:
  - `local`
  - `s3`
  - `both`
- S3 credentials if needed
- whether to create cron
- whether to create/update the vhost

It also still supports non-interactive flags for automation.

---

## Backup strategy

Recommended:
- keep **local rotating backups**
- optionally also push to **private S3**

This gives:
- fast local restore
- off-host disaster recovery
- safer operations during redeploys

### Private S3 credentials

If backup target is `s3` or `both`, the installer stores credentials in:

- `/etc/crm-backup.env`

This file is created as:
- root-owned
- `chmod 600`

So the bucket does **not** need to be public.

---

## Docker mode

### What it does
- clones/updates the repo
- writes `.env.docker`
- writes `docker-compose.override.yml`
- starts **app + DB** in Docker
- keeps DB/storage in named volumes
- optionally creates nginx/apache reverse-proxy config
- installs nightly DB backup cron
- creates helper scripts:
  - `scripts/backup_db.sh`
  - `scripts/restore_db.sh`
  - `scripts/redeploy_crm.sh`

### Important safety rule
Normal redeploys preserve data because Docker uses named volumes.

Do **not** run:

```bash
docker compose down -v
```

unless you intentionally want to destroy DB/storage.

---

## Regular mode

### What it does
- clones/updates the repo
- writes `.env`
- runs Composer / npm
- runs migrations + seeders
- configures nginx or apache using a dedicated vhost
- installs nightly DB backup cron
- creates helper scripts:
  - `scripts/backup_db.sh`
  - `scripts/restore_db.sh`
  - `scripts/redeploy_crm.sh`

### Assumes host already has
- PHP
- Composer
- npm/node
- MySQL access
- nginx or apache

---

## Backups

### Generated files
- backup script: `scripts/backup_db.sh`
- restore script: `scripts/restore_db.sh`
- redeploy script: `scripts/redeploy_crm.sh`

### Cron
By default the installer creates:
- `/etc/cron.d/crm-db-backup`

Default schedule:
- daily at **02:15**

Default local backup directory:
- `/var/backups/crm`

---

## S3 / private bucket support

If you choose `s3` or `both`, the installer asks for:
- access key id
- secret access key
- region
- S3 bucket URI
- optional endpoint URL
- optional path-style mode

Those values are stored in:
- `/etc/crm-backup.env`

The backup script loads that env file automatically.

---

## Example: interactive

```bash
sudo bash scripts/install_crm.sh
```

---

## Example: non-interactive Docker

```bash
sudo bash scripts/install_crm.sh \
  --non-interactive \
  --mode docker \
  --domain crm.example.com \
  --app-dir /opt/crm.i-portal.me \
  --db-name crm \
  --db-user crm \
  --db-pass 'secret' \
  --db-root-pass 'rootsecret' \
  --backup-target both \
  --backup-dir /var/backups/crm \
  --backup-s3-uri s3://my-private-bucket/crm \
  --aws-region eu-central-1 \
  --aws-access-key-id AKIA... \
  --aws-secret-access-key 'super-secret'
```

---

## Example: non-interactive Regular

```bash
sudo bash scripts/install_crm.sh \
  --non-interactive \
  --mode regular \
  --domain crm.example.com \
  --app-dir /var/www/crm.i-portal.me \
  --db-name crm \
  --db-user crm \
  --db-pass 'secret' \
  --web-server nginx \
  --php-fpm-sock /run/php/php8.4-fpm.sock \
  --backup-target local
```

---

## Suggested next refinement after this

The next hardening pass should be:
- test installer on a clean VM in Docker mode
- test installer on a clean VM in regular mode
- improve restore workflow with pre-restore safety prompts
- optionally add TLS helper notes/templates
