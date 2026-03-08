#!/usr/bin/env bash
set -Eeuo pipefail

NS="${NS:-itverse}"
DEPLOY="${DEPLOY:-itverse-web}"
CONTAINER="${CONTAINER:-web}"

LOG="/tmp/itverse_fix_probes_$(date +%s).log"
exec > >(tee -a "$LOG") 2>&1

echo "=== FIX PROBES (JSON PATCH SAFE) ==="
echo "Time: $(date)"
echo "NS=$NS DEPLOY=$DEPLOY CONTAINER=$CONTAINER"
echo "Log: $LOG"
echo

echo "== 1) Find container index by name =="
IDX="$(kubectl -n "$NS" get deploy "$DEPLOY" -o json | python3 - <<'PY'
import json,sys
d=json.load(sys.stdin)
name=sys.argv[1] if len(sys.argv)>1 else "web"
cs=d["spec"]["template"]["spec"]["containers"]
for i,c in enumerate(cs):
    if c.get("name")==name:
        print(i)
        raise SystemExit(0)
print(-1)
PY
"$CONTAINER")"

if [[ "$IDX" == "-1" ]]; then
  echo "ERROR: container name not found: $CONTAINER"
  echo "Containers are:"
  kubectl -n "$NS" get deploy "$DEPLOY" -o jsonpath='{range .spec.template.spec.containers[*]}{.name}{"\n"}{end}'
  exit 1
fi

echo "[OK] container index = $IDX"
echo

echo "== 2) Apply JSON patches (readiness + liveness) without touching image =="
PATCH="$(cat <<JSON
[
  {
    "op":"add",
    "path":"/spec/template/spec/containers/$IDX/readinessProbe",
    "value":{
      "httpGet":{"path":"/","port":80,"scheme":"HTTP"},
      "initialDelaySeconds":10,
      "periodSeconds":10,
      "timeoutSeconds":2,
      "failureThreshold":3,
      "successThreshold":1
    }
  },
  {
    "op":"add",
    "path":"/spec/template/spec/containers/$IDX/livenessProbe",
    "value":{
      "httpGet":{"path":"/","port":80,"scheme":"HTTP"},
      "initialDelaySeconds":30,
      "periodSeconds":20,
      "timeoutSeconds":2,
      "failureThreshold":3,
      "successThreshold":1
    }
  }
]
JSON
)"

kubectl -n "$NS" patch deploy "$DEPLOY" --type='json' -p "$PATCH"
echo "[OK] probes patched"
echo

echo "== 3) Rollout status =="
kubectl -n "$NS" rollout status deploy/"$DEPLOY" --timeout=240s
echo

echo "== 4) Show probes + image (prove not broken) =="
echo "-- image --"
kubectl -n "$NS" get deploy "$DEPLOY" -o jsonpath='{.spec.template.spec.containers['"$IDX"'].image}{"\n"}'
echo "-- readiness --"
kubectl -n "$NS" get deploy "$DEPLOY" -o jsonpath='{.spec.template.spec.containers['"$IDX"'].readinessProbe.httpGet.path}{" "}{.spec.template.spec.containers['"$IDX"'].readinessProbe.httpGet.port}{"\n"}'
echo "-- liveness --"
kubectl -n "$NS" get deploy "$DEPLOY" -o jsonpath='{.spec.template.spec.containers['"$IDX"'].livenessProbe.httpGet.path}{" "}{.spec.template.spec.containers['"$IDX"'].livenessProbe.httpGet.port}{"\n"}'
echo

echo "=== DONE ==="
echo "Log: $LOG"
echo
read -r -p "Press Enter to close... " _
