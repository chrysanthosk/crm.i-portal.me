#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'EOF'
CRM installer

Interactive mode (default):
  sudo bash scripts/install_crm.sh

Non-interactive mode:
  sudo bash scripts/install_crm.sh --non-interactive [options]

Core options:
  --mode docker|regular
  --domain example.com
  --app-dir /opt/crm.i-portal.me
  --app-name "CRM"
  --branch master
  --repo git@github.com:chrysanthosk/crm.i-portal.me.git

Database options:
  --db-name crm
  --db-user crm
  --db-pass 'secret'
  --db-root-pass 'rootsecret'      Docker mode only

Web / runtime:
  --web-server auto|nginx|apache
  --php-bin /usr/bin/php
  --php-fpm-sock /run/php/php8.4-fpm.sock
  --app-port 8088                  Docker mode only

Backups:
  --backup-target local|s3|both
  --backup-dir /var/backups/crm
  --backup-retention-days 14
  --backup-s3-uri s3://bucket/path
  --aws-region eu-central-1
  --aws-access-key-id AKIA...
  --aws-secret-access-key secret
  --aws-endpoint-url https://s3.example.com
  --aws-path-style true|false

Flags:
  --non-interactive
  --skip-backup-cron
  --skip-vhost
  -h, --help
EOF
}

require_root() {
  if [[ ${EUID:-$(id -u)} -ne 0 ]]; then
    echo "Please run as root (use sudo)." >&2
    exit 1
  fi
}

need_cmd() {
  command -v "$1" >/dev/null 2>&1 || { echo "Missing required command: $1" >&2; exit 1; }
}

random_b64() {
  openssl rand -base64 24 | tr -d '\n'
}

prompt() {
  local var_name="$1"
  local label="$2"
  local default_value="${3:-}"
  local secret="${4:-0}"
  local value=""

  if [[ -n "$default_value" ]]; then
    if [[ "$secret" == "1" ]]; then
      read -r -s -p "${label} [hidden, press Enter to keep current]: " value
      echo
      value="${value:-$default_value}"
    else
      read -r -p "${label} [${default_value}]: " value
      value="${value:-$default_value}"
    fi
  else
    if [[ "$secret" == "1" ]]; then
      while [[ -z "$value" ]]; do
        read -r -s -p "${label}: " value
        echo
      done
    else
      while [[ -z "$value" ]]; do
        read -r -p "${label}: " value
      done
    fi
  fi

  printf -v "$var_name" '%s' "$value"
}

choose_one() {
  local var_name="$1"
  local label="$2"
  local default_value="$3"
  shift 3
  local options=("$@")
  local joined
  joined=$(IFS=/; echo "${options[*]}")
  local value=""

  while :; do
    read -r -p "${label} (${joined}) [${default_value}]: " value
    value="${value:-$default_value}"
    for opt in "${options[@]}"; do
      if [[ "$value" == "$opt" ]]; then
        printf -v "$var_name" '%s' "$value"
        return 0
      fi
    done
    echo "Invalid choice: $value" >&2
  done
}

confirm_yes_no() {
  local var_name="$1"
  local label="$2"
  local default_value="${3:-yes}"
  local answer=""
  local normalized_default="yes"
  [[ "$default_value" == "no" ]] && normalized_default="no"

  while :; do
    read -r -p "${label} [${normalized_default}]: " answer
    answer="${answer:-$normalized_default}"
    case "$answer" in
      y|Y|yes|YES) printf -v "$var_name" '%s' "1"; return 0 ;;
      n|N|no|NO) printf -v "$var_name" '%s' "0"; return 0 ;;
      *) echo "Please answer yes or no." >&2 ;;
    esac
  done
}


apt_install() {
  export DEBIAN_FRONTEND=noninteractive
  apt-get update -y
  apt-get install -y "$@"
}

ensure_base_packages() {
  apt_install ca-certificates curl gnupg lsb-release git unzip sed openssl
}

