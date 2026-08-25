#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"; cd "$ROOT"
if docker compose version >/dev/null 2>&1; then C=(docker compose); else C=(docker-compose); fi
[ -f .mediline-installed ] || { echo "Store is not installed yet." >&2; exit 1; }
"${C[@]}" pull db wordpress cli caddy
"${C[@]}" up -d db wordpress caddy
wpcli(){ "${C[@]}" run --rm cli "$@"; }
wpcli plugin install /mediline-packages/mediline-store-core.zip --force --activate --path=/var/www/html
wpcli theme install /mediline-packages/storefront-theme.zip --force --activate --path=/var/www/html
wpcli mediline-store sync --path=/var/www/html
wpcli rewrite flush --hard --path=/var/www/html >/dev/null
echo "Mediline store packages and catalog are updated."
