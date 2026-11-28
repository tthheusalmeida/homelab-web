# Chaves e testes

## 1. Testes automáticos (não precisam de nenhuma chave nem de internet)

```
docker run --rm -v "$PWD":/app -w /app php:8.3-cli php tests/run.php
```
(com PHP 8.2+ instalado: `php tests/run.php`). Hoje são **92 testes**, todos no formato `when <situação>, should <resultado>`:

| Arquivo | O que cobre |
|---|---|
| `tests/jwt.test.php` | token do Firebase: válido, expirado, `aud`/`iss` errados, `alg: none`, payload adulterado, chave desconhecida, e-mail não verificado, provedor que não é Google, cache dos certificados |
| `tests/domain.test.php` | planos (trial, Pro, preço antigo, preço desconhecido, cancelado, atraso com e sem tolerância), assinatura do webhook, assinatura Ed25519, trial por e-mail, cota diária e virada de dia em São Paulo, limite de requisições, ida e volta dos campos do Firestore |
| `tests/routes.test.php` | rotas de ponta a ponta com banco e rede falsos: 401/403/404/405/429/500, trial no primeiro login, uso e limite, checkout ignorando o corpo, portal, webhook (assinatura inválida, fora de ordem, repetido, usuário desconhecido, chave malformada), preços |
| `tests/audit.test.php` | nenhuma chave no repositório, nada de PHP em `site/`, `api/` só com o ponto de entrada, `.gitignore`, regras do Firestore que negam tudo, páginas de conta sem script/estilo inline e sem `innerHTML` |

## 2. Teste manual local (sem domínio): o que criar e onde

Tudo abaixo é grátis. `localhost` já vale como domínio autorizado no Firebase.

### Chaves necessárias (6)

| Variável (`.env.local`) | De onde vem |
|---|---|
| `FIREBASE_PROJECT_ID` | Firebase Console → criar projeto (plano Spark) → Configurações do projeto |
| `FIREBASE_SERVICE_ACCOUNT_B64` | Console do Google Cloud → IAM → Contas de serviço → criar conta com **só** o papel *Cloud Datastore User* → Chaves → criar JSON → `base64 -w0 arquivo.json` |
| `STRIPE_SECRET_KEY` | Stripe (modo **teste**) → Desenvolvedores → Chaves de API → criar **chave restrita** (Checkout Sessions e Billing Portal: escrita; Prices, Customers, Subscriptions: leitura) |
| `STRIPE_WEBHOOK_SECRET` | aparece ao rodar `stripe listen` (veja abaixo), começa com `whsec_` |
| `STRIPE_PRO_PRICE_ACTIVE` | Stripe → Catálogo de produtos → criar "HomeLab Pro" com preço recorrente mensal → copiar o `price_...` |
| `SIGNING_SECRET_KEY` | `docker run --rm -v "$PWD":/app -w /app php:8.3-cli php tools/gen-signing-keys.php` (a linha SECRET; a PUBLIC vai para o app depois) |

Opcional: `STRIPE_PRO_PRICES_ACCEPTED` (ids antigos separados por vírgula) e `SITE_URL=http://localhost:8080`.

### Onde cada coisa vai
- **Chaves secretas** → arquivo `.env.local` na raiz (copie de `.env.example`). Ele está no `.gitignore`: nunca vai para o GitHub.
- **Config web do Firebase** (apiKey, authDomain, projectId, appId; Console → Configurações do projeto → Seus apps → Web) → `site/firebase-config.js`. Essa é pública por natureza, pode ser commitada.
- **Login Google**: Firebase → Authentication → Método de login → ativar Google.
- **Regras do banco**: Firebase → Firestore Database → criar (modo produção) → colar `firestore.rules`.

