#!/usr/bin/env bash
set -euo pipefail

REGION="us-east-1"
CLUSTER="itverse-eks"
NODEGROUP="itverse-ng"
RDS_ID="itverse-rds"

echo "==> Scaling nodegroup down"
aws eks update-nodegroup-config \
  --cluster-name "$CLUSTER" \
  --nodegroup-name "$NODEGROUP" \
  --region "$REGION" \
  --scaling-config minSize=0,maxSize=1,desiredSize=0 >/dev/null

RDS_STATUS="$(aws rds describe-db-instances \
  --db-instance-identifier "$RDS_ID" \
  --region "$REGION" \
  --query 'DBInstances[0].DBInstanceStatus' \
  --output text)"

echo "==> Current RDS status: $RDS_STATUS"
if [[ "$RDS_STATUS" != "stopped" && "$RDS_STATUS" != "stopping" ]]; then
  echo "==> Stopping RDS"
  aws rds stop-db-instance \
    --db-instance-identifier "$RDS_ID" \
    --region "$REGION" >/dev/null
else
  echo "==> RDS already stopping/stopped"
fi

echo
echo "==> Status"
aws eks describe-nodegroup \
  --cluster-name "$CLUSTER" \
  --nodegroup-name "$NODEGROUP" \
  --region "$REGION" \
  --query 'nodegroup.scalingConfig' \
  --output table

aws rds describe-db-instances \
  --db-instance-identifier "$RDS_ID" \
  --region "$REGION" \
  --query 'DBInstances[0].DBInstanceStatus' \
  --output text
