#!/usr/bin/env bash
set -euo pipefail
cd ~/Downloads/ITVERSE

echo "[import] start"
docker compose exec -T db sh -lc 'mariadb -uroot -proot lms_db < /tmp/db_dump.sql'
echo "[import] done"
