#!/usr/bin/env bash
set -euo pipefail

LOG="/tmp/itverse_cd_oidc_fix.log"
exec > >(tee -a "$LOG") 2>&1

echo "=== START $(date) ==="

# ---- Config (edit if needed) ----
AWS_REGION="us-east-1"
EKS_CLUSTER_NAME="itverse-eks"
GITHUB_OWNER="AhmedGIT245whsfj"
GITHUB_REPO="LMS"
ROLE_NAME="itverse-github-actions-cd"
K8S_NAMESPACE="itverse"

echo "Region=$AWS_REGION"
echo "Cluster=$EKS_CLUSTER_NAME"
echo "Repo=$GITHUB_OWNER/$GITHUB_REPO"
echo "Role=$ROLE_NAME"
echo "Namespace=$K8S_NAMESPACE"

# ---- 0) Sanity checks ----
command -v aws >/dev/null
command -v kubectl >/dev/null
command -v python3 >/dev/null

aws sts get-caller-identity >/dev/null
kubectl version --client >/dev/null

ACCOUNT_ID="$(aws sts get-caller-identity --query Account --output text)"
echo "AWS Account: $ACCOUNT_ID"

# ---- 1) Ensure IAM OIDC provider for GitHub exists ----
# GitHub OIDC issuer
OIDC_URL="https://token.actions.githubusercontent.com"
OIDC_HOST="token.actions.githubusercontent.com"

