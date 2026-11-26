#!/usr/bin/env bash
# Converte uma gravação de tela nos clipes da landing.
# Uso: tools/encode-clips.sh <gravacao> <nome> <dark|light>
#   nome = id do clipe (mapa-mental, paleta, livros, buscar, transcricao, visual, consumo, backup, nova-tarefa)
# Saída em site/assets/app/video/ (sem áudio):
#   <nome>-<tema>.webm|.mp4        prévia leve, 1040x628 (hero, cartões, notebook 3D)
#   <nome>-<tema>-hd.webm|.mp4     versão do modal, 1360x820 (a gravação deve ter essa resolução)
#   <nome>-<tema>.jpg              poster, quadro em 40% do clipe
# Precisa de ffmpeg e ffprobe no PATH.
set -euo pipefail
[ $# -eq 3 ] || { echo "Uso: $0 <gravacao> <nome> <dark|light>"; exit 1; }
in="$1"; name="$2"; theme="$3"
case "$theme" in dark|light) ;; *) echo "O tema deve ser dark ou light"; exit 1;; esac
out="$(dirname "$0")/../site/assets/app/video"
mkdir -p "$out"
speed="${SPEED:-1}" # SPEED=1.3 acelera a gravação (ex.: tirar a espera)
enc() { # sufixo largura altura crf-webm crf-mp4
  local vf="setpts=PTS/$speed,fps=30,scale=$2:$3:flags=lanczos"
  ffmpeg -y -loglevel error -i "$in" -an -vf "$vf" -c:v libvpx-vp9 -b:v 0 -crf "$4" -row-mt 1 "$out/$name-$theme$1.webm"
  ffmpeg -y -loglevel error -i "$in" -an -vf "$vf" -c:v libx264 -preset slow -crf "$5" -pix_fmt yuv420p -movflags +faststart "$out/$name-$theme$1.mp4"
}
enc "" 1040 628 36 26
enc "-hd" 1360 820 33 23
dur=$(ffprobe -v error -show_entries format=duration -of csv=p=0 "$in")
at=$(awk -v d="$dur" 'BEGIN { printf "%.2f", d * 0.4 }')
ffmpeg -y -loglevel error -ss "$at" -i "$in" -frames:v 1 -vf "scale=1040:628:flags=lanczos" -q:v 3 "$out/$name-$theme.jpg"
ls -l "$out/$name-$theme".* "$out/$name-$theme"-hd.*
