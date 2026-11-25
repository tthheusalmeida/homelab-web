#!/usr/bin/env bash
# Sobe o site + API localmente em http://localhost:8080 (precisa de Docker). Segredos vêm de .env.local (fora do Git).
set -euo pipefail
cd "$(dirname "$0")/.."
[ -f .env.local ] || { echo "Crie o .env.local (veja docs/chaves-e-testes.md)"; exit 1; }
rm -rf private-dev && mkdir -p private-dev && ln -s ../src private-dev/src
docker run --rm -v "$PWD":/app -w /app php:8.3-cli php tools/build-config.php private-dev/config.php .env.local
echo "Site em http://localhost:8080  (Ctrl+C para parar)"
docker run --rm -p 8080:8080 -v "$PWD":/app -w /app -e HOMELAB_PRIVATE=/app/private-dev \
  php:8.3-cli php -S 0.0.0.0:8080 -t site tools/dev-router.php
