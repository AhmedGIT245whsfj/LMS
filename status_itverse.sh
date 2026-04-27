#!/usr/bin/env bash
set -euo pipefail

REGION="us-east-1"
CLUSTER="itverse-eks"
NODEGROUP="itverse-ng"
RDS_ID="itverse-rds"
NAMESPACE="itverse"

echo "==> Nodegroup"
aws eks describe-nodegroup \
  --cluster-name "$CLUSTER" \
  --nodegroup-name "$NODEGROUP" \
  --region "$REGION" \
  --query 'nodegroup.scalingConfig' \
  --output table

echo
echo "==> RDS"
aws rds describe-db-instances \
  --db-instance-identifier "$RDS_ID" \
  --region "$REGION" \
  --query 'DBInstances[0].DBInstanceStatus' \
  --output text

echo
echo "==> Nodes"
kubectl get nodes 2>/dev/null || true

echo
echo "==> Pods"
kubectl -n "$NAMESPACE" get pods 2>/dev/null || true

echo
echo "==> Service"
kubectl -n "$NAMESPACE" get svc itverse-web-lb 2>/dev/null || true
