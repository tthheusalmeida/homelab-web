# Sobe o site + API localmente em http://localhost:8080 (precisa de Docker). Equivalente do tools/dev.sh para Windows.
# Segredos vêm de .env.local (fora do Git).
$ErrorActionPreference = 'Stop'
Set-Location (Join-Path $PSScriptRoot '..')

if (-not (Test-Path .env.local)) {
    Write-Host 'Crie o .env.local (veja docs/chaves-e-testes.md)'
    exit 1
}

if (Test-Path private-dev) { Remove-Item -Recurse -Force private-dev }
New-Item -ItemType Directory private-dev | Out-Null

$root = (Get-Location).Path
# src é montado dentro de private-dev (no lugar do symlink do dev.sh).
$srcMount = "${root}\src:/app/private-dev/src:ro"

docker run --rm -v "${root}:/app" -v $srcMount -w /app php:8.3-cli php tools/build-config.php private-dev/config.php .env.local
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host 'Site em http://localhost:8080  (Ctrl+C para parar)'
docker run --rm -p 8080:8080 -v "${root}:/app" -v $srcMount -w /app -e HOMELAB_PRIVATE=/app/private-dev `
    php:8.3-cli php -S 0.0.0.0:8080 -t site tools/dev-router.php
