#!/bin/bash
# Builds a deployable zip of Sahl for App Service: production vendor/, built assets, no local files.
# Usage: infra/app-service/package.sh            -> writes build/sahl.zip
#        az webapp deploy -g rg-sahl-dev-sdc -n <app> --type zip --src-path build/sahl.zip
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
STAGE="$ROOT/build/stage"
ZIP="$ROOT/build/sahl.zip"

cd "$ROOT"
npm run build

rm -rf "$STAGE" "$ZIP"
mkdir -p "$STAGE"

# Copy the working tree, minus anything local, generated or secret. A leading / anchors a pattern to the repo root.
rsync -a "$ROOT/" "$STAGE/" \
    --exclude '/.git/' --exclude '/node_modules/' --exclude '/vendor/' --exclude '/build/' \
    --exclude '/.env' --exclude '/.env.*' --exclude '/public/hot' --exclude '/public/storage' \
    --exclude '/public/fonts-manifest.dev.json' --exclude '/storage/' --exclude '/bootstrap/cache/*.php' \
    --exclude '/tests/' --exclude '/database/*.sqlite' --exclude '/.phpunit.*' \
    --exclude '/.idea/' --exclude '/.vscode/' --exclude '/.claude/' --exclude '.DS_Store'

# Empty storage skeleton (Laravel writes logs, sessions, caches and mPDF temp files here).
mkdir -p "$STAGE"/storage/app/public "$STAGE"/storage/framework/{cache/data,sessions,views} "$STAGE"/storage/logs
chmod +x "$STAGE/infra/app-service/startup.sh"

# Production dependencies only. package:discover rebuilds bootstrap/cache without dev packages.
composer install --working-dir="$STAGE" --no-dev --optimize-autoloader --no-interaction --no-progress

(cd "$STAGE" && zip -qr "$ZIP" . -x '*.DS_Store')
echo "Built $ZIP ($(du -h "$ZIP" | cut -f1))"