ensure_docker_installed() {
  if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
    return 0
  fi

  ensure_base_packages
  install -m 0755 -d /etc/apt/keyrings
  if [[ ! -f /etc/apt/keyrings/docker.asc ]]; then
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
    chmod a+r /etc/apt/keyrings/docker.asc
  fi

  . /etc/os-release
  echo \
    "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu ${VERSION_CODENAME} stable" \
    > /etc/apt/sources.list.d/docker.list

  apt-get update -y
  apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
  systemctl enable --now docker
}

ensure_aws_cli() {
  command -v aws >/dev/null 2>&1 && return 0
  apt_install awscli
}

ensure_nginx() {
  command -v nginx >/dev/null 2>&1 || apt_install nginx
  systemctl enable --now nginx
}

ensure_apache() {
  if command -v apache2ctl >/dev/null 2>&1 || command -v httpd >/dev/null 2>&1; then
    :
  else
    apt_install apache2
  fi
  systemctl enable --now apache2 2>/dev/null || systemctl enable --now httpd
}

ensure_regular_stack() {
  ensure_base_packages
  apt_install software-properties-common
  apt_install php-cli php-fpm php-mysql php-xml php-mbstring php-curl php-zip php-gd php-intl php-bcmath php-sqlite3 composer nodejs npm mysql-client
  systemctl enable --now php8.4-fpm 2>/dev/null || systemctl enable --now php-fpm 2>/dev/null || true
}

bootstrap_host() {
  ensure_base_packages

  if [[ "$MODE" == "docker" ]]; then
    ensure_docker_installed
    if [[ "$WEB_SERVER" == "nginx" ]]; then
      ensure_nginx
    elif [[ "$WEB_SERVER" == "apache" ]]; then
      ensure_apache
    fi
  else
    ensure_regular_stack
    if [[ "$WEB_SERVER" == "nginx" ]]; then
      ensure_nginx
    elif [[ "$WEB_SERVER" == "apache" ]]; then
      ensure_apache
    fi
  fi

  if [[ "$BACKUP_TARGET" == "s3" || "$BACKUP_TARGET" == "both" ]]; then
    ensure_aws_cli
  fi
}

detect_web_server() {
  if systemctl list-unit-files 2>/dev/null | grep -q '^nginx.service'; then
    echo nginx
  elif systemctl list-unit-files 2>/dev/null | grep -Eq '^(apache2|httpd)\.service'; then
    echo apache
  else
    echo nginx
  fi
}

MODE=""
DOMAIN=""
APP_DIR=""
APP_NAME="CRM"
BRANCH="master"
REPO="git@github.com:chrysanthosk/crm.i-portal.me.git"
DB_NAME=""
DB_USER=""
DB_PASS=""
DB_ROOT_PASS=""
WEB_SERVER="auto"
PHP_BIN="php"
PHP_FPM_SOCK=""
APP_PORT="8088"
BACKUP_TARGET="local"
BACKUP_DIR="/var/backups/crm"
BACKUP_RETENTION_DAYS="14"
BACKUP_S3_URI=""
AWS_REGION=""
AWS_ACCESS_KEY_ID=""
AWS_SECRET_ACCESS_KEY=""
AWS_ENDPOINT_URL=""
AWS_PATH_STYLE="false"
SKIP_BACKUP_CRON="0"
SKIP_VHOST="0"
NON_INTERACTIVE="0"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --mode) MODE="$2"; shift 2 ;;
    --domain) DOMAIN="$2"; shift 2 ;;
    --app-dir) APP_DIR="$2"; shift 2 ;;
    --app-name) APP_NAME="$2"; shift 2 ;;
    --branch) BRANCH="$2"; shift 2 ;;
    --repo) REPO="$2"; shift 2 ;;
    --db-name) DB_NAME="$2"; shift 2 ;;
    --db-user) DB_USER="$2"; shift 2 ;;
    --db-pass) DB_PASS="$2"; shift 2 ;;
    --db-root-pass) DB_ROOT_PASS="$2"; shift 2 ;;
    --web-server) WEB_SERVER="$2"; shift 2 ;;
    --php-bin) PHP_BIN="$2"; shift 2 ;;
    --php-fpm-sock) PHP_FPM_SOCK="$2"; shift 2 ;;
    --app-port) APP_PORT="$2"; shift 2 ;;
    --backup-target) BACKUP_TARGET="$2"; shift 2 ;;
    --backup-dir) BACKUP_DIR="$2"; shift 2 ;;
    --backup-retention-days) BACKUP_RETENTION_DAYS="$2"; shift 2 ;;
    --backup-s3-uri) BACKUP_S3_URI="$2"; shift 2 ;;
    --aws-region) AWS_REGION="$2"; shift 2 ;;
    --aws-access-key-id) AWS_ACCESS_KEY_ID="$2"; shift 2 ;;
    --aws-secret-access-key) AWS_SECRET_ACCESS_KEY="$2"; shift 2 ;;
    --aws-endpoint-url) AWS_ENDPOINT_URL="$2"; shift 2 ;;
    --aws-path-style) AWS_PATH_STYLE="$2"; shift 2 ;;
    --skip-backup-cron) SKIP_BACKUP_CRON="1"; shift ;;
    --skip-vhost) SKIP_VHOST="1"; shift ;;
    --non-interactive) NON_INTERACTIVE="1"; shift ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Unknown argument: $1" >&2; usage; exit 1 ;;
  esac
