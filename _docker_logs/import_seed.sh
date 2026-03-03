#!/usr/bin/env bash
set -euo pipefail
cd ~/Downloads/ITVERSE

echo "[seed] start"
docker compose exec -T db sh -lc 'mariadb -uroot -proot lms_db < /tmp/seed.sql'
echo "[seed] done"
