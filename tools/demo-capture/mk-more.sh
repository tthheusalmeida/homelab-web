#!/usr/bin/env bash
# uso: mk-more.sh <tema> <clipe...>
cd "$(dirname "$0")"
T="$1"; shift
for n in "$@"; do
  node rec-more.mjs "$n" "$T" || { echo "FALHOU $n"; continue; }
  (cd frames-$n && ffmpeg -y -loglevel error -f concat -safe 0 -i list.txt -vf "fps=30,scale=1360:820" -c:v libx264 -crf 14 -pix_fmt yuv420p ../raw-$n-$T.mp4)
  echo "$n $(ffprobe -v error -show_entries format=duration -of csv=p=0 raw-$n-$T.mp4)s"
done