done

require_root

if [[ "$NON_INTERACTIVE" != "1" ]]; then
  echo "== CRM interactive installer =="
  choose_one MODE "Installation mode" "docker" docker regular
  prompt DOMAIN "Domain / host name" "${DOMAIN:-crm.example.com}"
  if [[ "$MODE" == "docker" ]]; then
    prompt APP_DIR "Application directory" "${APP_DIR:-/opt/crm.i-portal.me}"
  else
    prompt APP_DIR "Application directory" "${APP_DIR:-/var/www/crm.i-portal.me}"
  fi
  prompt APP_NAME "Application name" "$APP_NAME"
  prompt BRANCH "Git branch" "$BRANCH"
  prompt REPO "Git repository" "$REPO"
  prompt DB_NAME "Database name" "${DB_NAME:-crm_i_portal_me}"
  prompt DB_USER "Database user" "${DB_USER:-crm_i_portal_me}"
  prompt DB_PASS "Database password" "$DB_PASS" 1
  if [[ "$MODE" == "docker" ]]; then
    prompt DB_ROOT_PASS "Docker MySQL root password" "$DB_ROOT_PASS" 1
    prompt APP_PORT "Docker app port (bound to 127.0.0.1)" "$APP_PORT"
  fi
  choose_one WEB_SERVER "Web server" "$(detect_web_server)" auto nginx apache
  if [[ "$MODE" == "regular" && "$WEB_SERVER" == "nginx" ]]; then
    prompt PHP_FPM_SOCK "PHP-FPM socket" "${PHP_FPM_SOCK:-/run/php/php8.4-fpm.sock}"
  fi
  prompt PHP_BIN "PHP binary" "$PHP_BIN"
  choose_one BACKUP_TARGET "Backup target" "$BACKUP_TARGET" local s3 both
  prompt BACKUP_DIR "Local backup directory" "$BACKUP_DIR"
  prompt BACKUP_RETENTION_DAYS "Backup retention days" "$BACKUP_RETENTION_DAYS"
  if [[ "$BACKUP_TARGET" == "s3" || "$BACKUP_TARGET" == "both" ]]; then
    prompt BACKUP_S3_URI "S3 bucket URI (e.g. s3://bucket/path)" "$BACKUP_S3_URI"
    prompt AWS_REGION "AWS region" "${AWS_REGION:-eu-central-1}"
    prompt AWS_ACCESS_KEY_ID "AWS access key id" "$AWS_ACCESS_KEY_ID"
    prompt AWS_SECRET_ACCESS_KEY "AWS secret access key" "$AWS_SECRET_ACCESS_KEY" 1
    prompt AWS_ENDPOINT_URL "Custom S3 endpoint URL (optional)" "$AWS_ENDPOINT_URL"
    choose_one AWS_PATH_STYLE "Use path-style S3 requests" "$AWS_PATH_STYLE" true false
  fi
  confirm_yes_no create_backup_cron "Install nightly DB backup cron?" yes
  confirm_yes_no create_vhost "Create / update web-server vhost?" yes
  [[ "$create_backup_cron" == "1" ]] || SKIP_BACKUP_CRON="1"
  [[ "$create_vhost" == "1" ]] || SKIP_VHOST="1"

  echo
  echo "== Summary =="
  echo "Mode:               $MODE"
  echo "Domain:             $DOMAIN"
  echo "App dir:            $APP_DIR"
  echo "Repo / branch:      $REPO @ $BRANCH"
  echo "DB name / user:     $DB_NAME / $DB_USER"
  echo "Web server:         $WEB_SERVER"
  echo "Backup target:      $BACKUP_TARGET"
  echo "Backup dir:         $BACKUP_DIR"
  echo "Retention days:     $BACKUP_RETENTION_DAYS"
  echo "S3 target:          ${BACKUP_S3_URI:-disabled}"
  echo "Create backup cron: $([[ "$SKIP_BACKUP_CRON" == "1" ]] && echo no || echo yes)"
  echo "Create vhost:       $([[ "$SKIP_VHOST" == "1" ]] && echo no || echo yes)"
  echo
  confirm_yes_no proceed_now "Proceed with installation?" yes
  [[ "$proceed_now" == "1" ]] || { echo "Aborted."; exit 0; }
