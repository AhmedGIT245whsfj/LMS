#!/usr/bin/env bash
set -euo pipefail

WF=".github/workflows/dockerhub.yml"
LOG="/tmp/itverse_add_cd_workflow.log"

exec > >(tee -a "$LOG") 2>&1
echo "=== START $(date) ==="

if [[ ! -f "$WF" ]]; then
  echo "ERROR: $WF not found"
  exit 1
fi

# If already added, exit clean
if grep -q "Deploy to EKS (rolling update)" "$WF"; then
  echo "Already contains CD deploy steps. Nothing to do."
  exit 0
fi

# Insert steps right after the build-push-action step block (after cache-to line)
python3 - <<'PY'
from pathlib import Path

wf = Path(".github/workflows/dockerhub.yml")
s = wf.read_text(encoding="utf-8", errors="ignore").splitlines()

insert_after = None
for i, line in enumerate(s):
    if "cache-to:" in line and "type=gha" in line:
        insert_after = i
        # keep scanning in case there is another, but usually last is fine
if insert_after is None:
    raise SystemExit("ERROR: Could not find insertion point (cache-to: type=gha,mode=max).")

block = [
"",
"      - name: Install kubectl",
"        if: github.ref == 'refs/heads/main'",
"        uses: azure/setup-kubectl@v4",
"        with:",
"          version: 'latest'",
"",
"      - name: Deploy to EKS (rolling update)",
"        if: github.ref == 'refs/heads/main'",
"        env:",
"          KUBECONFIG_B64: ${{ secrets.KUBECONFIG_B64 }}",
"        run: |",
"          set -e",
"          echo \"$KUBECONFIG_B64\" | base64 -d > kubeconfig",
"          export KUBECONFIG=$PWD/kubeconfig",
"",
"          kubectl -n itverse rollout restart deploy/itverse-web",
"          kubectl -n itverse rollout status deploy/itverse-web --timeout=240s",
"",
"          echo \"Deployed. Current image:\"",
"          kubectl -n itverse get deploy itverse-web -o jsonpath='{.spec.template.spec.containers[0].image}{\"\\n\"}'",
""
]

out = s[:insert_after+1] + block + s[insert_after+1:]
wf.write_text("\n".join(out) + "\n", encoding="utf-8")
print("[OK] patched:", wf)
PY

echo "=== SHOW PATCHED FILE (tail) ==="
tail -n 80 "$WF"

echo "=== DONE $(date) ==="
echo "Log: $LOG"
