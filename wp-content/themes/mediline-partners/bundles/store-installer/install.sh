#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; NC='\033[0m'
info(){ printf "%b\n" "${GREEN}✓${NC} $*"; }
warn(){ printf "%b\n" "${YELLOW}!${NC} $*"; }
die(){ printf "%b\n" "${RED}✕${NC} $*" >&2; exit 1; }

command -v docker >/dev/null 2>&1 || die "Docker is required. Install Docker Engine/Desktop first."
docker info >/dev/null 2>&1 || die "Docker daemon is not running or your user cannot access it."
if docker compose version >/dev/null 2>&1; then COMPOSE=(docker compose); elif command -v docker-compose >/dev/null 2>&1; then COMPOSE=(docker-compose); else die "Docker Compose is required."; fi
[ -f mediline-installation.json ] || [ -f .mediline-runtime/installation.json ] || [ -f .mediline-installed ] || die "mediline-installation.json is missing. Download a fresh package from Mediline Store Builder."
[ -f packages/storefront-theme.zip ] || die "packages/storefront-theme.zip is missing."
[ -f packages/mediline-store-core.zip ] || die "packages/mediline-store-core.zip is missing."

if [ -f .mediline-installed ]; then
  info "This store is already installed."
  cat .mediline-installed
  exit 0
fi

mkdir -p .mediline-runtime
chmod 700 .mediline-runtime

if [ ! -f .mediline-runtime/installation.json ] || [ ! -f .env ]; then
  if [ -f .mediline-runtime/installation.json ]; then
    warn "Recovering local environment after an interrupted provisioning write."
  else
    info "Claiming one-time Mediline provisioning configuration…"
  fi
  docker run --rm -v "$ROOT:/work" python:3.13-alpine python /work/installer/bootstrap.py
else
  warn "Reusing previously claimed provisioning data after an interrupted installation."
fi
[ -f .env ] || die "Installer could not create .env."
set -a; source ./.env; set +a

runtime(){ docker run --rm -v "$ROOT:/work:ro" python:3.13-alpine python /work/installer/runtime.py "$1"; }
STORE_NAME="$(runtime store_name)"
ADMIN_EMAIL="$(runtime admin_email)"
ADMIN_USER="$(runtime admin_username)"
ADMIN_PASSWORD="$(runtime admin_password)"
PRIMARY_LANGUAGE="$(runtime primary_language)"
LANGUAGES="$(runtime languages)"
CURRENCY="$(runtime currency)"
CATALOG_API_URL="$(runtime catalog.api_url)"
STORE_ID="$(runtime catalog.store_id)"
STORE_SECRET="$(runtime catalog.store_secret)"
PAP_TRACKING_SCRIPT_URL="$(runtime catalog.pap_tracking_script_url 2>/dev/null || true)"
PAP_ACCOUNT_ID="$(runtime catalog.pap_account_id 2>/dev/null || true)"
INSTALLATION_ID="$(runtime installation_id)"

info "Pulling WordPress, database, proxy and WP-CLI images…"
"${COMPOSE[@]}" pull db wordpress cli caddy
info "Starting database and WordPress…"
"${COMPOSE[@]}" up -d db wordpress

printf "Waiting for WordPress files"
for _ in $(seq 1 90); do
  if "${COMPOSE[@]}" exec -T wordpress test -f /var/www/html/wp-includes/version.php >/dev/null 2>&1; then echo; break; fi
  printf "."; sleep 2
done
"${COMPOSE[@]}" exec -T wordpress test -f /var/www/html/wp-includes/version.php >/dev/null 2>&1 || die "WordPress container did not become ready."

wpcli(){ "${COMPOSE[@]}" run --rm cli "$@"; }
if ! wpcli core is-installed --path=/var/www/html >/dev/null 2>&1; then
  info "Installing WordPress…"
  wpcli core install --path=/var/www/html --url="$MEDILINE_SITE_URL" --title="$STORE_NAME" --admin_user="$ADMIN_USER" --admin_password="$ADMIN_PASSWORD" --admin_email="$ADMIN_EMAIL" --skip-email
else
  warn "WordPress is already installed in the Docker volume; continuing without recreating it."
fi

info "Installing storefront theme…"
wpcli theme install /mediline-packages/storefront-theme.zip --force --activate --path=/var/www/html
STOREFRONT_THEME="$(wpcli option get stylesheet --path=/var/www/html)"
[ -n "$STOREFRONT_THEME" ] || die "Could not determine the activated storefront theme."
info "Installing Mediline Store Core…"
wpcli plugin install /mediline-packages/mediline-store-core.zip --force --activate --path=/var/www/html

