#!/usr/bin/env bash
# Converte uma gravação de tela nos clipes do notebook da landing (WebM + MP4, sem áudio, 1040x628).
# Uso: tools/encode-clips.sh <gravacao> <nome> <dark|light>
#   nome = o data-video do passo em site/index.html (nova-tarefa, mapa-mental, paleta)
# Saída: site/assets/app/video/<nome>-<tema>.webm e .mp4 (precisa de ffmpeg no PATH).
set -euo pipefail
[ $# -eq 3 ] || { echo "Uso: $0 <gravacao> <nome> <dark|light>"; exit 1; }
in="$1"; name="$2"; theme="$3"
case "$theme" in dark|light) ;; *) echo "O tema deve ser dark ou light"; exit 1;; esac
out="$(dirname "$0")/../site/assets/app/video"
mkdir -p "$out"
vf="fps=30,scale=1040:628:flags=lanczos"
ffmpeg -y -loglevel error -i "$in" -an -vf "$vf" -c:v libvpx-vp9 -b:v 0 -crf 36 -row-mt 1 "$out/$name-$theme.webm"
ffmpeg -y -loglevel error -i "$in" -an -vf "$vf" -c:v libx264 -preset slow -crf 26 -pix_fmt yuv420p -movflags +faststart "$out/$name-$theme.mp4"
ls -l "$out/$name-$theme".*