### Subir e testar
1. `tools/dev.sh` → site e API em `http://localhost:8080`.
2. Em outro terminal, com a [Stripe CLI](https://docs.stripe.com/stripe-cli) instalada e logada: `stripe listen --forward-to localhost:8080/api/webhook` (copie o `whsec_` para o `.env.local` e rode o `dev.sh` de novo).
3. Roteiro:
   1. Abra `localhost:8080/entrar/` e entre com o Google → vai para o Firestore `users/…` com `trialStartedAt`.
   2. Abra `localhost:8080/assinaturas/` → "Teste grátis, faltam 7 dias" e o preço do Pro vindo do Stripe.
   3. Clique em "Assinar o Pro" → pague com o cartão de teste `4242 4242 4242 4242` (qualquer data futura e CVC).
   4. Volte para `/assinaturas/?status=ok` → "Seu plano Pro está ativo" (o `stripe listen` mostra o webhook com 200).
   5. "Gerenciar assinatura" abre o portal; cancele e veja "Cancelada: vale até …".
   6. Troque `STRIPE_PRO_PRICE_ACTIVE` por outro preço, rode `dev.sh` e confira o valor novo no site e no checkout.
   7. Login vindo do app (simulado): `localhost:8080/entrar/?app=1&port=4567&state=abcdefghijklmnop1234` com `nc -l 4567` ou `python3 -m http.server 4567` escutando → o navegador redireciona para `127.0.0.1:4567/callback?state=…&refresh_token=…`.
   8. Segurança: sem token `curl localhost:8080/api/me` → 401; `curl -X POST localhost:8080/api/webhook -d '{}'` → 400.

### Conformidade (ver `docs/conformidade.md`)
1. **Cookies:** abra `localhost:8080/` em janela anônima → banner sem nada carregado (aba Rede só com arquivos do próprio site, sem `fonts.googleapis.com`, `fonts.gstatic.com` nem `cdnjs`). Role, clique fora, navegue: o banner continua. "Rejeitar tudo" e "Aceitar tudo" têm o mesmo tamanho e cor. Escolha e confira `consents/…` no Firestore. "Preferências de cookies" no rodapé reabre.
2. **Identificação:** rodapé de `/`, `/entrar/`, `/assinaturas/` e página `/contato/` mostram `RAZAO_SOCIAL_DEFAULT`, `CNPJ_DEFAULT`, `ENDERECO_DEFAULT` e `EMAIL_DEFAULT`.
3. **Termos:** em `/entrar/` com um usuário novo, depois do login aparece a caixa desmarcada e o botão "Aceitar e continuar" só habilita ao marcar; confira `termsVersion` e `termsAcceptedAt` em `users/…`.
4. **Checkout:** em `/assinaturas/`, logo abaixo do botão de assinar está o aviso de renovação automática, cancelamento e 7 dias; na tela do Stripe aparece o mesmo texto perto do botão de pagar.
5. **Arrependimento:** depois de assinar com o cartão de teste, `/assinaturas/` mostra "Você pode desistir da compra até …" e o botão (dois cliques). Confirme no Stripe: reembolso integral e assinatura cancelada; a página volta ao plano de teste. Com `startedAt` mais antigo que 7 dias no Firestore, o botão some e `POST /api/refund` dá 403.
6. **Logs:** depois de usar o site, veja `accessLogs/…` no Firestore com `ip`, `at`, `path`, `status` e `expireAt` ≈ 186 dias à frente (e a política de TTL ligada no console).
7. **Dados da empresa:** `php tools/check-company.php` lista os `*_DEFAULT` pendentes.

## 3. Deploy (depois de ter domínio)

No GitHub: Settings → Environments → `production` (restrinja à branch `main` e exija aprovação).

| Tipo | Nome | Valor |
|---|---|---|
| Secret | `SSH_PRIVATE_KEY` | chave privada **só de deploy** (`ssh-keygen -t ed25519`; a pública vai no hPanel → SSH) |
| Secret | `SSH_KNOWN_HOSTS` | `ssh-keyscan -p 65002 <host>` (confira a impressão digital com o hPanel) |
| Secret | `FIREBASE_SERVICE_ACCOUNT_B64`, `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`, `SIGNING_SECRET_KEY` | os mesmos do local, mas **de produção** (Stripe em modo live) |
| Variable | `SSH_HOST`, `SSH_USER`, `SSH_PORT` (65002), `REMOTE_ROOT` | dados da Hostinger |
| Variable | `SITE_URL`, `FIREBASE_PROJECT_ID`, `STRIPE_PRO_PRICE_ACTIVE`, `STRIPE_PRO_PRICES_ACCEPTED` | públicas; mudar o preço = editar a variável e rodar o workflow de novo |

Também: criar o webhook no Stripe apontando para `https://SEU-DOMINIO/api/webhook` (eventos `checkout.session.completed` e `customer.subscription.created|updated|deleted`), adicionar o domínio em Firebase → Authentication → Domínios autorizados, e rodar `php tools/check-env.php` por SSH.
