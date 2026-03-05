#!/usr/bin/env bash
set -euo pipefail

NS="itverse"
CM="itverse-config"
SEC="itverse-secrets"
LOG="/tmp/itverse_autofix.log"

exec > >(tee -a "$LOG") 2>&1

echo "=== START $(date) ==="

get_cm() { kubectl -n "$NS" get configmap "$CM" -o "go-template={{index .data \"$1\"}}" 2>/dev/null || true; }
get_sec(){ kubectl -n "$NS" get secret "$SEC" -o "go-template={{index .data \"$1\"}}" 2>/dev/null || true; }

DB_HOST="$(get_cm DB_HOST)"
DB_USER="$(get_cm DB_USER)"
DB_NAME="$(get_cm DB_NAME)"

DB_PASSWORD_B64="$(get_sec DB_PASS)"
if [[ -z "${DB_PASSWORD_B64:-}" ]]; then
  DB_PASSWORD_B64="$(get_sec DB_PASSWORD)"
fi

if [[ -z "${DB_HOST:-}" || -z "${DB_USER:-}" || -z "${DB_NAME:-}" || -z "${DB_PASSWORD_B64:-}" ]]; then
  echo "ERROR: Missing DB vars."
  exit 1
fi

DB_PASSWORD="$(echo "$DB_PASSWORD_B64" | base64 -d | tr -d '\r')"

echo "Using:"
echo "DB_HOST=$DB_HOST"
echo "DB_USER=$DB_USER"
echo "DB_NAME=$DB_NAME"

echo "== Fix DB schema (compatible): add column if missing =="
kubectl -n "$NS" run mysql-fix --rm -i --restart=Never \
  --image=mariadb:10.11 \
  --env="MYSQL_PWD=$DB_PASSWORD" \
  --command -- sh -lc "
set -e
mysql -h '$DB_HOST' -u '$DB_USER' '$DB_NAME' -Nse \"
SELECT COUNT(*)
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=DATABASE()
  AND TABLE_NAME='course'
  AND COLUMN_NAME='course_original_price';
\" > /tmp/has_col.txt

HAS_COL=\$(cat /tmp/has_col.txt | tr -d ' \r\n')
echo \"has_col=\$HAS_COL\"

if [ \"\$HAS_COL\" = \"0\" ]; then
  echo \"Adding column course_original_price...\"
  mysql -h '$DB_HOST' -u '$DB_USER' '$DB_NAME' -e \"
    ALTER TABLE course
      ADD COLUMN course_original_price DECIMAL(10,2) NULL AFTER course_price;
  \"
else
  echo \"Column already exists.\"
fi

echo \"Populating NULLs...\"
mysql -h '$DB_HOST' -u '$DB_USER' '$DB_NAME' -e \"
  UPDATE course
  SET course_original_price = course_price
  WHERE course_original_price IS NULL;
\"

mysql -h '$DB_HOST' -u '$DB_USER' '$DB_NAME' -e \"
  SELECT course_id, course_price, course_original_price
  FROM course
  ORDER BY course_id
  LIMIT 5;
\"
"

echo "== Restart web =="
kubectl -n "$NS" rollout restart deploy/itverse-web
kubectl -n "$NS" rollout status deploy/itverse-web --timeout=240s

echo "== Verify homepage =="
LB=$(kubectl -n "$NS" get svc itverse-web-lb -o jsonpath='{.status.loadBalancer.ingress[0].hostname}')
echo "LB=http://$LB/"
curl -s "http://$LB/" | grep -nEi "fatal error|Unknown column|warning:|notice:" | head -n 120 || echo "✅ Homepage clean"

echo "=== DONE $(date) ==="
