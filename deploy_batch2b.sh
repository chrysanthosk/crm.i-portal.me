#!/usr/bin/env bash
set -euo pipefail

APP_DIR=/opt/crm-dev.i-portal.me-docker
cd $APP_DIR

echo "== Fetching branch batch-2b-appointment-workflow =="
GIT_SSH_COMMAND='ssh -i /home/atlas/.ssh/github_atlas_ed25519 -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new' git fetch origin batch-2b-appointment-workflow
git checkout batch-2b-appointment-workflow
git reset --hard origin/batch-2b-appointment-workflow

echo "== Restarting Docker =="
docker-compose down -v || true
docker-compose up -d --build

sleep 10
echo "== ps =="
docker-compose ps
echo "== logs =="
docker-compose logs --tail=40 crm-app
echo "== test local 8088 =="
curl -s -I http://127.0.0.1:8088 | head -n 15
