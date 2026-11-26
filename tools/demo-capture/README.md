# Gravação dos clipes da landing

Roda o app desktop real com **dados de exemplo** e uma **IA falsa** (imita o Ollama), grava a tela por CDP e gera os vídeos do notebook. Nada toca nos seus dados: usa `HOMELAB_DATA_ROOT_DIR` e `HOMELAB_USER_DATA_DIR` temporários.

- `seed.mjs`: cria a biblioteca de exemplo (9 páginas) e as configurações (tema, IA padrão = Ollama falso).
- `mock-ollama.mjs` + `answers.json`: respostas prontas (ex.: o mapa mental de "Princípios da Administração Pública"). `MOCK_DELAY_MS` simula a espera da IA.
- `lib.mjs`: conexão CDP (Playwright do repositório `desktop`), cursor desenhado na página e gravador de quadros.
- `rec-paleta.mjs`, `rec-mapa.mjs`: os roteiros. `make-clips.sh <dark|light>` roda os dois e deixa `raw-<nome>-<tema>.mp4`.
- Depois: `tools/encode-clips.sh raw-paleta-dark.mp4 paleta dark` (WebM + MP4 em `site/assets/app/video/`). Veja `docs/clipes-do-notebook.md` (na branch do player).

Requisitos: o app desktop compilado (`npm run build` em `desktop`), ffmpeg no PATH, Edge/Chromium do Playwright. Defina `HOMELAB_DESKTOP_DIR` se o desktop não estiver em `D:/homelab/desktop`. Rode a partir desta pasta; feche o app depois (ele tem instância única).

Ainda não gravado: `nova-tarefa`. O fluxo de vídeo precisa de yt-dlp e Whisper instalados, e a leitura de PDF travou neste ambiente.
