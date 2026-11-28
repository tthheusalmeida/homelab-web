# Conformidade: cookies, idade, identificação, arrependimento, logs, fontes e checkout

Tudo o que a lei brasileira pede do site e da API do HomeLab, o que já está feito e o que depende de você. O lado do app desktop está em `homelab/desktop/docs/conta.md` (seção "Conformidade"). **Os textos das páginas legais são um ponto de partida: peça revisão a um advogado antes de publicar.**

Regra de dados: tudo que for persistido vai para o **Firebase (Firestore)**, pelo `DocumentStore`. Nenhum dado novo é gravado em arquivo.

## O que cada item virou

| # | Exigência | Onde está | Estado |
|---|---|---|---|
| 1 | Banner de cookies, sem rastreador antes do aceite | `site/assets/js/consent.js`, `consent.css`, `trackers-config.js`, rota `POST /api/consent` | feito |
| 2 | Idade: autodeclaração não vale | `Compliance`, campo `ageVerified`, flag `compliance.requireAgeVerification` | pronto, **desligado** até existir um método de verificação |
| 3 | Identificação do fornecedor | `site/assets/js/empresa.js` (única fonte), rodapé de todas as páginas, `/contato/` | feito, com `*_DEFAULT` |
| 4 | 7 dias de arrependimento | aviso no checkout, `POST /api/refund`, botão em `/assinaturas/`, `/arrependimento/` | feito |
| 5 | Logs de acesso por 6 meses | `StoreAccessLog` (Firestore, coleção `accessLogs`, TTL em `expireAt`) | feito; **falta ligar o TTL no Firebase** |
| 6 | Fontes (e three.js) hospedados por nós | `site/assets/fonts`, `site/assets/vendor/three.min.js`, `assets/css/fonts.css` | feito |
| 7 | Checkout e uploads | termos de renovação junto do botão e no Stripe (`custom_text`); aviso de direitos autorais ao importar é do **app** | site feito; app no plano dele |

Além disso: páginas `/termos/`, `/privacidade/`, `/arrependimento/`, `/contato/`, aceite explícito dos termos em `/entrar/` (`POST /api/terms/accept`) e `termsOk`/`ageOk` na resposta assinada do `/api/me` para o app.

## O que falta fazer (depende de você)

1. **Dados da empresa.** Abra `site/assets/js/empresa.js` e troque `RAZAO_SOCIAL_DEFAULT`, `CNPJ_DEFAULT`, `ENDERECO_DEFAULT` e `EMAIL_DEFAULT`. É o único lugar. `php tools/check-company.php` lista o que ainda está como `*_DEFAULT` (o workflow de deploy roda isso e **para** enquanto houver algum). Lembre: o repositório é público, então esses dados ficam visíveis (já ficam no site de qualquer forma). Se for usar CPF e endereço residencial, avalie antes.
2. **TTL dos logs no Firebase.** Sem isso os logs nunca são apagados. No console: Firestore → TTL → criar política na coleção `accessLogs`, campo `expireAt`. Ou `gcloud firestore fields ttls update expireAt --collection-group=accessLogs --enable-ttl`. A conta de serviço (só `Cloud Datastore User`) não lê nem apaga esses logs por rota nenhuma: são só gravados.
3. **Stripe: URL dos Termos.** No painel (Settings → Public details) cadastre `https://<domínio>/termos/`. Depois defina a variável `STRIPE_REQUIRE_TERMS_CONSENT=true` (e rode o deploy) para o Stripe mostrar o checkbox dos termos. Sem a URL cadastrada, o checkout falha, por isso começa desligado. O texto de renovação e arrependimento (`custom_text`) já vai junto do botão de pagar.
4. **Idade (ECA Digital).** Decidir com o jurídico se e como o HomeLab precisa verificar idade e qual método vale. O servidor já espera um registro `ageVerified: {method, at}` no usuário (`UserRepository::markAgeVerified`, sem data de nascimento) e, com `REQUIRE_AGE_VERIFICATION=true`, barra `/usage` e `/checkout` com `age_required` e devolve `ageOk=false` ao app. **Não ligue a flag antes de existir o método**, senão ninguém consegue usar.
5. **Analytics e pixel.** Escolha as ferramentas e cadastre cada uma em `site/assets/js/trackers-config.js` (há exemplos no arquivo). Elas só são injetadas depois do aceite da categoria. O teste `tests/site.test.php` barra qualquer snippet de analytics fora desse arquivo. As páginas de conta e legais têm CSP estrita: rastreadores só rodam na landing (se precisar em outra página, libere o domínio na CSP dela).
6. **Logs de páginas estáticas.** O registro de acesso do Marco Civil cobre a API (login, plano, uso, pagamento). O Hostinger também guarda o log do Apache das páginas: confira no hPanel por quanto tempo e se é acessível só a você. Confira também se, atrás do proxy da hospedagem, `REMOTE_ADDR` traz o IP real do visitante (senão o log guardaria o IP do proxy).
7. **Revisão jurídica** dos textos de `site/termos`, `site/privacidade`, `site/arrependimento` e do aviso de direitos autorais do app. Ao mudar os Termos ou a Política, suba `Compliance::TERMS_VERSION` **e** a linha "Versão …" das duas páginas (o teste confere que estão iguais): todos aceitam de novo no próximo acesso.

