#!/usr/bin/env bash
set -Eeuo pipefail

NS="${NS:-itverse}"
DEPLOY="${DEPLOY:-itverse-web}"
SVC="${SVC:-itverse-web-lb}"
CONTAINER="${CONTAINER:-web}"

LOG="/tmp/itverse_prod_hardening_$(date +%s).log"
exec > >(tee -a "$LOG") 2>&1

echo "=== ITVERSE PROD HARDENING ==="
echo "Time: $(date)"
echo "Namespace: $NS"
echo "Deploy: $DEPLOY"
echo "Service: $SVC"
echo "Container: $CONTAINER"
echo "Log: $LOG"
echo

echo "== 0) Sanity checks =="
kubectl version --client=true >/dev/null
kubectl -n "$NS" get deploy "$DEPLOY" >/dev/null
kubectl -n "$NS" get svc "$SVC" >/dev/null
echo "[OK] kubectl + resources exist"
echo

echo "== 1) Backup current deployment YAML =="
kubectl -n "$NS" get deploy "$DEPLOY" -o yaml > "/tmp/${DEPLOY}_before_hardening.yaml"
echo "[OK] saved: /tmp/${DEPLOY}_before_hardening.yaml"
echo

echo "== 2) Enforce safe RollingUpdate + progress deadline =="
# strategic merge patch (safe)
kubectl -n "$NS" patch deploy "$DEPLOY" --type merge -p "
spec:
  progressDeadlineSeconds: 300
  revisionHistoryLimit: 10
  strategy:
    type: RollingUpdate
    rollingUpdate:
      maxSurge: 1
      maxUnavailable: 0
"
echo "[OK] rolling update tuned"
echo

echo "== 3) Set Readiness/Liveness probes (HTTP / on :80) =="
# This overwrites/sets probes to known-good values
kubectl -n "$NS" patch deploy "$DEPLOY" --type merge -p "
spec:
  template:
    spec:
      containers:
      - name: ${CONTAINER}
        readinessProbe:
          httpGet:
            path: /
            port: 80
            scheme: HTTP
          initialDelaySeconds: 10
          periodSeconds: 10
          timeoutSeconds: 2
          failureThreshold: 3
          successThreshold: 1
        livenessProbe:
          httpGet:
            path: /
            port: 80
            scheme: HTTP
          initialDelaySeconds: 30
          periodSeconds: 20
          timeoutSeconds: 2
          failureThreshold: 3
          successThreshold: 1
"
echo "[OK] probes set"
echo

echo "== 4) Add resource requests/limits (prevents node starvation) =="
# conservative defaults; adjust later if needed
kubectl -n "$NS" set resources deploy/"$DEPLOY" -c "$CONTAINER" \
  --requests=cpu=100m,memory=256Mi \
  --limits=cpu=500m,memory=512Mi
echo "[OK] resources applied"
echo

echo "== 5) Ensure imagePullPolicy Always (avoid cached 'latest') =="
kubectl -n "$NS" patch deploy "$DEPLOY" --type merge -p "
spec:
  template:
    spec:
      containers:
      - name: ${CONTAINER}
        imagePullPolicy: Always
"
echo "[OK] imagePullPolicy=Always"
echo

echo "== 6) Apply HPA (Auto Scaling) =="
cat > /tmp/itverse_hpa.yaml <<YAML
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: itverse-web-hpa
  namespace: ${NS}
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: ${DEPLOY}
  minReplicas: 2
  maxReplicas: 6
  metrics:
  - type: Resource
    resource:
      name: cpu
      target:
        type: Utilization
        averageUtilization: 60
YAML

kubectl apply -f /tmp/itverse_hpa.yaml
echo "[OK] HPA applied: itverse-web-hpa (2..6 replicas, CPU 60%)"
echo

echo "== 7) Rollout + verify =="
kubectl -n "$NS" rollout status deploy/"$DEPLOY" --timeout=240s
echo

echo "== 8) Show current effective settings =="
echo "-- image --"
kubectl -n "$NS" get deploy "$DEPLOY" -o jsonpath='{.spec.template.spec.containers[0].image}{"\n"}'
echo "-- probes --"
kubectl -n "$NS" get deploy "$DEPLOY" -o jsonpath='{.spec.template.spec.containers[0].readinessProbe.httpGet.path}{" "}{.spec.template.spec.containers[0].readinessProbe.httpGet.port}{"\n"}' || true
kubectl -n "$NS" get deploy "$DEPLOY" -o jsonpath='{.spec.template.spec.containers[0].livenessProbe.httpGet.path}{" "}{.spec.template.spec.containers[0].livenessProbe.httpGet.port}{"\n"}' || true
echo "-- hpa --"
kubectl -n "$NS" get hpa itverse-web-hpa -o wide || true
echo

echo "== 9) External check =="
LB="$(kubectl -n "$NS" get svc "$SVC" -o jsonpath='{.status.loadBalancer.ingress[0].hostname}' 2>/dev/null || true)"
echo "LB=http://$LB/"
if [[ -n "${LB:-}" ]]; then
  code="$(curl -s -o /dev/null -w "%{http_code}" "http://$LB/")" || true
  echo "HTTP / => $code"
  echo "Scan homepage for PHP errors:"
  HTML="$(curl -s "http://$LB/" || true)"
  if echo "$HTML" | grep -qiE "fatal error|Unknown column|warning:|notice:"; then
    echo "❌ Found PHP errors in homepage HTML"
    echo "$HTML" | grep -nEi "fatal error|Unknown column|warning:|notice:" | head -n 80 || true
  else
    echo "✅ Homepage clean"
  fi
else
  echo "WARN: LB hostname not found yet."
fi

echo
echo "=== DONE ==="
echo "Backup: /tmp/${DEPLOY}_before_hardening.yaml"
echo "Log: $LOG"
echo
read -r -p "Press Enter to close... " _