case "$PRIMARY_LANGUAGE" in
  fr) WP_LOCALE=fr_FR;; de) WP_LOCALE=de_DE;; sp|es) WP_LOCALE=es_ES;; it) WP_LOCALE=it_IT;; *) WP_LOCALE=en_US;;
esac
if [ "$WP_LOCALE" != "en_US" ]; then
  wpcli language core install "$WP_LOCALE" --activate --path=/var/www/html >/dev/null 2>&1 || warn "Could not download WordPress core language $WP_LOCALE; storefront content languages still work."
fi

info "Configuring Mediline catalog connection…"
wpcli mediline-store configure --path=/var/www/html --api-url="$CATALOG_API_URL" --store-id="$STORE_ID" --store-secret="$STORE_SECRET" --primary-language="$PRIMARY_LANGUAGE" --languages="$LANGUAGES" --currency="$CURRENCY" --pap-tracking-script-url="$PAP_TRACKING_SCRIPT_URL" --pap-account-id="${PAP_ACCOUNT_ID:-default1}"
wpcli option update permalink_structure '/%postname%/' --path=/var/www/html >/dev/null
wpcli rewrite flush --hard --path=/var/www/html >/dev/null

info "Synchronizing the full catalog…"
wpcli mediline-store sync --full --path=/var/www/html
wpcli mediline-store heartbeat --status=online --path=/var/www/html >/dev/null 2>&1 || true
wpcli theme is-active "$STOREFRONT_THEME" --path=/var/www/html >/dev/null || die "Storefront theme $STOREFRONT_THEME is not active."
wpcli plugin is-active mediline-store-core --path=/var/www/html >/dev/null || die "Mediline Store Core is not active."

info "Starting HTTPS reverse proxy…"
"${COMPOSE[@]}" up -d caddy

printf "Waiting for storefront readiness"
STATUS_JSON=""
CADDY_ROUTE_CODE=""
READY=false
for _ in $(seq 1 30); do
  STATUS_JSON="$("${COMPOSE[@]}" exec -T caddy wget -qO- --header="Host: ${MEDILINE_DOMAIN}" http://wordpress/wp-json/mediline-store/v1/status 2>/dev/null || true)"
  CADDY_ROUTE_CODE="$(curl --noproxy '*' -sS --max-time 5 --max-redirs 0 --resolve "${MEDILINE_DOMAIN}:80:127.0.0.1" -o .mediline-runtime/caddy-probe.txt -w '%{http_code}' "http://${MEDILINE_DOMAIN}/wp-json/mediline-store/v1/status" 2>/dev/null || true)"
  CADDY_ROUTE_OK=false
  if [ "$CADDY_ROUTE_CODE" = "200" ] && grep -Eq '"configured"[[:space:]]*:[[:space:]]*true' .mediline-runtime/caddy-probe.txt 2>/dev/null; then
    CADDY_ROUTE_OK=true
  elif [[ "$MEDILINE_SITE_URL" == https://* ]] && [[ "$CADDY_ROUTE_CODE" =~ ^30[1278]$ ]]; then
    CADDY_ROUTE_OK=true
  fi
  if printf '%s' "$STATUS_JSON" | grep -Eq '"configured"[[:space:]]*:[[:space:]]*true' && [ "$CADDY_ROUTE_OK" = "true" ] && "${COMPOSE[@]}" exec -T caddy caddy validate --config /etc/caddy/Caddyfile >/dev/null 2>&1; then
    READY=true; echo; break
  fi
  printf "."; sleep 2
done
[ "$READY" = "true" ] || die "Storefront or Caddy readiness check failed; installation marker was not written."
PRODUCT_COUNT="$(printf '%s' "$STATUS_JSON" | sed -n 's/.*"products"[[:space:]]*:[[:space:]]*\([0-9][0-9]*\).*/\1/p')"
info "Storefront, active components and Caddy are responding (${PRODUCT_COUNT:-0} catalog products)."

cat > .mediline-installed <<EOF
MEDILINE STORE INSTALLED
installation_id=$INSTALLATION_ID
store_id=$STORE_ID
site=$MEDILINE_SITE_URL
installed_at=$(date -u +%Y-%m-%dT%H:%M:%SZ)
EOF
chmod 600 .mediline-installed
rm -rf .mediline-runtime mediline-installation.json

printf "\n%b\n" "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
printf "%b\n" "${GREEN} MEDILINE STORE IS READY${NC}"
printf "%b\n" "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
printf "Store:  %s\n" "$MEDILINE_SITE_URL"
printf "Admin:  %s/wp-admin\n" "$MEDILINE_SITE_URL"
printf "Store ID: %s\n" "$STORE_ID"
printf "Languages: %s\n" "$LANGUAGES"
printf "\nDNS must point ${MEDILINE_DOMAIN%%:*} to this server for automatic HTTPS.\n"