fi

[[ -n "$MODE" && -n "$DOMAIN" && -n "$APP_DIR" && -n "$DB_NAME" && -n "$DB_USER" && -n "$DB_PASS" ]] || {
  usage
  exit 1
}
[[ "$MODE" == "docker" || "$MODE" == "regular" ]] || { echo "--mode must be docker or regular" >&2; exit 1; }
[[ "$WEB_SERVER" == "auto" || "$WEB_SERVER" == "nginx" || "$WEB_SERVER" == "apache" ]] || { echo "--web-server must be auto|nginx|apache" >&2; exit 1; }
[[ "$BACKUP_TARGET" == "local" || "$BACKUP_TARGET" == "s3" || "$BACKUP_TARGET" == "both" ]] || { echo "--backup-target must be local|s3|both" >&2; exit 1; }

if [[ "$WEB_SERVER" == "auto" ]]; then
  WEB_SERVER="$(detect_web_server)"
fi

if [[ "$BACKUP_TARGET" == "s3" || "$BACKUP_TARGET" == "both" ]]; then
  [[ -n "$BACKUP_S3_URI" && -n "$AWS_REGION" && -n "$AWS_ACCESS_KEY_ID" && -n "$AWS_SECRET_ACCESS_KEY" ]] || {
    echo "S3 backups require bucket URI, region, access key id, and secret access key." >&2
    exit 1
  }
fi

bootstrap_host
need_cmd git
need_cmd openssl
need_cmd sed
need_cmd tee

if [[ "$MODE" == "regular" ]]; then
  PHP_BIN=$(command -v php || echo "$PHP_BIN")
fi

mkdir -p "$APP_DIR"
if [[ ! -d "$APP_DIR/.git" ]]; then
  git clone "$REPO" "$APP_DIR"
fi

cd "$APP_DIR"
git fetch origin "$BRANCH"
git checkout "$BRANCH"
git reset --hard "origin/$BRANCH"
mkdir -p scripts/generated deploy backups docs

APP_KEY_VALUE="base64:$($PHP_BIN -r 'echo base64_encode(random_bytes(32));' 2>/dev/null || true)"
if [[ "$APP_KEY_VALUE" == "base64:" ]]; then
  APP_KEY_VALUE="base64:$(random_b64)"
fi

