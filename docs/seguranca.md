# Segurança: repositório público, chaves e deploy

O repositório é **público**. Por isso a segurança nunca depende de alguém não ler o código: o código mostra *como* validamos, mas não contém nenhuma chave.

## Onde cada chave vive

| Etapa | O que existe lá | Chave exposta? |
|---|---|---|
| Repositório Git | só código, `config.example.php` e `.env.example` (valores de mentira) | **Não.** `.gitignore` bloqueia `config.php`, `.env.*`, `serviceAccount*.json`; o teste `tests/audit.test.php` falha se aparecer chave privada, `sk_live`, `rk_live` ou `whsec_` |
| GitHub Actions | secrets do ambiente `production`, cifrados | Mascarados nos logs. Forks **não** recebem secrets. O `config.php` é gerado no runner efêmero e apagado no fim |
| Transporte | SFTP/SSH (porta 65002, host conferido por `known_hosts`) | Não: cifrado e sem aceitar host desconhecido |
| Servidor | `private/config.php` com permissão **600**, **fora de `public_html`** | Não pela web: nenhuma URL chega nessa pasta. Se o PHP falhar e mostrar código-fonte, só `api/index.php` (sem segredo) está ao alcance |
| Navegador / app | só config web do Firebase (pública por natureza) e a chave **pública** de verificação | Nenhuma chave secreta |

**Conclusão da validação:** com esta estratégia, nenhuma chave fica exposta pelo repositório, pelos logs do deploy ou pela web. O que **sobra de risco** é humano e de infraestrutura, não de código:

1. Quem controlar sua conta do GitHub ou o ambiente `production` consegue ler os secrets (alterando o workflow). Mitigação: ambiente protegido só para a branch `main`, aprovação obrigatória, 2FA, e "exigir aprovação para workflows de forks" nas configurações do repositório.
2. Quem controlar sua conta da Hostinger (ou a própria Hostinger, via backup) lê `private/config.php`. Mitigação: 2FA, senha forte, chave SSH só de deploy, e as chaves abaixo com **permissão mínima**, para limitar o estrago.

## Chaves com o menor poder possível

- **Stripe:** chave **restrita** (`rk_...`), só com: Checkout Sessions (escrita), Customer Portal (escrita), Prices (leitura), Customers e Subscriptions (leitura). Nunca a `sk_`.
- **Firebase (conta de serviço):** só o papel `roles/datastore.user`. Nunca Owner/Editor. Se vazar, alguém mexe no Firestore, mas não no projeto.
- **Assinatura de `/api/me`:** a chave privada Ed25519 fica só no servidor; o app embute a pública.
- **Regras do Firestore:** `allow read, write: if false`. Ninguém (nem o app, nem o navegador) fala direto com o banco; só o servidor.

## O que o servidor garante, mesmo com o código público

- Token do Firebase conferido por assinatura RS256, emissor, público (`aud`), validade e provedor Google com e-mail verificado.
- O preço e o usuário do checkout **vêm do servidor**; o corpo da requisição é ignorado (teste cobre).
- O portal do Stripe usa o cliente salvo no servidor, nunca um id enviado pelo cliente.
- Webhook só vale com a assinatura do Stripe (HMAC, 5 min de tolerância); evento antigo/repetido não desfaz estado novo.
- Trial por **e-mail verificado** (apagar a conta e criar de novo não renova) e com o **relógio do servidor**.
- Cota diária contada no servidor, por dia de São Paulo.
- Limite de requisições por IP, `Origin` conferido, erros sem detalhes (500 genérico; o detalhe vai para um log fora da pasta pública, sem token nem chave).
- Páginas de conta com CSP estrita (sem script inline) e HTTPS forçado com HSTS.

## O que continua sendo só dissuasão

O app é de código aberto: quem recompilar o app pode remover o bloqueio local. A resposta assinada impede editar o arquivo de plano, e a cota no servidor impede gerar sem internet, mas não existe proteção total contra quem altera o próprio executável.

## Quando trocar o PHP por um servidor Node/Worker separado

Só se **um** destes for verdade (confira com `php tools/check-env.php` por SSH):
- não dá para criar pasta fora de `public_html` ou aplicar permissão 600;
- faltam `openssl`, `curl` ou `sodium`, ou a saída HTTPS para Google/Stripe é bloqueada;
- você não aceita guardar o segredo como arquivo num servidor compartilhado (um Worker guarda segredos cifrados, sem sistema de arquivos).

A API foi escrita para isso ser fácil: as regras ficam em classes pequenas (`Entitlement`, `Plans`, `Stripe`) e o armazenamento atrás de `DocumentStore`; portar para outro runtime é reescrever os mesmos 6 endpoints.
