#!/bin/sh
# Idempotent one-shot Garage bootstrap. Runs in the garage-setup container, which shares the garage
# service's network namespace and mounts its config + meta volume, so the CLI talks to the local node.
# Required env: GARAGE_BUCKET, GARAGE_KEY_NAME, GARAGE_ACCESS_KEY_ID (GK + 24 hex), GARAGE_SECRET_ACCESS_KEY (64 hex).
# Optional: GARAGE_CAPACITY (default 10G), GARAGE_ZONE (default dc1).
set -eu

: "${GARAGE_BUCKET:?}" "${GARAGE_KEY_NAME:?}" "${GARAGE_ACCESS_KEY_ID:?}" "${GARAGE_SECRET_ACCESS_KEY:?}"
CAPACITY="${GARAGE_CAPACITY:-10G}"
ZONE="${GARAGE_ZONE:-dc1}"
G="garage"

# Wait for the node to answer RPC.
i=0
until $G status >/dev/null 2>&1; do
  i=$((i + 1)); [ "$i" -gt 30 ] && { echo "garage not reachable" >&2; exit 1; }
  sleep 2
done

# 1. Layout: assign + apply only when no layout has been applied yet.
if $G layout show 2>/dev/null | grep -q "Current cluster layout version: 0"; then
  NODE_ID=$($G node id -q 2>/dev/null | cut -d@ -f1)
  $G layout assign -z "$ZONE" -c "$CAPACITY" "$NODE_ID"
  $G layout apply --version 1
  sleep 3
else
  echo "layout already applied"
fi

# 2. Bucket (create only if missing).
if ! $G bucket info "$GARAGE_BUCKET" >/dev/null 2>&1; then
  $G bucket create "$GARAGE_BUCKET"
fi

# 3. Key imported from .env so Laravel's AWS_* values are the source of truth (import only if missing).
if ! $G key info "$GARAGE_ACCESS_KEY_ID" >/dev/null 2>&1; then
  $G key import --yes -n "$GARAGE_KEY_NAME" "$GARAGE_ACCESS_KEY_ID" "$GARAGE_SECRET_ACCESS_KEY"
fi

# 4. Grant (idempotent).
$G bucket allow --read --write --owner "$GARAGE_BUCKET" --key "$GARAGE_ACCESS_KEY_ID"
echo "garage setup complete"
