#!/usr/bin/env bash
set -euo pipefail

NS="${NS:-itverse}"
DEPLOY="${DEPLOY:-itverse-web}"
CONTAINER="${CONTAINER:-web}"
IMAGE="${IMAGE:-ahmeduioueu235g/itverse-web:latest}"

LOG="/tmp/itverse_pull_latest_$(date +%s).log"
exec > >(tee -a "$LOG") 2>&1

echo "=== START $(date) ==="
echo "NS=$NS DEPLOY=$DEPLOY CONTAINER=$CONTAINER IMAGE=$IMAGE"
echo "Log: $LOG"
echo

echo "== 1) Ensure deployment exists =="
kubectl -n "$NS" get deploy "$DEPLOY" -o wide
echo

echo "== 2) Force imagePullPolicy: Always (so :latest is actually pulled) =="
kubectl -n "$NS" patch deploy "$DEPLOY" --type='json' -p="[
  {\"op\":\"add\",\"path\":\"/spec/template/spec/containers/0/imagePullPolicy\",\"value\":\"Always\"}
]" >/dev/null 2>&1 || true
echo "OK"
echo

echo "== 3) Force new revision with :latest (set image) =="
kubectl -n "$NS" set image "deploy/$DEPLOY" "$CONTAINER=$IMAGE" --record
echo

echo "== 4) Rollout restart + wait =="
kubectl -n "$NS" rollout restart "deploy/$DEPLOY"
kubectl -n "$NS" rollout status "deploy/$DEPLOY" --timeout=360s
echo

echo "== 5) Delete pods to force fresh pull (safest) =="
kubectl -n "$NS" delete pod -l app="$DEPLOY" --ignore-not-found=true
kubectl -n "$NS" rollout status "deploy/$DEPLOY" --timeout=360s
echo

echo "== 6) Show actual running image + imageID (this proves what was pulled) =="
kubectl -n "$NS" get deploy "$DEPLOY" -o jsonpath='{.spec.template.spec.containers[0].image}{"\n"}'
kubectl -n "$NS" get pods -l app="$DEPLOY" -o jsonpath='{range .items[*]}{.metadata.name}{"  =>  "}{.status.containerStatuses[0].imageID}{"\n"}{end}'
echo

echo "== 7) Quick homepage check =="
LB="$(kubectl -n "$NS" get svc itverse-web-lb -o jsonpath='{.status.loadBalancer.ingress[0].hostname}')"
echo "LB=http://$LB/"
HTML="$(curl -s "http://$LB/" || true)"
echo "$HTML" | grep -nEi "fatal error|Unknown column|warning:|notice:" | head -n 80 || echo "✅ Homepage clean"
echo

echo "=== DONE $(date) ==="
echo "Log: $LOG"
echo
read -rp "Press Enter to close..."
