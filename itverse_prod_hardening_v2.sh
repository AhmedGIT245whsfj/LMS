#!/usr/bin/env bash
set -Eeuo pipefail

NS="${NS:-itverse}"
DEPLOY="${DEPLOY:-itverse-web}"
CONTAINER="${CONTAINER:-web}"

LOG="/tmp/itverse_prod_hardening_v2_$(date +%s).log"
exec > >(tee -a "$LOG") 2>&1

echo "=== ITVERSE PROD HARDENING v2 ==="
echo "Time: $(date)"
echo "NS=$NS DEPLOY=$DEPLOY CONTAINER=$CONTAINER"
echo "Log: $LOG"
echo

echo "== 0) Sanity =="
kubectl -n "$NS" get deploy "$DEPLOY" >/dev/null
echo "[OK] deploy exists"
echo

echo "== 1) Backup deploy yaml =="
kubectl -n "$NS" get deploy "$DEPLOY" -o yaml > "/tmp/${DEPLOY}_before_hardening.yaml"
echo "[OK] /tmp/${DEPLOY}_before_hardening.yaml"
echo

echo "== 2) Get container index safely (no heredoc args bug) =="
JSON="$(kubectl -n "$NS" get deploy "$DEPLOY" -o json)"
IDX="$(python3 - "$CONTAINER" <<'PY'
import json,sys
name=sys.argv[1]
d=json.load(sys.stdin)
cs=d["spec"]["template"]["spec"]["containers"]
for i,c in enumerate(cs):
    if c.get("name")==name:
        print(i)
        sys.exit(0)
print(-1)
PY
<<<"$JSON")"

if [[ "$IDX" == "-1" ]]; then
  echo "ERROR: container not found: $CONTAINER"
  echo "Containers:"
  echo "$JSON" | python3 - <<'PY'
import json,sys
d=json.load(sys.stdin)
print("\n".join([c.get("name","?") for c in d["spec"]["template"]["spec"]["containers"]]))
PY
  exit 1
fi
echo "[OK] container index=$IDX"
echo

echo "== 3) Tune RollingUpdate + progressDeadline (safe merge) =="
kubectl -n "$NS" patch deploy "$DEPLOY" --type='merge' -p '{
  "spec":{
    "progressDeadlineSeconds": 300,
    "strategy":{
      "type":"RollingUpdate",
      "rollingUpdate":{"maxSurge":1,"maxUnavailable":0}
    }
  }
}'
echo "[OK] rolling update tuned"
echo

echo "== 4) Set/Replace Readiness+Liveness using JSON patch (never touches image) =="
# detect if probes already exist
HAS_READINESS="$(echo "$JSON" | python3 - <<PY
import json,sys
d=json.load(sys.stdin)
c=d["spec"]["template"]["spec"]["containers"][$IDX]
print(1 if "readinessProbe" in c else 0)
PY
)"
HAS_LIVENESS="$(echo "$JSON" | python3 - <<PY
import json,sys
d=json.load(sys.stdin)
c=d["spec"]["template"]["spec"]["containers"][$IDX]
print(1 if "livenessProbe" in c else 0)
PY
)"

OP_R="replace" ; [[ "$HAS_READINESS" == "0" ]] && OP_R="add"
OP_L="replace" ; [[ "$HAS_LIVENESS" == "0" ]] && OP_L="add"

PATCH="$(cat <<JSON
[
  {
    "op":"$OP_R",
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
    "op":"$OP_L",
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
echo "[OK] probes ensured (readiness=$OP_R, liveness=$OP_L)"
echo

echo "== 5) Rollout status =="
kubectl -n "$NS" rollout status deploy/"$DEPLOY" --timeout=240s
echo

echo "== 6) Summary (image + probes) =="
echo "-- image --"
kubectl -n "$NS" get deploy "$DEPLOY" -o jsonpath='{.spec.template.spec.containers['"$IDX"'].image}{"\n"}'
echo "-- readiness --"
kubectl -n "$NS" get deploy "$DEPLOY" -o jsonpath='{.spec.template.spec.containers['"$IDX"'].readinessProbe.httpGet.path}{" "}{.spec.template.spec.containers['"$IDX"'].readinessProbe.httpGet.port}{"\n"}'
echo "-- liveness --"
kubectl -n "$NS" get deploy "$DEPLOY" -o jsonpath='{.spec.template.spec.containers['"$IDX"'].livenessProbe.httpGet.path}{" "}{.spec.template.spec.containers['"$IDX"'].livenessProbe.httpGet.port}{"\n"}'
echo

echo "== 7) HPA status =="
kubectl -n "$NS" get hpa -o wide || true
echo

echo "=== DONE ==="
echo "Log: $LOG"
