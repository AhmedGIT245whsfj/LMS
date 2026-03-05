#!/bin/bash

NS=itverse

echo "=== Restart web ==="
kubectl -n $NS rollout restart deploy/itverse-web

echo "=== Wait rollout ==="
kubectl -n $NS rollout status deploy/itverse-web

echo "=== Get LoadBalancer ==="
LB=$(kubectl -n $NS get svc itverse-web-lb -o jsonpath='{.status.loadBalancer.ingress[0].hostname}')

echo "LB=http://$LB/"

echo "=== Check homepage ==="
curl -s "http://$LB/" | grep -nEi "fatal|error|warning|notice" || echo "Homepage OK"

echo "=== DONE ==="
