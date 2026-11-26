#!/usr/bin/env bash
# uso: restart-app.sh <dark|light>
cd "$(dirname "$0")"
D="$PWD"
powershell -NoProfile -Command "Get-Process electron -ErrorAction SilentlyContinue | Where-Object { \$_.Path -like '*homelab*desktop*' } | Stop-Process -Force" 
sleep 2
node seed.mjs "$D/root" "${1:-dark}" 11500 >/dev/null
rm -rf "$D/ud" && mkdir -p "$D/ud"
cd D:/homelab/desktop
HOMELAB_DATA_ROOT_DIR="$D/root" HOMELAB_USER_DATA_DIR="$D/ud" nohup npx electron . --remote-debugging-port=9222 > "$D/electron.out" 2>&1 &
sleep 12
curl -s http://127.0.0.1:9222/json | grep -c '"page"'