echo "== Ensure GitHub OIDC provider exists =="
EXISTING_PROVIDER_ARN="$(aws iam list-open-id-connect-providers \
  --query "OpenIDConnectProviderList[?contains(Arn, \`token.actions.githubusercontent.com\`)].Arn | [0]" \
  --output text)"

if [[ "$EXISTING_PROVIDER_ARN" == "None" || -z "$EXISTING_PROVIDER_ARN" ]]; then
  echo "No existing GitHub OIDC provider found. Creating..."
  aws iam create-open-id-connect-provider \
    --url "$OIDC_URL" \
    --client-id-list "sts.amazonaws.com" \
    --thumbprint-list "6938fd4d98bab03faadb97b34396831e3780aea1" >/dev/null
  echo "Created GitHub OIDC provider."
else
  echo "Found OIDC provider: $EXISTING_PROVIDER_ARN"
fi

PROVIDER_ARN="$(aws iam list-open-id-connect-providers \
  --query "OpenIDConnectProviderList[?contains(Arn, \`token.actions.githubusercontent.com\`)].Arn | [0]" \
  --output text)"
if [[ "$PROVIDER_ARN" == "None" || -z "$PROVIDER_ARN" ]]; then
  echo "ERROR: Could not detect GitHub OIDC provider ARN"
  exit 1
fi
echo "Using OIDC provider ARN: $PROVIDER_ARN"

# ---- 2) Create/Update IAM role for GitHub Actions ----
echo "== Create/Update IAM Role =="
TRUST_JSON="/tmp/itverse_github_oidc_trust.json"
cat > "$TRUST_JSON" <<EOF
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": { "Federated": "${PROVIDER_ARN}" },
      "Action": "sts:AssumeRoleWithWebIdentity",
      "Condition": {
        "StringEquals": {
          "${OIDC_HOST}:aud": "sts.amazonaws.com"
        },
        "StringLike": {
          "${OIDC_HOST}:sub": "repo:${GITHUB_OWNER}/${GITHUB_REPO}:*"
        }
      }
    }
  ]
}
EOF

ROLE_ARN="$(aws iam get-role --role-name "$ROLE_NAME" --query Role.Arn --output text 2>/dev/null || true)"
if [[ -z "${ROLE_ARN:-}" ]]; then
  echo "Role not found. Creating..."
  aws iam create-role \
    --role-name "$ROLE_NAME" \
    --assume-role-policy-document "file://${TRUST_JSON}" >/dev/null
  ROLE_ARN="$(aws iam get-role --role-name "$ROLE_NAME" --query Role.Arn --output text)"
  echo "Created role: $ROLE_ARN"
else
  echo "Role exists: $ROLE_ARN"
  echo "Updating trust policy..."
  aws iam update-assume-role-policy \
    --role-name "$ROLE_NAME" \
    --policy-document "file://${TRUST_JSON}" >/dev/null
  echo "Updated trust policy."
fi

# Minimal AWS permissions for deployment flow:
# - DescribeCluster (aws eks update-kubeconfig needs it)
# Kubernetes auth itself is via aws-auth mapping; kubectl actions are RBAC inside cluster.
POLICY_JSON="/tmp/itverse_github_cd_policy.json"
cat > "$POLICY_JSON" <<EOF
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "EKSDescribe",
      "Effect": "Allow",
      "Action": [
        "eks:DescribeCluster"
      ],
      "Resource": "*"
    }
  ]
}
EOF

POLICY_NAME="itverse-github-actions-cd-policy"
POLICY_ARN="$(aws iam list-policies --scope Local \
  --query "Policies[?PolicyName=='${POLICY_NAME}'].Arn | [0]" --output text)"

if [[ "$POLICY_ARN" == "None" || -z "$POLICY_ARN" ]]; then
  echo "Creating inline customer managed policy..."
  POLICY_ARN="$(aws iam create-policy --policy-name "$POLICY_NAME" --policy-document "file://${POLICY_JSON}" --query Policy.Arn --output text)"
  echo "Created policy: $POLICY_ARN"
else
  echo "Policy exists: $POLICY_ARN"
  echo "Updating policy version..."
  aws iam create-policy-version --policy-arn "$POLICY_ARN" --policy-document "file://${POLICY_JSON}" --set-as-default >/dev/null
  echo "Policy updated."
fi

echo "Attaching policy to role..."
aws iam attach-role-policy --role-name "$ROLE_NAME" --policy-arn "$POLICY_ARN" >/dev/null || true
echo "Attached."

# ---- 3) Map IAM role into EKS aws-auth (gives kubectl permissions) ----
# For a graduation project, simplest is system:masters. Later you can tighten RBAC.
echo "== Map Role into EKS aws-auth =="
kubectl -n kube-system get configmap aws-auth >/dev/null

python3 - <<PY
import subprocess, json, textwrap

role_arn = "${ROLE_ARN}"
username = "github-actions"
groups = ["system:masters"]

# Get current aws-auth
raw = subprocess.check_output(["kubectl","-n","kube-system","get","configmap","aws-auth","-o","json"], text=True)
cm = json.loads(raw)
data = cm.get("data", {})
map_roles = data.get("mapRoles", "") or ""

entry = textwrap.dedent(f"""\
- rolearn: {role_arn}
  username: {username}
  groups:
""") + "\n".join([f"    - {g}" for g in groups]) + "\n"

if role_arn in map_roles:
    print("[OK] Role already mapped in aws-auth.")
else:
    new_map = (map_roles.rstrip() + "\n" + entry).lstrip()
    data["mapRoles"] = new_map
    cm["data"] = data
    # Apply patched configmap
    patch = json.dumps({"data": {"mapRoles": new_map}})
    subprocess.check_call(["kubectl","-n","kube-system","patch","configmap","aws-auth","--type","merge","-p", patch])
    print("[OK] Added role mapping to aws-auth.")
PY

# ---- 4) Patch GitHub Actions workflow to use OIDC instead of KUBECONFIG_B64 ----
WF=".github/workflows/dockerhub.yml"
echo "== Patch workflow: $WF =="
test -f "$WF"

python3 - <<'PY'
from pathlib import Path
import re

wf = Path(".github/workflows/dockerhub.yml")
s = wf.read_text(encoding="utf-8", errors="ignore")

# Ensure permissions include id-token: write for OIDC
if re.search(r"permissions:\s*\n(?:[^\n]*\n)*?\s*id-token:\s*write", s) is None:
    # If permissions block exists, add id-token under it; else add a new permissions block
    if "permissions:" in s:
        s = re.sub(r"(permissions:\s*\n)", r"\1  id-token: write\n", s, count=1)
    else:
        s = s.replace("on:\n", "on:\n\npermissions:\n  contents: read\n  id-token: write\n", 1)

# Add AWS env defaults (non-secret)
if "AWS_REGION" not in s:
    s = s.replace("env:\n  IMAGE_NAME: itverse-web\n", "env:\n  IMAGE_NAME: itverse-web\n  AWS_REGION: us-east-1\n  EKS_CLUSTER_NAME: itverse-eks\n", 1)

# Remove old kubectl install block if present? Keep it, but we'll replace deploy step content.
# Replace "Deploy to EKS (rolling update)" step with OIDC-based flow.
pattern = r"- name: Deploy to EKS \(rolling update\)(?:.|\n)*?(?=\n\s*- name:|\n\s*$)"
m = re.search(pattern, s)
deploy_step = """- name: Deploy to EKS (rolling update)
        if: github.ref == 'refs/heads/main'
        uses: aws-actions/configure-aws-credentials@v4
        with:
          role-to-assume: ${{ secrets.AWS_ROLE_ARN }}
          aws-region: ${{ env.AWS_REGION }}

      - name: Install kubectl
        if: github.ref == 'refs/heads/main'
        uses: azure/setup-kubectl@v4
        with:
          version: 'latest'

      - name: Rollout restart in EKS
        if: github.ref == 'refs/heads/main'
        run: |
          set -e
          aws eks update-kubeconfig --name "${{ env.EKS_CLUSTER_NAME }}" --region "${{ env.AWS_REGION }}"
          kubectl -n itverse rollout restart deploy/itverse-web
          kubectl -n itverse rollout status deploy/itverse-web --timeout=240s
          echo "Deployed. Current image:"
          kubectl -n itverse get deploy itverse-web -o jsonpath='{.spec.template.spec.containers[0].image}{"\\n"}'
"""
if m:
    s = s[:m.start()] + deploy_step + s[m.end():]
else:
    # If deploy step not found, append at end of steps in job
    s += "\n\n# --- CD steps added by itverse_cd_oidc_fix.sh ---\n" + deploy_step + "\n"

wf.write_text(s, encoding="utf-8")
print("[OK] workflow patched for OIDC CD")
PY

echo "== Show workflow tail =="
tail -n 120 "$WF"

echo
echo "=== IMPORTANT: Add GitHub Secret now ==="
echo "Go to GitHub repo -> Settings -> Secrets and variables -> Actions -> New repository secret"
echo "Name: AWS_ROLE_ARN"
echo "Value: $ROLE_ARN"
echo
echo "=== DONE $(date) ==="
echo "Log: $LOG"
