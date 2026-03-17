#!/usr/bin/env bash
set -euo pipefail

APP_DIR=${APP_DIR:-/opt/crm-dev.i-portal.me-docker}
BRANCH=${1:-${BRANCH:-master}}
MODE=${MODE:-app-only}

cd "$APP_DIR"

echo "== Fetching branch ${BRANCH} =="
GIT_SSH_COMMAND='ssh -i /home/atlas/.ssh/github_atlas_ed25519 -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new' git fetch origin "$BRANCH"
git checkout "$BRANCH"
git reset --hard "origin/$BRANCH"

if [[ "$MODE" == "full-reset" ]]; then
  echo "== Full reset deploy (will remove DB/storage volumes) =="
  docker-compose down -v || true
  docker-compose up -d --build
else
  echo "== App-only deploy (DB/storage preserved) =="
  docker-compose build crm-app
  docker-compose up -d --no-deps crm-app
fi

sleep 10
echo "== ps =="
docker-compose ps
echo "== logs =="
docker-compose logs --tail=40 crm-app
echo "== test local 8088 =="
curl -s -I http://127.0.0.1:8088 | head -n 15
