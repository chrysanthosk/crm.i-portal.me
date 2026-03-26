# CRM Installation Guide

This repo includes an interactive-first installer:

- `scripts/install_crm.sh`

It now targets a **fresh Ubuntu host** much better than before.

It supports:
- **Docker installation**
- **Regular installation**
- **host bootstrap / prerequisite installation**
- **SSL mode selection**
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

The installer asks for:
- install mode (`docker` or `regular`)
- domain
- app directory
- repo / branch
- DB credentials
- web server choice
- SSL mode:
  - `none`
  - `existing`
  - `letsencrypt`
- backup target:
  - `local`
  - `s3`
  - `both`
- S3 credentials if needed
- whether to create cron
- whether to create/update the vhost

It still supports non-interactive flags for automation.

---

## Fresh Ubuntu behavior

### Docker mode
On a fresh Ubuntu host, the installer will now attempt to install:
- `git`
- `curl`
- `ca-certificates`
- Docker Engine
- Docker Compose plugin
- nginx or apache if selected
- certbot if Let’s Encrypt is selected
- AWS CLI if S3 backups are selected
- it also generates the Laravel `APP_KEY` inside the running app container after first boot

### Regular mode
On a fresh Ubuntu host, the installer will now attempt to install:
- `git`
- `curl`
- `ca-certificates`
- PHP CLI / FPM and common Laravel extensions
- Composer
- Node.js / npm
- MySQL client
- nginx or apache if selected
- certbot if Let’s Encrypt is selected
- AWS CLI if S3 backups are selected
- it generates the Laravel `APP_KEY` using `php artisan key:generate --force`

This makes the installer much more suitable for clean Ubuntu servers.

---

## SSL support

The installer now supports three SSL modes:

### 1. `none`
- HTTP only
- useful for first-pass testing or reverse-proxy setups handled elsewhere

### 2. `existing`
You provide:
- certificate path
- private key path

The installer writes SSL-enabled nginx/apache vhost config using those files.

### 3. `letsencrypt`
You provide:
- email address
- domain

The installer uses certbot to obtain and configure a Let’s Encrypt certificate.

> For Let’s Encrypt to work, the domain must already resolve to the server and port 80 must be reachable from the public internet.

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
- bootstraps Docker if missing
- clones/updates the repo
- writes `.env.docker`
- writes `docker-compose.override.yml`
- starts **app + DB** in Docker
- keeps DB/storage in named volumes
- optionally creates nginx/apache reverse-proxy config
- optionally provisions SSL
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
- bootstraps most host prerequisites if missing
- clones/updates the repo
- writes `.env`
- runs Composer / npm
- runs migrations + seeders
- configures nginx or apache using a dedicated vhost
- optionally provisions SSL
- installs nightly DB backup cron
- creates helper scripts:
  - `scripts/backup_db.sh`
  - `scripts/restore_db.sh`
  - `scripts/redeploy_crm.sh`

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

## Example: non-interactive Docker with Let’s Encrypt

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
  --ssl-mode letsencrypt \
  --ssl-email admin@example.com \
  --backup-target both \
  --backup-s3-uri s3://my-private-bucket/crm \
  --aws-region eu-central-1 \
  --aws-access-key-id AKIA... \
  --aws-secret-access-key 'super-secret'
```

---

## Example: non-interactive Regular with existing certs

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
  --ssl-mode existing \
  --ssl-cert-path /etc/letsencrypt/live/crm.example.com/fullchain.pem \
  --ssl-key-path /etc/letsencrypt/live/crm.example.com/privkey.pem \
  --backup-target local
```

---

## Recommended test order

1. test Docker mode on a fresh Ubuntu VM
2. test Docker mode again with SSL enabled (`existing` or `letsencrypt`)
3. verify redeploy preserves DB/storage
4. test backup locally
5. test S3 upload with a private bucket
6. test Regular mode on a separate fresh Ubuntu VM

---

## Current hardening status

This branch now includes:
- destructive-action confirmation in `restore_db.sh`
- improved S3 upload handling for private buckets / custom endpoints
- fresh-Ubuntu bootstrap for Docker and Regular installation modes
- Laravel-native APP_KEY generation
- SSL mode support for `none`, `existing`, and `letsencrypt`
- automatic AWS CLI installation with official-installer fallback when apt is insufficient

## Still recommended before production use

- perform one real end-to-end dry run in Docker mode
- perform one real end-to-end dry run in Regular mode
- verify Let’s Encrypt flow against a real public DNS name
- tighten any environment-specific package/version assumptions found during those tests
