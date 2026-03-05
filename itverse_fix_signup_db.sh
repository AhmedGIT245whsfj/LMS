#!/usr/bin/env bash
set -euo pipefail

NS="itverse"
CM="itverse-config"
SEC="itverse-secrets"
LOG="/tmp/itverse_fix_signup_db.log"

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
  echo "ERROR: Missing DB vars"
  echo "DB_HOST='${DB_HOST:-}' DB_USER='${DB_USER:-}' DB_NAME='${DB_NAME:-}' PASS_PRESENT=$([[ -n "${DB_PASSWORD_B64:-}" ]] && echo yes || echo no)"
  exit 1
fi

DB_PASSWORD="$(echo "$DB_PASSWORD_B64" | base64 -d | tr -d '\r')"

echo "Using:"
echo "DB_HOST=$DB_HOST"
echo "DB_USER=$DB_USER"
echo "DB_NAME=$DB_NAME"

echo "== Ensure columns exist in student table =="
kubectl -n "$NS" run mysql-migrate-student --rm -i --restart=Never \
  --image=mariadb:10.11 \
  --env="MYSQL_PWD=$DB_PASSWORD" \
  --command -- sh -lc "
set -e

# helper: add column only if missing
add_col() {
  TBL=\"\$1\"
  COL=\"\$2\"
  DEF=\"\$3\"

  HAS=\$(mysql -h '$DB_HOST' -u '$DB_USER' '$DB_NAME' -Nse \"
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE()
      AND TABLE_NAME='\$TBL'
      AND COLUMN_NAME='\$COL';
  \" | tr -d ' \r\n')

  echo \"check \$TBL.\$COL => \$HAS\"

  if [ \"\$HAS\" = \"0\" ]; then
    echo \"Adding \$TBL.\$COL ...\"
    mysql -h '$DB_HOST' -u '$DB_USER' '$DB_NAME' -e \"ALTER TABLE \\\`\$TBL\\\` ADD COLUMN \\\`\$COL\\\` \$DEF;\"
  else
    echo \"Already exists: \$TBL.\$COL\"
  fi
}

# The code currently fails on preferred_track.
# Add both fields for your registration feature (track + experience).
add_col student preferred_track 'VARCHAR(100) NULL'
add_col student experience_level 'VARCHAR(50) NULL'

echo '== Show the columns (sanity) =='
mysql -h '$DB_HOST' -u '$DB_USER' '$DB_NAME' -e \"SHOW COLUMNS FROM student LIKE 'preferred_%'; SHOW COLUMNS FROM student LIKE 'experience_%';\"
"

echo "== Restart web =="
kubectl -n "$NS" rollout restart deploy/itverse-web
kubectl -n "$NS" rollout status deploy/itverse-web --timeout=240s

echo "== Smoke test signup endpoint (should not fatal) =="
LB=$(kubectl -n "$NS" get svc itverse-web-lb -o jsonpath='{.status.loadBalancer.ingress[0].hostname}')
echo "LB=http://$LB/"

curl -s -X POST "http://$LB/Student/addstudent.php" \
  -d 'stusignup=1' \
  -d 'stuname=Smoke Test' \
  -d "stuemail=smoke_$(date +%s)@example.com" \
  -d 'stupass=Test@12345' \
  | head -n 60

echo "=== DONE $(date) ==="
