# HomeLab Web

Site público do HomeLab: landing, login, área de assinaturas e a API que valida o plano do app.

## Organização

```
site/                     → vai para public_html/ na hospedagem
  index.html, styles.css, main.js, scene.js, assets/   landing
  entrar/                 login com Google (também devolve o login ao app)
  assinaturas/            escolher plano e abrir o pagamento
api/                      → vai para public_html/api/ (só o index.php, o ponto de entrada)
  index.php               ponto de entrada: /api/me, /usage, /plans, /checkout, /portal, /webhook
src/                      → vai para fora de public_html (classes PHP: token, Stripe, Firestore, planos)
docs/                     decisões e como publicar
.github/workflows/        deploy por SFTP
```

- **Front:** HTML, CSS e JavaScript puros, sem etapa de build.
- **Back:** PHP 8+ (versão escolhida no hPanel da hospedagem).
- **Dados:** Firebase (Auth e Firestore). **Pagamento:** Stripe.
- **Segredos:** nunca neste repositório (ele é público). Veja `docs/seguranca.md` e `docs/chaves-e-testes.md`.

## Documentação

- `docs/seguranca.md`: onde cada chave vive e o que fica exposto (resultado da validação).
- `docs/viabilidade-hostinger.md`: o que a hospedagem precisa ter e como conferir.
- `docs/api.md`: rotas, planos e dados.
- `docs/chaves-e-testes.md`: quais chaves, onde colocar, como rodar os testes e testar localmente.

## Rodar

```
docker run --rm -v "$PWD":/app -w /app php:8.3-cli php tests/run.php   # testes (sem chaves)
tools/dev.sh                                                          # site + API em localhost:8080
```
