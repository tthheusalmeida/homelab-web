# API (`/api`, PHP)

Mesmo domínio do site, sem CORS. Rotas autenticadas exigem `Authorization: Bearer <ID token do Firebase>`.

| Rota | Auth | O que faz |
|---|---|---|
| `GET /api/me` | sim | Cria o registro no primeiro acesso (inicia o trial) e devolve plano, datas, permissões, cota do dia e `signed` (Ed25519) |
| `GET /api/plans` | não | Preço do Pro lido do Stripe (cache de 1 h) e dias de trial |
| `POST /api/usage` `{feature}` | sim | Consome 1 da cota diária (`summary`, `infographic`, `mindmap`, `videoTranscription`, `videoSummary`). 429 se acabou, 403 se o trial acabou |
| `POST /api/checkout` | sim | Cria a sessão de pagamento; devolve `{url}` do Stripe. 409 se já é Pro |
| `POST /api/portal` | sim | Abre o portal do Stripe (trocar cartão, faturas, cancelar). 404 sem assinatura |
| `POST /api/webhook` | assinatura do Stripe | Atualiza a assinatura no Firestore |

## Planos
- **Trial:** 7 dias a partir do primeiro login; precisa de internet todo dia (resposta assinada vale 1 dia); 1 uso por dia de cada recurso.
- **Pro:** sem limite diário; resposta assinada vale até 30 dias offline; mantém 3 dias de tolerância se o pagamento atrasar.
- **Expirado:** sem permissões de gerar (o app continua abrindo o que já existe, localmente).

## Dados no Firestore (só o servidor lê e grava)
- `users/{uid do Firebase}`: e-mail, `trialStartedAt`, `stripeCustomerId`, `subscription` (`status`, `priceId`, `currentPeriodEnd`, `cancelAtPeriodEnd`, `eventCreated`).
- `usage/{chave}_{AAAAMMDD}`: contadores do dia (fuso de São Paulo).
- `stripeCustomers/{cus_...}`: aponta para o usuário.

## Preços (sem mexer em código)
`STRIPE_PRO_PRICE_ACTIVE` = preço dos **novos** checkouts. `STRIPE_PRO_PRICES_ACCEPTED` = todos os ids que valem como Pro (inclua os antigos ao trocar de preço, senão quem já assina perde o Pro). O site e o checkout leem o valor do Stripe por esse id.

## Para a fase do app (ainda não feita)
O app guarda o `refresh_token` (recebido em `127.0.0.1` pela página `/entrar`), troca por ID tokens em `securetoken.googleapis.com` com a chave web do Firebase, chama `/api/me` e confere `signed` com a chave **pública** Ed25519 embutida.
