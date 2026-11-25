# Viabilidade na Hostinger (Premium Web Hosting)

Resumo: **viável**, com uma ressalva a confirmar no seu plano (itens 1 a 4 abaixo).

## O que o plano oferece (conforme a documentação que você consultou)
- PHP escolhido no hPanel (use 8.2 ou mais). O FTP em si não executa PHP.
- FTP comum sem criptografia. **Não use.** Use **SFTP/SSH na porta 65002**.
- Domínio e SSL gratuito (HTTPS) para o webhook do Stripe.

## O que o projeto precisa e como conferir
1. **PHP ≥ 8.2 com `openssl`, `curl` e `sodium`**: rode `php tools/check-env.php` por SSH.
2. **Saída HTTPS** para `googleapis.com` e `api.stripe.com`: o mesmo script testa.
3. **Pasta fora de `public_html`** (`<raiz do domínio>/private`) gravável, com permissão 600: o script confere.
4. **`rsync` por SSH**: se não existir no servidor, troque o passo do `deploy.yml` por `scp -r` (mesma ideia).

## Como ficam as pastas no servidor
```
<raiz do domínio>/
  public_html/            ← site/ (landing, /entrar, /assinaturas)
    api/index.php         ← único PHP público (roteador)
  private/                ← fora da web
    src/                  ← classes PHP
    config.php            ← gerado no deploy, permissão 600
    cache/ logs/ ratelimit/
```

## Limites a ter em mente
- Hospedagem compartilhada tem limite de processos e tempo por requisição; esta API é leve (poucas chamadas curtas), mas o webhook e o `/api/me` dependem de Firestore e Stripe responderem rápido.
- O cache do token Google e do preço fica em arquivo (pasta `private/cache`).
- Sem domínio ainda: tudo roda **local** (veja `chaves-e-testes.md`). O deploy é manual (`workflow_dispatch`) e só deve ser ligado depois que o domínio e o ambiente `production` existirem.
