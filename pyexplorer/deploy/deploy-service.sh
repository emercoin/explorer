#!/usr/bin/env bash
# Release rollout, invoked by CI over SSH after it has pushed ghcr.io/emercoin/explorer:<tag>
# (see .github/workflows/explorer-release.yml):
#
#   bash /opt/explorer/pyexplorer/deploy/deploy-service.sh v0.2.0
#
# Checks out the same tag, so compose/Caddyfile always match the image, runs the
# schema migration, then recreates indexer and api. The node, PostgreSQL and Caddy
# are left running unless their compose definition changed. With the testnet
# profile on (COMPOSE_PROFILES in .env), `up` updates the testnet services too;
# migrate-testnet runs as their dependency.
#
# The CI key is pinned to this script in ~/.ssh/authorized_keys of the deploy user:
#   command="/opt/explorer/pyexplorer/deploy/deploy-service.sh ${SSH_ORIGINAL_COMMAND##* }",restrict ssh-ed25519 AAAA... explorer-ci
# sshd then runs only this script and passes it the last word the client sent
# (the tag), which the check below validates.
set -euo pipefail

tag="${1:-}"
if [[ ! "$tag" =~ ^v[0-9]+\.[0-9]+\.[0-9]+([.-][0-9A-Za-z.-]+)?$ ]]; then
  echo "usage: deploy-service.sh vMAJOR.MINOR.PATCH" >&2
  exit 2
fi

REPO=/opt/explorer
cd "$REPO"
git fetch -q --tags origin
before=$(git rev-parse HEAD)
git -c advice.detachedHead=false checkout -q "refs/tags/$tag"
changed=$(git diff --name-only "$before" HEAD)

cd "$REPO/pyexplorer/deploy"
# Record the running version in .env so a manual `docker compose up` keeps it.
sed -i "s/^EXPLORER_TAG=.*/EXPLORER_TAG=${tag#v}/" .env
COMPOSE=(docker compose --env-file .env)

"${COMPOSE[@]}" pull migrate indexer api
"${COMPOSE[@]}" run --rm migrate
# The indexer writes each block in one transaction, so stopping it mid-sync is safe.
"${COMPOSE[@]}" up -d --no-build --remove-orphans
# The Caddyfile is a single-file bind mount: checkout replaces its inode and the
# running container would keep the old file, so recreate Caddy when it changed.
# Only if it runs: naming a service switches its profile on, and a host that
# disables Caddy in the override (another web server on :80) must keep it off.
if grep -q '^pyexplorer/deploy/Caddyfile$' <<<"$changed" &&
  [ -n "$("${COMPOSE[@]}" ps -q caddy 2>/dev/null)" ]; then
  "${COMPOSE[@]}" up -d --no-build --force-recreate caddy
fi
docker image prune -f
echo "deployed $tag"
"${COMPOSE[@]}" ps