BACKUP_ENV_FILE="/etc/crm-backup.env"
install_backup_env() {
  if [[ "$BACKUP_TARGET" == "local" ]]; then
    return 0
  fi

  cat > "$BACKUP_ENV_FILE" <<EOF
AWS_ACCESS_KEY_ID=${AWS_ACCESS_KEY_ID}
AWS_SECRET_ACCESS_KEY=${AWS_SECRET_ACCESS_KEY}
AWS_DEFAULT_REGION=${AWS_REGION}
BACKUP_S3_URI=${BACKUP_S3_URI}
AWS_ENDPOINT_URL=${AWS_ENDPOINT_URL}
AWS_USE_PATH_STYLE_ENDPOINT=${AWS_PATH_STYLE}
EOF
  chmod 600 "$BACKUP_ENV_FILE"
}

install_backup_script() {
  local backup_script="$APP_DIR/scripts/backup_db.sh"
  cat > "$backup_script" <<EOF
#!/usr/bin/env bash
set -euo pipefail

MODE="${MODE}"
APP_DIR="${APP_DIR}"
BACKUP_TARGET="${BACKUP_TARGET}"
BACKUP_DIR="${BACKUP_DIR}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS}"
DB_NAME="${DB_NAME}"
DB_USER="${DB_USER}"
DB_PASS='${DB_PASS}'
DB_ROOT_PASS='${DB_ROOT_PASS}'
BACKUP_ENV_FILE="${BACKUP_ENV_FILE}"
STAMP=\$(date +%F-%H%M%S)
HOSTNAME_SHORT=\$(hostname -s)
OUT="\${BACKUP_DIR}/\${DB_NAME}-\${HOSTNAME_SHORT}-\${STAMP}.sql.gz"
mkdir -p "\${BACKUP_DIR}"

if [[ "\${MODE}" == "docker" ]]; then
  cd "\${APP_DIR}"
  docker compose exec -T crm-db sh -lc 'exec mysqldump -uroot -p"'"'"\${DB_ROOT_PASS}"'"'" --single-transaction --quick --routines --triggers "'"'"\${DB_NAME}"'"'"' | gzip -9 > "\${OUT}"
else
  mysqldump -u"\${DB_USER}" -p"\${DB_PASS}" --single-transaction --quick --routines --triggers "\${DB_NAME}" | gzip -9 > "\${OUT}"
fi

find "\${BACKUP_DIR}" -type f -name '*.sql.gz' -mtime +"\${RETENTION_DAYS}" -delete

if [[ "\${BACKUP_TARGET}" == "s3" || "\${BACKUP_TARGET}" == "both" ]]; then
  [[ -f "\${BACKUP_ENV_FILE}" ]] || { echo "Missing backup env file: \${BACKUP_ENV_FILE}" >&2; exit 1; }
  source "\${BACKUP_ENV_FILE}"
  command -v aws >/dev/null 2>&1 || { echo "aws CLI not found" >&2; exit 1; }

  AWS_ARGS=()
  [[ -n "\${AWS_ENDPOINT_URL:-}" ]] && AWS_ARGS+=(--endpoint-url "\${AWS_ENDPOINT_URL}")
  export AWS_ACCESS_KEY_ID="\${AWS_ACCESS_KEY_ID}"
  export AWS_SECRET_ACCESS_KEY="\${AWS_SECRET_ACCESS_KEY}"
  export AWS_DEFAULT_REGION="\${AWS_DEFAULT_REGION}"
  if [[ "\${AWS_USE_PATH_STYLE_ENDPOINT:-false}" == "true" ]]; then
    export AWS_S3_FORCE_PATH_STYLE=true
  fi
  aws s3 cp "\${OUT}" "\${BACKUP_S3_URI}/" "\${AWS_ARGS[@]}"
fi

echo "Backup created: \${OUT}"
EOF
  chmod +x "$backup_script"

  if [[ "$SKIP_BACKUP_CRON" != "1" ]]; then
    cat > /etc/cron.d/crm-db-backup <<EOF
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
15 2 * * * root ${backup_script} >> /var/log/crm-db-backup.log 2>&1
EOF
    chmod 644 /etc/cron.d/crm-db-backup
  fi
}