## Como cada parte funciona

### Cookies (`consent.js`)
- Escolha em `localStorage` (`homelab.consent`, com versão). Sem escolha: banner e nenhum rastreador.
- "Rejeitar tudo" e "Aceitar tudo" têm as mesmas classes (o teste confere), lado a lado. "Personalizar" abre o painel com as categorias desligadas por padrão. Rolar, clicar fora ou navegar não grava nada (o teste confere que não há listener de scroll/teclado).
- Rodapé: "Preferências de cookies" reabre o painel. Revogar uma categoria que já carregou apaga os cookies listados no registro e recarrega a página.
- Prova do consentimento: `POST /api/consent` grava `consents/{id}` (`at`, `version`, `analytics`, `marketing`), sem dado pessoal; o IP e a hora ficam no `accessLogs` da mesma chamada.

### Aceite dos termos e idade
- `/entrar/` mostra a caixa (desmarcada) depois do login se `me.terms.accepted` for falso; `POST /api/terms/accept {version}` só aceita a versão vigente (409 se for antiga). O servidor grava `termsVersion` e `termsAcceptedAt` no usuário.
- `/api/usage` e `/api/checkout` respondem 403 `terms_required` sem aceite da versão atual (e `age_required` quando a flag de idade estiver ligada).
- A resposta assinada de `/api/me` traz `termsOk` e `ageOk` para o `AccessPolicy` do app.

### Arrependimento (7 dias)
- O webhook grava `subscription.startedAt` (`start_date` do Stripe). `WithdrawalPolicy` libera a desistência até `startedAt + 7 dias` para assinatura `active`/`trialing` ainda não desfeita; `/api/me` devolve `withdrawalUntil`.
- `POST /api/refund`: acha a última cobrança paga do cliente, **reembolsa o valor integral** (chave de idempotência `withdrawal-<sub>`), cancela a assinatura na hora (`DELETE /subscriptions/<id>`), grava `withdrawal` no usuário e marca a assinatura como cancelada. 403 `withdrawal_period_over` fora do prazo, 409 `already_withdrawn`, 404 sem assinatura.
- Cancelar depois dos 7 dias continua pelo portal ("Gerenciar assinatura"), mesmo canal da compra.

### Registro de acesso
- Todo pedido que chega ao `Router` vira `accessLogs/{id}`: `at`, `ip`, `method`, `path`, `status`, `uid` (se autenticado) e `expireAt` (= `at` + 186 dias, os 6 meses mais folga do TTL). Nunca token, cabeçalho nem corpo.
- Não entram: o webhook do Stripe (não é usuário) e as respostas 429 (limite de requisições, para um ataque não encher o banco). Falha ao gravar o log não derruba a resposta; vai para `logs/api.log`.
- Sigilo: as regras do Firestore negam tudo ao cliente e só o servidor (conta de serviço) grava. Ninguém lê isso por rota. Só entregue a autoridade mediante ordem judicial.

## Como testar
- Automático: `docker run --rm -v "$PWD":/app -w /app php:8.3-cli php tests/run.php` (inclui `tests/compliance.test.php` e `tests/site.test.php`).
- Manual: roteiro em `docs/chaves-e-testes.md`, seção "Conformidade".
