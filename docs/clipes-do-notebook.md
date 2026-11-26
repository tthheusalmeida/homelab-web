# Clipes de vídeo no notebook da landing

O notebook 3D mostra uma captura do app por recurso. Cada recurso pode ganhar um **clipe curto** que toca na tela do notebook enquanto o passo está ativo. Sem clipe, nada muda: continua a captura parada.

## Como funciona
- Um passo tem clipe se o `<li>` dele em `.story-steps` (`site/index.html`) tiver `data-video="nome"`. Hoje: `nova-tarefa` (passo 2), `mapa-mental` (passo 3) e `paleta` (passo 6).
- Os arquivos ficam em `site/assets/app/video/<nome>-<dark|light>.webm` e `.mp4`. O tema segue o botão claro/escuro da página, então cada clipe é gravado nos dois temas.
- O vídeo só é pedido quando o passo aparece e a seção está na tela; pausa ao sair e recomeça do início ao voltar.
- A captura parada aparece enquanto o vídeo carrega, se o arquivo não existir, com `prefers-reduced-motion` ou com economia de dados.
- Só o notebook/tablet 3D usa o clipe. Sem WebGL, a versão em CSS segue com as capturas.

## Gravar
Resolução da janela do app: **1360×820** (mesma proporção das capturas). OBS, 60 fps, uma gravação por clipe e por tema. Sem cortes, 6 a 10 segundos, que repetem em loop: comece e termine no mesmo estado da tela (ou termine num estado parado que possa voltar ao início sem pulo).

| Arquivo (`nome`) | O que mostrar |
| --- | --- |
| `nova-tarefa` | Colar um link de vídeo, escolher o tipo de resumo, iniciar e ver as etapas (baixar, transcrever, dividir, resumir) avançando. Acelere a espera na edição. |
| `mapa-mental` | Abrir o mapa em colmeia, expandir um ramo, mover um nó e passar o mouse no botão de baixar PNG. |
| `paleta` | Ctrl K, digitar parte de um título, setas para escolher e Enter abrindo a página. |

Dicas: use um resumo real e bonito (não "teste"), esconda dados pessoais, mouse devagar e sem tremer, tela limpa (sem notificações).

## Converter
```bash
tools/encode-clips.sh gravacao-nova-tarefa-dark.mp4 nova-tarefa dark
tools/encode-clips.sh gravacao-nova-tarefa-light.mp4 nova-tarefa light
```
Gera `.webm` (VP9) e `.mp4` (H.264), sem áudio, 1040×628. Alvo: **até 2 MB por arquivo**; se passar, encurte o clipe ou suba o `-crf` no script.

## Adicionar outro passo
Coloque `data-video="nome"` no `<li>` do passo e grave `nome-dark` e `nome-light`. O código não muda.

## Estado dos clipes
- Gravados (tools/demo-capture): `paleta` e `mapa-mental`, nos dois temas.
- Faltando: `nova-tarefa`. Precisa de yt-dlp e Whisper instalados (vídeo) ou de a leitura de PDF funcionar no app usado para gravar. Quando existir, é só gerar com `tools/encode-clips.sh`; o passo 2 já tem o `data-video`.
- O hero (`site/hero-video.js`) toca o clipe `mapa-mental` numa janela do app, só em telas largas (a partir de 961px).
