#!/usr/bin/env bash
set -euo pipefail

REGION="us-east-1"
NAMESPACE="itverse"
SERVICE="itverse-web-lb"
RDS_ID="itverse-rds"
DASHBOARD_NAME="itverse-ops-dashboard"

LB_DNS="$(kubectl -n "$NAMESPACE" get svc "$SERVICE" -o jsonpath='{.status.loadBalancer.ingress[0].hostname}' 2>/dev/null || true)"

LB_KIND="none"
LB_DIM=""
LB_NAMESPACE=""
TG_DIM=""

if [[ -n "${LB_DNS}" ]]; then
  # Try ELBv2 first
  V2_INFO="$(aws elbv2 describe-load-balancers --region "$REGION" \
    --query "LoadBalancers[?DNSName=='${LB_DNS}'].[Type,LoadBalancerArn]" \
    --output text 2>/dev/null || true)"

  if [[ -n "${V2_INFO}" ]]; then
    LB_TYPE="$(echo "$V2_INFO" | awk '{print $1}')"
    LB_ARN="$(echo "$V2_INFO" | awk '{print $2}')"
    LB_DIM="${LB_ARN#*:loadbalancer/}"

    if [[ "$LB_TYPE" == "application" ]]; then
      LB_KIND="alb"
      LB_NAMESPACE="AWS/ApplicationELB"
      TG_ARN="$(aws elbv2 describe-target-groups --region "$REGION" \
        --load-balancer-arn "$LB_ARN" \
        --query 'TargetGroups[0].TargetGroupArn' \
        --output text 2>/dev/null || true)"
      [[ -n "${TG_ARN}" && "${TG_ARN}" != "None" ]] && TG_DIM="${TG_ARN#*:targetgroup/}"
    elif [[ "$LB_TYPE" == "network" ]]; then
      LB_KIND="nlb"
      LB_NAMESPACE="AWS/NetworkELB"
    fi
  else
    # Fallback: Classic ELB
    CLB_NAME="$(aws elb describe-load-balancers --region "$REGION" \
      --query "LoadBalancerDescriptions[?DNSName=='${LB_DNS}'].LoadBalancerName" \
      --output text 2>/dev/null || true)"
    if [[ -n "${CLB_NAME}" && "${CLB_NAME}" != "None" ]]; then
      LB_KIND="clb"
      LB_NAMESPACE="AWS/ELB"
      LB_DIM="$CLB_NAME"
    fi
  fi
fi

cat > /tmp/itverse-dashboard.json <<EOF
{
  "widgets": [
    {
      "type": "metric",
      "x": 0, "y": 0, "width": 12, "height": 6,
      "properties": {
        "title": "RDS CPU Utilization",
        "region": "$REGION",
        "view": "timeSeries",
        "stacked": false,
        "metrics": [
          [ "AWS/RDS", "CPUUtilization", "DBInstanceIdentifier", "$RDS_ID" ]
        ],
        "period": 300
      }
    },
    {
      "type": "metric",
      "x": 12, "y": 0, "width": 12, "height": 6,
      "properties": {
        "title": "RDS Database Connections",
        "region": "$REGION",
        "view": "timeSeries",
        "stacked": false,
        "metrics": [
          [ "AWS/RDS", "DatabaseConnections", "DBInstanceIdentifier", "$RDS_ID" ]
        ],
        "period": 300
      }
    },
    {
      "type": "metric",
      "x": 0, "y": 6, "width": 12, "height": 6,
      "properties": {
        "title": "RDS Free Storage Space",
        "region": "$REGION",
        "view": "timeSeries",
        "stacked": false,
        "metrics": [
          [ "AWS/RDS", "FreeStorageSpace", "DBInstanceIdentifier", "$RDS_ID" ]
        ],
        "period": 300
      }
    }
  ]
}
EOF

# Add LB widgets if discovered
if [[ "$LB_KIND" == "alb" ]]; then
  python3 - <<PY
