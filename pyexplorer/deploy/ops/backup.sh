#!/usr/bin/env bash
# Nightly dump of the explorer database, run by explorer-backup.timer.
#
# The index can always be rebuilt from the node (about 2 hours on one core),
# so the dump is a shortcut, not the only copy: keep a couple, plus one off
# the host if BACKUP_UPLOAD is set.
#
# Settings (all optional) come from /etc/explorer-ops.env:
#   BACKUP_DIR     default /var/backups/explorer
#   BACKUP_KEEP    dumps kept on this host, default 2
#   BACKUP_UPLOAD  command run with the new dump's path as $1, e.g.
#                  'rclone copy "$1" r2:explorer-backups/' ; failures are logged
#   COMPOSE_DIR    default /opt/explorer/pyexplorer/deploy
set -euo pipefail

[ -f /etc/explorer-ops.env ] && . /etc/explorer-ops.env
BACKUP_DIR=${BACKUP_DIR:-/var/backups/explorer}
BACKUP_KEEP=${BACKUP_KEEP:-2}
COMPOSE_DIR=${COMPOSE_DIR:-/opt/explorer/pyexplorer/deploy}

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"
out="$BACKUP_DIR/explorer-$(date -u +%Y%m%dT%H%MZ).dump"
tmp="$out.partial"

cd "$COMPOSE_DIR"
start=$(date +%s)
# Custom format: compressed, restorable table by table, in parallel (pg_restore -j).
docker compose --env-file .env exec -T postgres \
  pg_dump -U explorer -d explorer -Fc -Z 6 >"$tmp"
mv "$tmp" "$out"
logger -t explorer-backup "dumped $(du -h "$out" | cut -f1) to $out in $(($(date +%s) - start))s"

# Rotate: keep the newest BACKUP_KEEP dumps.
ls -1t "$BACKUP_DIR"/explorer-*.dump | tail -n +"$((BACKUP_KEEP + 1))" | xargs -r rm -f --

if [ -n "${BACKUP_UPLOAD:-}" ]; then
  bash -c "$BACKUP_UPLOAD" _ "$out" ||
    logger -t explorer-backup "upload failed: $BACKUP_UPLOAD"
fi
