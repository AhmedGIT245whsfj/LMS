#!/usr/bin/env bash
set -euo pipefail

REGION="us-east-1"
CLUSTER="itverse-eks"
NODEGROUP="itverse-ng"
RDS_ID="itverse-rds"
NAMESPACE="itverse"
DEPLOYMENT="itverse-web"
DESIRED=2
MIN=2
MAX=2

MAIN_URL="http://a243d20a343754da691ffae2b29dcc32-913701681.us-east-1.elb.amazonaws.com/"
ADMIN_URL="http://a243d20a343754da691ffae2b29dcc32-913701681.us-east-1.elb.amazonaws.com/Admin/adminDashboard.php"

get_rds_status() {
  aws rds describe-db-instances \
    --db-instance-identifier "$RDS_ID" \
    --region "$REGION" \
    --query 'DBInstances[0].DBInstanceStatus' \
    --output text
}

RDS_STATUS="$(get_rds_status)"
echo "==> Current RDS status: $RDS_STATUS"

if [[ "$RDS_STATUS" == "stopping" ]]; then
  echo "==> Waiting for RDS to fully stop first"
  for i in $(seq 1 60); do
    RDS_STATUS="$(get_rds_status)"
    echo "RDS status: $RDS_STATUS"
    [[ "$RDS_STATUS" == "stopped" ]] && break
    sleep 10
  done
fi

RDS_STATUS="$(get_rds_status)"
if [[ "$RDS_STATUS" == "stopped" ]]; then
  echo "==> Starting RDS"
  aws rds start-db-instance \
    --db-instance-identifier "$RDS_ID" \
    --region "$REGION" >/dev/null

  echo "==> Waiting for RDS to become available"
  aws rds wait db-instance-available \
    --db-instance-identifier "$RDS_ID" \
    --region "$REGION"
elif [[ "$RDS_STATUS" == "starting" ]]; then
  echo "==> RDS already starting, waiting"
  aws rds wait db-instance-available \
    --db-instance-identifier "$RDS_ID" \
    --region "$REGION"
elif [[ "$RDS_STATUS" == "available" ]]; then
  echo "==> RDS already available"
else
  echo "==> Unexpected RDS status: $RDS_STATUS"
  exit 1
fi

echo "==> Scaling nodegroup up"
aws eks update-nodegroup-config \
  --cluster-name "$CLUSTER" \
  --nodegroup-name "$NODEGROUP" \
  --region "$REGION" \
  --scaling-config minSize="$MIN",maxSize="$MAX",desiredSize="$DESIRED" >/dev/null

echo "==> Updating kubeconfig"
aws eks update-kubeconfig \
  --region "$REGION" \
  --name "$CLUSTER" >/dev/null

echo "==> Waiting for nodes"
for i in $(seq 1 60); do
  READY="$(kubectl get nodes --no-headers 2>/dev/null | awk '$2=="Ready"{c++} END{print c+0}')"
  echo "Ready nodes: ${READY}/${DESIRED}"
  if [[ "${READY}" -ge "${DESIRED}" ]]; then
    break
  fi
  sleep 10
done

echo "==> Waiting for deployment"
kubectl -n "$NAMESPACE" rollout status deployment/"$DEPLOYMENT" --timeout=300s

echo
kubectl -n "$NAMESPACE" get pods -o wide
echo
kubectl -n "$NAMESPACE" get svc itverse-web-lb

echo
echo "Main URL : $MAIN_URL"
echo "Admin URL: $ADMIN_URL"
