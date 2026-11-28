# API (`/api`, PHP)

Mesmo domínio do site, sem CORS. Rotas autenticadas exigem `Authorization: Bearer <ID token do Firebase>`.

| Rota | Auth | O que faz |
|---|---|---|
| `GET /api/me` | sim | Cria o registro no primeiro acesso (inicia o trial) e devolve plano, datas, permissões, cota do dia, `terms`, `age`, `withdrawalUntil` e `signed` (Ed25519, com `termsOk` e `ageOk`) |
| `GET /api/plans` | não | Preço do Pro lido do Stripe (cache de 1 h) e dias de trial |
| `POST /api/usage` `{feature}` | sim | Consome 1 da cota diária (`summary`, `infographic`, `mindmap`, `videoTranscription`, `videoSummary`). 429 se acabou, 403 se o trial acabou ou `terms_required`/`age_required` |
| `POST /api/checkout` | sim | Cria a sessão de pagamento (com o aviso de renovação e arrependimento junto do botão); devolve `{url}` do Stripe. 409 se já é Pro, 403 `terms_required`/`age_required` |
| `POST /api/portal` | sim | Abre o portal do Stripe (trocar cartão, faturas, cancelar). 404 sem assinatura |
| `POST /api/refund` | sim | Desistência em até 7 dias (CDC, art. 49): reembolso integral + cancelamento imediato. 403 `withdrawal_period_over`, 409 `already_withdrawn`, 404 sem assinatura |
| `POST /api/terms/accept` `{version}` | sim | Registra o aceite dos Termos vigentes (409 se a versão for antiga) |
| `POST /api/consent` `{version,analytics,marketing}` | não | Prova anônima da escolha do banner de cookies (20/min por IP) |
| `POST /api/webhook` | assinatura do Stripe | Atualiza a assinatura no Firestore |

## Planos
- **Trial:** 7 dias a partir do primeiro login; precisa de internet todo dia (resposta assinada vale 1 dia); 1 uso por dia de cada recurso.
- **Pro:** sem limite diário; resposta assinada vale até 30 dias offline; mantém 3 dias de tolerância se o pagamento atrasar.
- **Expirado:** sem permissões de gerar (o app continua abrindo o que já existe, localmente).

## Dados no Firestore (só o servidor lê e grava)
- `users/{uid do Firebase}`: e-mail, `trialStartedAt`, `stripeCustomerId`, `subscription` (`status`, `priceId`, `currentPeriodEnd`, `cancelAtPeriodEnd`, `startedAt`, `eventCreated`), `termsVersion`, `termsAcceptedAt`, `ageVerified` (`method`, `at`) e `withdrawal` (`subscriptionId`, `at`).
- `usage/{chave}_{AAAAMMDD}`: contadores do dia (fuso de São Paulo).
- `stripeCustomers/{cus_...}`: aponta para o usuário.
- `accessLogs/{id}`: registro de acesso (IP, data e hora, rota, status, uid) com `expireAt` (TTL do Firestore, 186 dias). Ver `docs/conformidade.md`.
- `consents/{id}`: escolha do banner de cookies (sem dado pessoal).

## Preços (sem mexer em código)
`STRIPE_PRO_PRICE_ACTIVE` = preço dos **novos** checkouts. `STRIPE_PRO_PRICES_ACCEPTED` = todos os ids que valem como Pro (inclua os antigos ao trocar de preço, senão quem já assina perde o Pro). O site e o checkout leem o valor do Stripe por esse id.

## Para a fase do app (ainda não feita)
O app guarda o `refresh_token` (recebido em `127.0.0.1` pela página `/entrar`), troca por ID tokens em `securetoken.googleapis.com` com a chave web do Firebase, chama `/api/me` e confere `signed` com a chave **pública** Ed25519 embutida.
