#!/usr/bin/env bash
# Explorer health check, run every few minutes by explorer-healthcheck.timer.
#
# Checks that the API answers, the index keeps up with the node, the indexer
# is not crash-looping and the disk is not filling up. Alerts only when the
# state changes (ok -> failing, failing -> ok), so a long outage sends two
# messages, not one every run.
#
# Settings (all optional) come from /etc/explorer-ops.env:
#   API_URL           default http://127.0.0.1:8000
#   PUBLIC_URL        e.g. https://explorer3.emercoin.com (checked if set)
#   MAX_LAG           blocks the index may trail the node, default 10
#   MAX_STALE         seconds since the indexer last polled the node, default 600
#   MIN_FREE_GB       default 2
#   TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID   send alerts to Telegram if both set
set -uo pipefail

[ -f /etc/explorer-ops.env ] && . /etc/explorer-ops.env
API_URL=${API_URL:-http://127.0.0.1:8000}
MAX_LAG=${MAX_LAG:-10}
MAX_STALE=${MAX_STALE:-600}
MIN_FREE_GB=${MIN_FREE_GB:-2}
STATE_DIR=/var/lib/explorer-ops
mkdir -p "$STATE_DIR"

problems=()

status=$(curl -sf --max-time 10 "$API_URL/api/v1/status") || problems+=("API $API_URL does not answer")
if [ -n "$status" ]; then
  read -r indexed node updated < <(python3 -c '
import json, sys
d = json.load(sys.stdin)
print(d.get("indexed_height") or 0, d.get("node_height") or 0, d.get("updated_at") or 0)' <<<"$status")
  lag=$((node - indexed))
  age=$(($(date +%s) - updated))
  [ "$lag" -gt "$MAX_LAG" ] && problems+=("index is $lag blocks behind the node ($indexed / $node)")
  [ "$age" -gt "$MAX_STALE" ] && problems+=("indexer has not polled the node for ${age}s")
fi

if [ -n "${PUBLIC_URL:-}" ]; then
  curl -sf --max-time 15 -o /dev/null "$PUBLIC_URL/api/v1/status" || problems+=("$PUBLIC_URL does not answer")
fi

# A growing restart count means a crash loop even if the API still answers.
for c in explorer-indexer explorer-api explorer-postgres; do
  st=$(docker inspect -f '{{.State.Status}} {{.RestartCount}}' "$c" 2>/dev/null) || { problems+=("container $c is missing"); continue; }
  read -r state restarts <<<"$st"
  [ "$state" != running ] && problems+=("container $c is $state")
  prev=$(cat "$STATE_DIR/$c.restarts" 2>/dev/null || echo "$restarts")
  [ "$restarts" -gt "$prev" ] && problems+=("container $c restarted $((restarts - prev)) time(s)")
  echo "$restarts" >"$STATE_DIR/$c.restarts"
done

free_gb=$(df -BG --output=avail / | tail -1 | tr -dc 0-9)
[ "$free_gb" -lt "$MIN_FREE_GB" ] && problems+=("only ${free_gb}G free on /")

notify() {
  logger -t explorer-health "$1"
  if [ -n "${TELEGRAM_BOT_TOKEN:-}" ] && [ -n "${TELEGRAM_CHAT_ID:-}" ]; then
    curl -sf --max-time 10 -o /dev/null \
      "https://api.telegram.org/bot$TELEGRAM_BOT_TOKEN/sendMessage" \
      --data-urlencode "chat_id=$TELEGRAM_CHAT_ID" --data-urlencode "text=$1" ||
      logger -t explorer-health "telegram notification failed"
  fi
}

host=$(hostname)
was=$(cat "$STATE_DIR/state" 2>/dev/null || echo ok)
if [ ${#problems[@]} -eq 0 ]; then
  [ "$was" != ok ] && notify "explorer on $host: OK again"
  echo ok >"$STATE_DIR/state"
else
  msg="explorer on $host: $(printf '%s; ' "${problems[@]}")"
  [ "$was" = ok ] && notify "${msg%; }"
  echo failing >"$STATE_DIR/state"
  echo "${msg%; }" >&2
  exit 1
fi
