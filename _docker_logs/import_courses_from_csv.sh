#!/usr/bin/env bash
cd "$(dirname "$0")/.." 2>/dev/null || cd .. || true

DC="docker compose"
command -v docker-compose >/dev/null 2>&1 && DC="docker-compose"

CSV="courses.csv"
if [ ! -f "$CSV" ]; then
  echo "ERROR: $CSV not found in repo root"
  exit 0
fi

echo "== Compose status =="
$DC ps || true

echo
echo "== Convert CSV -> /tmp/courses_seed.sql =="
python3 - <<'PY'
import csv
from pathlib import Path

csv_path = Path("courses.csv")
sql_path = Path("_docker_logs/courses_seed.sql")
rows = []
with csv_path.open("r", encoding="utf-8") as f:
    r = csv.DictReader(f)
    for row in r:
        rows.append(row)

def esc(s: str) -> str:
    if s is None: return ""
    return str(s).replace("\\", "\\\\").replace("'", "''")

# We will map track_name -> track_id from DB at import-time using a SELECT subquery.
# Also we store course_img as the filename only. Pages usually prefix with image/courseimg/.
# If your pages expect full path, we can switch it later.

lines = []
lines.append("START TRANSACTION;")
lines.append("USE lms_db;")
lines.append("SET SQL_SAFE_UPDATES=0;")
lines.append("DELETE FROM lesson;")
lines.append("DELETE FROM course;")
lines.append("ALTER TABLE course AUTO_INCREMENT=1;")
lines.append("ALTER TABLE lesson AUTO_INCREMENT=1;")

for i, row in enumerate(rows, start=1):
    track_name = esc(row.get("track_name","").strip())
    course_name = esc(row.get("course_name","").strip())
    course_desc = esc(row.get("course_desc","").strip())
    course_author = esc(row.get("course_author","").strip())
    course_duration = esc(row.get("course_duration","2h").strip())
    course_price = row.get("course_price","0").strip() or "0"
    course_original_price = row.get("course_original_price","0").strip() or "0"
    course_img = esc(row.get("course_img","").strip())

    # track_id comes from track table (your DB uses track)
    # If track name doesn't exist, it will insert NULL -> you will see it in sanity check.
    lines.append(
        "INSERT INTO course (track_id, course_name, course_desc, course_author, course_img, course_duration, course_price, course_original_price) "
        f"VALUES ((SELECT track_id FROM track WHERE track_name='{track_name}' LIMIT 1), "
        f"'{course_name}','{course_desc}','{course_author}','{course_img}','{course_duration}',{course_price},{course_original_price});"
    )

lines.append("COMMIT;")
sql_path.write_text("\n".join(lines) + "\n", encoding="utf-8")
print(f"OK: wrote {sql_path} with {len(rows)} INSERTs")
PY

echo
echo "== Copy seed SQL into db container and import =="
$DC cp _docker_logs/courses_seed.sql db:/tmp/courses_seed.sql 2>/dev/null || $DC cp _docker_logs/courses_seed.sql itverse-db:/tmp/courses_seed.sql || true
$DC exec -T db mariadb -uroot -proot -e "SOURCE /tmp/courses_seed.sql;" || true

echo
echo "== Sanity checks =="
$DC exec -T db mariadb -uroot -proot -e "
USE lms_db;
SELECT COUNT(*) AS track FROM track;
SELECT COUNT(*) AS courses FROM course;
SELECT track_id, track_name FROM track ORDER BY track_id;
SELECT course_id, track_id, course_name, course_img FROM course ORDER BY course_id LIMIT 20;
" || true

echo
echo "DONE"
