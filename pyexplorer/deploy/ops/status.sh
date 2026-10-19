#!/usr/bin/env bash
# Read-only diagnostics for CI, run over SSH by .github/workflows/explorer-ops.yml.
# Pin the ops key to this script in ~/.ssh/authorized_keys of the deploy user:
#
#   command="/opt/explorer/pyexplorer/deploy/ops/status.sh",restrict ssh-ed25519 AAAA... explorer-ops
#
# sshd then runs this script whatever the client asked for, and the request
# arrives in SSH_ORIGINAL_COMMAND. Only the actions below are accepted, none of
# them changes anything on the host.
#
# The repository is public, so whatever this prints ends up in a public Actions
# log. That is why node and Caddy logs (peer and visitor IP addresses) and
# anything from .env are never printed.
set -uo pipefail

read -r action _ <<<"${SSH_ORIGINAL_COMMAND:-${1:-status}}"
case "$action" in
  status | logs-indexer | logs-api | logs-migrate) ;;
  logs-indexer-testnet | logs-api-testnet | logs-migrate-testnet) ;;
  *)
    echo "unknown action '$action'; use status or logs-{indexer,api,migrate}[-testnet]" >&2
    exit 2
    ;;
esac

cd /opt/explorer/pyexplorer/deploy || exit 1
COMPOSE=(docker compose --env-file .env)
API_URL=http://127.0.0.1:8000
TESTNET_API_URL=http://127.0.0.1:8001   # api-testnet, see docker-compose.override.example.yaml
if [ -f /etc/explorer-ops.env ]; then
  API_URL=$(. /etc/explorer-ops.env; echo "${API_URL:-$API_URL}")
  TESTNET_API_URL=$(. /etc/explorer-ops.env; echo "${TESTNET_API_URL:-$TESTNET_API_URL}")
fi

case "$action" in
  status)
    echo "== release";      git -C /opt/explorer describe --tags --always
    echo "== containers";   "${COMPOSE[@]}" ps --format 'table {{.Service}}\t{{.Image}}\t{{.Status}}'
    echo "== index";        curl -sf --max-time 10 "$API_URL/api/v1/status" || echo "API $API_URL does not answer"
    echo
    echo "== meta"  # indexer bookkeeping: daily stats mark, one-off backfills
    "${COMPOSE[@]}" exec -T postgres psql -U explorer -d explorer -Atc \
      "SELECT key || ': ' || value FROM meta WHERE key <> 'status' ORDER BY key" 2>&1 | sed 's/^/  /'
    if [ -n "$("${COMPOSE[@]}" ps -q api-testnet 2>/dev/null)" ]; then
      echo "== index testnet"
      curl -sf --max-time 10 "$TESTNET_API_URL/api/v1/status" || echo "API $TESTNET_API_URL does not answer"
      echo
    fi
    echo "== disk";         df -h --output=size,used,avail,pcent / | sed 's/^/  /'
    echo "== memory";       free -m | sed 's/^/  /'
    docker stats --no-stream --format 'table {{.Name}}\t{{.MemUsage}}\t{{.CPUPerc}}' | sed 's/^/  /'
    echo "== health";       journalctl -t explorer-health -n 10 --no-pager -o short-iso 2>/dev/null || true
    ;;
  logs-*)
    "${COMPOSE[@]}" logs --no-color --timestamps --tail 200 "${action#logs-}"
    ;;
esac
