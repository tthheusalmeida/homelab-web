# CLAUDE.md — homelab-web

Site público do HomeLab: landing, login com Google, área de assinaturas e a API PHP que valida o plano do app desktop. O repositório é **público**: nenhuma chave entra aqui. Visão geral em `README.md`; chaves e testes em `docs/chaves-e-testes.md`; segurança em `docs/seguranca.md`; leis (cookies, termos, arrependimento, logs de acesso) em `docs/conformidade.md`.

## Regras
- Front em HTML/CSS/JS puros (sem build). API em PHP 8.3 sem Composer. Textos em português; código e commits em inglês (Conventional Commits).
- Segredos só em `.env.local` (ignorado pelo Git) ou nos secrets do GitHub. Nunca em `site/`, nos testes ou nos logs. `tests/audit.test.php` barra isso.
- Só `api/index.php` é PHP público; o resto (`src/`, `config.php`) fica fora de `public_html`.
- Princípios: uma responsabilidade por classe, dependência por interface injetada, rota e guard novos entram por registro em `src/App.php`.
- Todo código novo vem com teste (`when <situação>, should <resultado>`). Rodar: `docker run --rm -v "$PWD":/app -w /app php:8.3-cli php tests/run.php` (hoje 134 testes).
- Commit e push só quando o usuário pedir.

## Estado (2026-11-25)
- Landing pronta e aprovada (visual).
- API, `/entrar`, `/assinaturas`, workflows e docs feitos; 134 testes passando; refatoração SOLID feita (`UserRepository`, `SubscriptionRepository`, `UsageQuota`, `WebhookVerifier`, `Guard`).
- **Nada foi validado com serviços reais**: Firebase, Stripe e Hostinger. As páginas foram vistas só num Chromium headless (prints), não num navegador de uso real.
- Firebase: projeto criado; usuário estava criando a conta de serviço (papel só Cloud Datastore User), a chave JSON e o `.env.local`.
- Stripe: deixado para depois (usar `rk_test_placeholder`, `whsec_placeholder`, `price_placeholder` no `.env.local` para testar só o login).
- Sem domínio ainda; o deploy (`.github/workflows/deploy.yml`) é manual e não foi usado.

## Conformidade (feat/compliance)
- Banner de cookies, páginas legais, aceite de termos, `/api/refund` (7 dias), `accessLogs` com TTL, fontes locais e identificação da empresa estão feitos (`docs/conformidade.md`). Dados da empresa estão como `*_DEFAULT` em `site/assets/js/empresa.js` (`php tools/check-company.php` lista; o deploy barra). Falta: TTL no Firebase, URL dos Termos no Stripe, método de idade, ferramentas de analytics em `trackers-config.js` e revisão jurídica.
- Persistência: tudo que for gravado vai para o Firestore pelo `DocumentStore`, nunca em arquivo.

## Próximos passos
1. Terminar o Firebase: Firestore em modo produção, regras de `firestore.rules`, Google em Authentication, config web em `site/firebase-config.js`, `.env.local` completo (`SIGNING_SECRET_KEY` via `tools/gen-signing-keys.php`).
2. `tools/dev.sh` e seguir o roteiro de `docs/chaves-e-testes.md` (login, trial, 401/400/404, login vindo do app com `nc -l 4567`); corrigir o que aparecer, principalmente popup do Google e a CSP estrita.
3. Stripe em modo teste: chave restrita, preço mensal do Pro, `stripe listen`; testar checkout, portal, cancelamento e troca de preço.
4. Domínio: criar o ambiente `production` no GitHub, chave SSH de deploy, webhook do Stripe, domínio autorizado no Firebase, `php tools/check-env.php` por SSH; fixar `actions/checkout` por SHA e habilitar o deploy.
5. App desktop (repositório `homelab`, seguir o `CLAUDE.md` dele: fases com ok, branch `feat/auth-trial`, MR): login pelo navegador com callback em `127.0.0.1`, refresh token no `SecretStore`, `<Gate>`/permissões, cota via `/api/usage`, verificação da assinatura Ed25519 com a chave pública embutida.
6. Pendências antigas do app: o updater `check()` ignora versões novas quando o estado é `ready`; yt-dlp e CUDA no Windows ficam na pasta de instalação; vídeo de apresentação da landing (perguntar duração, tema, legendas e resolução antes); stickers no tema claro sem conferência visual.
