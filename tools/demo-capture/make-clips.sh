#!/usr/bin/env bash
# uso: make-clips.sh <dark|light>
cd "$(dirname "$0")"
T="$1"
powershell -NoProfile -Command "Get-CimInstance Win32_Process -Filter \"Name='node.exe'\" | Where-Object { \$_.CommandLine -like '*mock-ollama*' } | ForEach-Object { Stop-Process -Id \$_.ProcessId -Force }"
(MOCK_DELAY_MS=1500 node mock-ollama.mjs 11500 > mock.out 2>&1 &)
sleep 1
./restart-app.sh "$T"
for n in paleta mapa; do
  node rec-$n.mjs "$T" || exit 1
  (cd frames-$n && ffmpeg -y -loglevel error -f concat -safe 0 -i list.txt -vf "fps=30,scale=1360:820" -c:v libx264 -crf 14 -pix_fmt yuv420p ../raw-$n-$T.mp4)
  ffprobe -v error -show_entries format=duration -of csv=p=0 raw-$n-$T.mp4
done
