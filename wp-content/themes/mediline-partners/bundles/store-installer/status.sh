#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"; cd "$ROOT"
if docker compose version >/dev/null 2>&1; then C=(docker compose); else C=(docker-compose); fi
"${C[@]}" ps
if [ -f .mediline-installed ]; then echo; cat .mediline-installed; fi
"${C[@]}" run --rm cli mediline-store status --path=/var/www/html || true