install_restore_script() {
  local restore_script="$APP_DIR/scripts/restore_db.sh"
  cat > "$restore_script" <<EOF
#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 1 ]]; then
  echo "Usage: $0 /path/to/backup.sql.gz" >&2
  exit 1
fi

BACKUP_FILE="$1"
MODE="${MODE}"
APP_DIR="${APP_DIR}"
DB_NAME="${DB_NAME}"
DB_USER="${DB_USER}"
DB_PASS='${DB_PASS}'
DB_ROOT_PASS='${DB_ROOT_PASS}'

[[ -f "\${BACKUP_FILE}" ]] || { echo "Backup file not found: \${BACKUP_FILE}" >&2; exit 1; }

echo "WARNING: this will overwrite data in database '\${DB_NAME}'."
read -r -p "Type RESTORE to continue: " CONFIRM
[[ "\${CONFIRM}" == "RESTORE" ]] || { echo "Aborted."; exit 1; }

if [[ "\${MODE}" == "docker" ]]; then
  cd "\${APP_DIR}"
  gunzip -c "\${BACKUP_FILE}" | docker compose exec -T crm-db sh -lc 'exec mysql -uroot -p"'"'"\${DB_ROOT_PASS}"'"'" "'"'"\${DB_NAME}"'"'"'
else
  gunzip -c "\${BACKUP_FILE}" | mysql -u"\${DB_USER}" -p"\${DB_PASS}" "\${DB_NAME}"
fi

echo "Restore complete from \${BACKUP_FILE}"
EOF
  chmod +x "$restore_script"
}

install_redeploy_script() {
  local redeploy_script="$APP_DIR/scripts/redeploy_crm.sh"
  cat > "$redeploy_script" <<EOF
#!/usr/bin/env bash
set -euo pipefail

BRANCH="\${1:-${BRANCH}}"
APP_DIR="${APP_DIR}"
MODE="${MODE}"
PHP_BIN="${PHP_BIN}"

cd "\${APP_DIR}"
git fetch origin "\${BRANCH}"
git checkout "\${BRANCH}"
git reset --hard "origin/\${BRANCH}"

if [[ "\${MODE}" == "docker" ]]; then
  docker compose build crm-app
  docker compose stop crm-app || true
  docker compose rm -sf crm-app || true
  docker compose up -d --no-deps crm-app
else
  composer install --no-interaction --prefer-dist --optimize-autoloader
  npm ci
  npm run build
  "\${PHP_BIN}" artisan migrate --force
  "\${PHP_BIN}" artisan db:seed --class=Database\\Seeders\\DatabaseSeeder --force
  "\${PHP_BIN}" artisan db:seed --class=Database\\Seeders\\InitialSetupSeeder --force
  "\${PHP_BIN}" artisan db:seed --class=Database\\Seeders\\PaymentMethodSeeder --force
  "\${PHP_BIN}" artisan db:seed --class=Database\\Seeders\\SmsProviderSeeder --force
fi

echo "Redeploy finished for branch: \${BRANCH}"
EOF
  chmod +x "$redeploy_script"
}

write_nginx_vhost() {
  local upstream_port="$1"
  mkdir -p /etc/nginx/sites-available /etc/nginx/sites-enabled
  cat > "/etc/nginx/sites-available/${DOMAIN}.conf" <<EOF
server {
    listen 80;
    server_name ${DOMAIN};

    client_max_body_size 32m;

    location / {
        proxy_pass http://127.0.0.1:${upstream_port};
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection upgrade;
    }
}
EOF
  ln -sfn "/etc/nginx/sites-available/${DOMAIN}.conf" "/etc/nginx/sites-enabled/${DOMAIN}.conf"
  nginx -t
  systemctl reload nginx
}

