#!/bin/bash
set -euo pipefail

DEV="${BDC_DEV_PATH:-/home2/zqculgmy/BDC_DEV}"
STAGING="${BDC_STAGING_PATH:-/home2/zqculgmy/public_html/bachatadancecouncil/BDC_STAGING}"
LOG="${BDC_DEPLOY_LOG:-/home2/zqculgmy/deployment_logs/bdc_staging.log}"
MARKER="${BDC_DEPLOY_MARKER:-/home2/zqculgmy/deployment_logs/bdc_staging_commit}"
LOCK="${BDC_DEPLOY_LOCK:-/tmp/bdc_staging_deploy.lock}"

mkdir -p "$(dirname "$LOG")"
exec >>"$LOG" 2>&1
exec 9>"$LOCK"
/usr/bin/flock -n 9 || exit 0

echo "===== Deployment $(date) ====="
git -C "$DEV" fetch origin develop
REMOTE="$(git -C "$DEV" rev-parse origin/develop)"
DEPLOYED="$(cat "$MARKER" 2>/dev/null || true)"

if [ "$REMOTE" = "$DEPLOYED" ]; then
    echo "No new release."
    exit 0
fi

git -C "$DEV" merge --ff-only origin/develop

mkdir -p "$STAGING/uploads" "$STAGING/public/results" "$STAGING/storage"

rsync -a --delete \
  --exclude=".git/" \
  --exclude="config/config.php" \
  --exclude="config/config.local.php" \
  --exclude="uploads/" \
  --exclude="public/results/" \
  --exclude="storage/" \
  "$DEV/" "$STAGING/"

mkdir -p "$STAGING/uploads" "$STAGING/public/results" "$STAGING/storage"
chmod 755 "$STAGING/uploads" "$STAGING/public/results"
chmod 600 "$STAGING/config/config.php"

php "$STAGING/bin/migrate.php"
HEALTH="$(php "$STAGING/health.php")"
echo "$HEALTH"
echo "$HEALTH" | grep -q '"status"[[:space:]]*:[[:space:]]*"ok"'

printf '%s\n' "$REMOTE" > "$MARKER"
echo "Successfully deployed commit: $REMOTE"