import json
p="/tmp/itverse-dashboard.json"
d=json.load(open(p))
d["widgets"] += [
  {
    "type":"metric","x":12,"y":6,"width":12,"height":6,
    "properties":{
      "title":"ALB Request Count",
      "region":"$REGION","view":"timeSeries","stacked":False,
      "metrics":[["AWS/ApplicationELB","RequestCount","LoadBalancer","$LB_DIM"]],
      "period":300,"stat":"Sum"
    }
  },
  {
    "type":"metric","x":0,"y":12,"width":12,"height":6,
    "properties":{
      "title":"ALB 5XX Count",
      "region":"$REGION","view":"timeSeries","stacked":False,
      "metrics":[["AWS/ApplicationELB","HTTPCode_ELB_5XX_Count","LoadBalancer","$LB_DIM"]],
      "period":300,"stat":"Sum"
    }
  }
]
if "$TG_DIM":
    d["widgets"].append({
      "type":"metric","x":12,"y":12,"width":12,"height":6,
      "properties":{
        "title":"ALB Healthy Hosts",
        "region":"$REGION","view":"timeSeries","stacked":False,
        "metrics":[["AWS/ApplicationELB","HealthyHostCount","TargetGroup","$TG_DIM","LoadBalancer","$LB_DIM"]],
        "period":300,"stat":"Average"
      }
    })
json.dump(d, open(p,"w"))
PY
elif [[ "$LB_KIND" == "clb" ]]; then
  python3 - <<PY
import json
p="/tmp/itverse-dashboard.json"
d=json.load(open(p))
d["widgets"] += [
  {
    "type":"metric","x":12,"y":6,"width":12,"height":6,
    "properties":{
      "title":"ELB Request Count",
      "region":"$REGION","view":"timeSeries","stacked":False,
      "metrics":[["AWS/ELB","RequestCount","LoadBalancerName","$LB_DIM"]],
      "period":300,"stat":"Sum"
    }
  },
  {
    "type":"metric","x":0,"y":12,"width":12,"height":6,
    "properties":{
      "title":"ELB Latency",
      "region":"$REGION","view":"timeSeries","stacked":False,
      "metrics":[["AWS/ELB","Latency","LoadBalancerName","$LB_DIM"]],
      "period":300,"stat":"Average"
    }
  }
]
json.dump(d, open(p,"w"))
PY
fi

echo "==> Creating dashboard: $DASHBOARD_NAME"
aws cloudwatch put-dashboard \
  --dashboard-name "$DASHBOARD_NAME" \
  --dashboard-body file:///tmp/itverse-dashboard.json \
  --region "$REGION" >/dev/null

echo "==> Creating RDS alarms"
aws cloudwatch put-metric-alarm \
  --alarm-name "itverse-rds-high-cpu" \
  --alarm-description "RDS CPU > 70% for 10 minutes" \
  --namespace AWS/RDS \
  --metric-name CPUUtilization \
  --dimensions Name=DBInstanceIdentifier,Value="$RDS_ID" \
  --statistic Average \
  --period 300 \
  --evaluation-periods 2 \
  --threshold 70 \
  --comparison-operator GreaterThanThreshold \
  --treat-missing-data notBreaching \
  --region "$REGION" >/dev/null

aws cloudwatch put-metric-alarm \
  --alarm-name "itverse-rds-low-storage" \
  --alarm-description "RDS free storage < 5 GB" \
  --namespace AWS/RDS \
  --metric-name FreeStorageSpace \
  --dimensions Name=DBInstanceIdentifier,Value="$RDS_ID" \
  --statistic Average \
  --period 300 \
  --evaluation-periods 1 \
  --threshold 5368709120 \
  --comparison-operator LessThanThreshold \
  --treat-missing-data notBreaching \
  --region "$REGION" >/dev/null

if [[ "$LB_KIND" == "alb" ]]; then
  echo "==> Creating ALB 5XX alarm"
  aws cloudwatch put-metric-alarm \
    --alarm-name "itverse-alb-5xx" \
    --alarm-description "ALB 5XX count > 5 in 5 minutes" \
    --namespace AWS/ApplicationELB \
    --metric-name HTTPCode_ELB_5XX_Count \
    --dimensions Name=LoadBalancer,Value="$LB_DIM" \
    --statistic Sum \
    --period 300 \
    --evaluation-periods 1 \
    --threshold 5 \
    --comparison-operator GreaterThanThreshold \
    --treat-missing-data notBreaching \
    --region "$REGION" >/dev/null
fi

echo
echo "==> Summary"
echo "LB_DNS      = ${LB_DNS:-none}"
echo "LB_KIND     = ${LB_KIND}"
echo "LB_DIM      = ${LB_DIM:-none}"
echo "TG_DIM      = ${TG_DIM:-none}"
echo "DASHBOARD   = $DASHBOARD_NAME"

echo
echo "==> Dashboards"
aws cloudwatch list-dashboards --region "$REGION" \
  --query 'DashboardEntries[].DashboardName' --output table

echo
echo "==> Alarms"
aws cloudwatch describe-alarms --region "$REGION" \
  --query 'MetricAlarms[].AlarmName' --output table