write_apache_vhost_proxy() {
  local upstream_port="$1"
  local apache_dir="/etc/apache2/sites-available"
  [[ -d "$apache_dir" ]] || apache_dir="/etc/httpd/conf.d"
  cat > "${apache_dir}/${DOMAIN}.conf" <<EOF
<VirtualHost *:80>
    ServerName ${DOMAIN}
    ProxyPreserveHost On
    ProxyPass / http://127.0.0.1:${upstream_port}/
    ProxyPassReverse / http://127.0.0.1:${upstream_port}/
    RequestHeader set X-Forwarded-Proto "http"
</VirtualHost>
EOF
  if command -v a2enmod >/dev/null 2>&1; then
    a2enmod proxy proxy_http headers >/dev/null || true
    a2ensite "${DOMAIN}.conf" >/dev/null || true
    apache2ctl configtest
    systemctl reload apache2
  else
    httpd -t
    systemctl reload httpd
  fi
}

install_docker() {
  need_cmd docker
  docker compose version >/dev/null 2>&1 || { echo "docker compose plugin is required" >&2; exit 1; }
  [[ -n "$DB_ROOT_PASS" ]] || { echo "Docker mode requires a MySQL root password" >&2; exit 1; }

  cat > .env.docker <<EOF
APP_NAME="${APP_NAME}"
APP_ENV=production
APP_KEY=${APP_KEY_VALUE}
APP_DEBUG=false
APP_URL=http://${DOMAIN}
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US
LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=info
DB_CONNECTION=mysql
DB_HOST=crm-db
DB_PORT=3306
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASS}
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=${DOMAIN}
BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database
MAIL_MAILER=log
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=${AWS_REGION:-us-east-1}
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false
VITE_APP_NAME="${APP_NAME}"
EOF

  cat > docker-compose.override.yml <<EOF
services:
  crm-db:
    container_name: crm-db
    environment:
      MYSQL_DATABASE: ${DB_NAME}
      MYSQL_USER: ${DB_USER}
      MYSQL_PASSWORD: ${DB_PASS}
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASS}
    volumes:
      - crm_db_data:/var/lib/mysql
  crm-app:
    container_name: crm-app
    environment:
      APP_NAME: "${APP_NAME}"
      APP_ENV: production
      APP_DEBUG: "false"
      APP_URL: http://${DOMAIN}
      DB_CONNECTION: mysql
      DB_HOST: crm-db
      DB_PORT: 3306
      DB_DATABASE: ${DB_NAME}
      DB_USERNAME: ${DB_USER}
      DB_PASSWORD: ${DB_PASS}
      SESSION_DRIVER: database
      CACHE_STORE: database
      QUEUE_CONNECTION: database
      MAIL_MAILER: log
    ports:
      - "127.0.0.1:${APP_PORT}:8000"
    volumes:
      - ./.env.docker:/var/www/html/.env
      - crm_storage:/var/www/html/storage
volumes:
  crm_db_data:
  crm_storage:
EOF

  docker compose up -d --build

  if [[ "$SKIP_VHOST" != "1" ]]; then
    if [[ "$WEB_SERVER" == "nginx" ]]; then
      write_nginx_vhost "$APP_PORT"
    else
      write_apache_vhost_proxy "$APP_PORT"
    fi
  fi
}

