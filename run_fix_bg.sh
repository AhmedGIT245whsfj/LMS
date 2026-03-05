#!/usr/bin/env bash
set -euo pipefail
NS=itverse
S=itverse-secrets

echo "=== $(date) START ==="

echo "== Secret keys (go-template) =="
kubectl -n "$NS" get secret "$S" -o go-template='{{range $k,$v := .data}}{{printf "%s\n" $k}}{{end}}' | sed '/^$/d'

KEY="$(kubectl -n "$NS" get secret "$S" -o go-template='{{range $k,$v := .data}}{{printf "%s\n" $k}}{{end}}' | sed '/^$/d' | head -n 1)"
if [[ -z "${KEY:-}" ]]; then
  echo "ERROR: Could not detect secret data key"
  exit 1
fi
echo "Using key: $KEY"

kubectl -n "$NS" get secret "$S" -o "go-template={{index .data \"$KEY\"}}" | base64 -d > /tmp/itverse.env

DB_HOST=$(grep -E '^DB_HOST=' /tmp/itverse.env | tail -n 1 | cut -d= -f2- | tr -d '\r"')
DB_USER=$(grep -E '^DB_USER=' /tmp/itverse.env | tail -n 1 | cut -d= -f2- | tr -d '\r"')
DB_PASSWORD=$(grep -E '^DB_PASSWORD=' /tmp/itverse.env | tail -n 1 | cut -d= -f2- | tr -d '\r"')
DB_NAME=$(grep -E '^DB_NAME=' /tmp/itverse.env | tail -n 1 | cut -d= -f2- | tr -d '\r"')

echo "DB_HOST=$DB_HOST"
echo "DB_USER=$DB_USER"
echo "DB_NAME=$DB_NAME"

if [[ -z "$DB_HOST" || -z "$DB_USER" || -z "$DB_PASSWORD" || -z "$DB_NAME" ]]; then
  echo "ERROR: DB vars missing. Showing DB_ lines:"
  grep -nE 'DB_' /tmp/itverse.env || true
  exit 1
fi

echo "== SQL fix: add course_original_price =="
kubectl -n "$NS" run mysql-fix --rm -i --restart=Never \
  --image=mariadb:10.11 \
  --env="MYSQL_PWD=$DB_PASSWORD" \
  --command -- sh -lc "
set -e
mysql -h '$DB_HOST' -u '$DB_USER' '$DB_NAME' -e \"
ALTER TABLE course
  ADD COLUMN IF NOT EXISTS course_original_price DECIMAL(10,2) NULL AFTER course_price;

UPDATE course
SET course_original_price = course_price
WHERE course_original_price IS NULL;

SELECT COUNT(*) AS has_col
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=DATABASE()
  AND TABLE_NAME='course'
  AND COLUMN_NAME='course_original_price';
\"
"

echo "== Restart web =="
kubectl -n "$NS" rollout restart deploy/itverse-web
kubectl -n "$NS" rollout status deploy/itverse-web --timeout=240s

LB=$(kubectl -n "$NS" get svc itverse-web-lb -o jsonpath='{.status.loadBalancer.ingress[0].hostname}')
echo "LB=http://$LB/"
echo "== Homepage check =="
curl -s "http://$LB/" | grep -nEi "fatal error|Unknown column|warning:|notice:" | head -n 120 || echo "✅ Homepage clean"

echo "=== $(date) DONE ==="