install_regular() {
  need_cmd "$PHP_BIN"
  need_cmd composer
  need_cmd npm
  need_cmd mysql

  local www_user="www-data"
  id "$www_user" >/dev/null 2>&1 || www_user="apache"

  cp -f .env.example .env
  perl -0pi -e 's/^APP_NAME=.*$/APP_NAME="'"${APP_NAME//\//\/}"'"/m' .env
  perl -0pi -e 's/^APP_ENV=.*$/APP_ENV=production/m' .env
  perl -0pi -e 's/^APP_DEBUG=.*$/APP_DEBUG=false/m' .env
  perl -0pi -e 's/^APP_URL=.*$/APP_URL=http:\/\/'"${DOMAIN//\//\/}"'/m' .env
  perl -0pi -e 's/^APP_KEY=.*$/APP_KEY='"${APP_KEY_VALUE//\//\/}"'/m' .env
  perl -0pi -e 's/^SESSION_DOMAIN=.*$/SESSION_DOMAIN='"${DOMAIN//\//\/}"'/m' .env
  perl -0pi -e 's/^DB_CONNECTION=.*?\n(?:# DB_HOST=.*?\n)?(?:# DB_PORT=.*?\n)?(?:# DB_DATABASE=.*?\n)?(?:# DB_USERNAME=.*?\n)?(?:# DB_PASSWORD=.*?\n)?/DB_CONNECTION=mysql\nDB_HOST=127.0.0.1\nDB_PORT=3306\nDB_DATABASE='"${DB_NAME}"'\nDB_USERNAME='"${DB_USER}"'\nDB_PASSWORD='"${DB_PASS}"'\n/sm' .env

  composer install --no-interaction --prefer-dist --optimize-autoloader
  npm ci
  npm run build
  "$PHP_BIN" artisan migrate --force
  "$PHP_BIN" artisan db:seed --class=Database\\Seeders\\DatabaseSeeder --force
  "$PHP_BIN" artisan db:seed --class=Database\\Seeders\\InitialSetupSeeder --force
  "$PHP_BIN" artisan db:seed --class=Database\\Seeders\\PaymentMethodSeeder --force
  "$PHP_BIN" artisan db:seed --class=Database\\Seeders\\SmsProviderSeeder --force
  chown -R "$www_user":"$www_user" storage bootstrap/cache

  if [[ "$SKIP_VHOST" != "1" ]]; then
    if [[ "$WEB_SERVER" == "nginx" ]]; then
      [[ -n "$PHP_FPM_SOCK" ]] || { echo "Regular nginx mode requires --php-fpm-sock" >&2; exit 1; }
      mkdir -p /etc/nginx/sites-available /etc/nginx/sites-enabled
      cat > "/etc/nginx/sites-available/${DOMAIN}.conf" <<EOF
server {
    listen 80;
    server_name ${DOMAIN};
    root ${APP_DIR}/public;
    index index.php index.html;
    client_max_body_size 32m;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_pass unix:${PHP_FPM_SOCK};
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF
      ln -sfn "/etc/nginx/sites-available/${DOMAIN}.conf" "/etc/nginx/sites-enabled/${DOMAIN}.conf"
      nginx -t
      systemctl reload nginx
    else
      local apache_dir="/etc/apache2/sites-available"
      [[ -d "$apache_dir" ]] || apache_dir="/etc/httpd/conf.d"
      cat > "${apache_dir}/${DOMAIN}.conf" <<EOF
<VirtualHost *:80>
    ServerName ${DOMAIN}
    DocumentRoot ${APP_DIR}/public

    <Directory ${APP_DIR}/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
EOF
      if command -v a2ensite >/dev/null 2>&1; then
        a2enmod rewrite headers >/dev/null || true
        a2ensite "${DOMAIN}.conf" >/dev/null || true
        apache2ctl configtest
        systemctl reload apache2
      else
        httpd -t
        systemctl reload httpd
      fi
    fi
  fi
}

install_backup_env
install_backup_script
install_restore_script
install_redeploy_script

if [[ "$MODE" == "docker" ]]; then
  install_docker
else
  install_regular
fi

cat <<EOF

Installation complete.
Mode: ${MODE}
Domain: ${DOMAIN}
App dir: ${APP_DIR}
Backup target: ${BACKUP_TARGET}
Backup dir: ${BACKUP_DIR}
Backup cron: $( [[ "$SKIP_BACKUP_CRON" == "1" ]] && echo disabled || echo /etc/cron.d/crm-db-backup )
Backup env file: $( [[ "$BACKUP_TARGET" == "local" ]] && echo not-needed || echo ${BACKUP_ENV_FILE} )

Created helper scripts:
- ${APP_DIR}/scripts/backup_db.sh
- ${APP_DIR}/scripts/restore_db.sh
- ${APP_DIR}/scripts/redeploy_crm.sh

Important:
- Docker mode preserves DB/storage on normal redeploys because it uses named volumes.
- Avoid 'docker compose down -v' unless you explicitly want to destroy DB/storage volumes.
- Private S3 backups use ${BACKUP_ENV_FILE} (root-only).
EOF
