/* Área de assinatura: mostra o plano (vindo do servidor) e leva ao pagamento/portal do Stripe. */
import { api, configured, logout, onUser, setText, show } from "./account.js";

const status = new URLSearchParams(location.search).get("status");
const fmtDate = (s) => new Date(s * 1000).toLocaleDateString("pt-BR");
const fmtMoney = (cents, cur) => (cents / 100).toLocaleString("pt-BR", { style: "currency", currency: String(cur).toUpperCase() });
const DAY = 86400;

function note(id, message) { setText(id, message); show(id, true); show(id === "error" ? "info" : "error", false); }

function render(me) {
  const now = Date.now() / 1000;
  const pro = me.plan === "pro";
  if (pro) {
    setText("plan-name", "Pro");
    setText("plan-detail", me.cancelsAtPeriodEnd ? "Cancelada: vale até " + fmtDate(me.proValidUntil) : "Ativa. Renovação até " + fmtDate(me.proValidUntil));
  } else if (me.plan === "trial") {
    const days = Math.max(1, Math.ceil((me.trialEndsAt - now) / DAY));
    setText("plan-name", "Teste grátis");
    setText("plan-detail", days === 1 ? "Termina hoje ou amanhã" : "Faltam " + days + " dias");
  } else {
    setText("plan-name", "Teste encerrado");
    setText("plan-detail", "Você continua vendo os resumos e transcrições que já fez. Assine para voltar a gerar.");
  }
  show("pro-card", !pro);
  show("subscribe", !pro);
  show("manage", pro);
}

async function load() {
  const me = await api("/me");
  render(me);
  return me;
}

async function waitForPro() {
  note("info", "Pagamento recebido! Confirmando sua assinatura…");
  for (let i = 0; i < 15; i++) {
    const me = await load();
    if (me.plan === "pro") return note("info", "Tudo certo! Seu plano Pro está ativo. Reabra o app para liberar os recursos.");
    await new Promise((r) => setTimeout(r, 2000));
  }
  note("info", "Ainda estamos confirmando. Atualize esta página em alguns instantes.");
}

async function start() {
  show("loading", false);
  show("signed-in", true);
  try {
    api("/plans").then((p) => { if (p.pro) setText("price", fmtMoney(p.pro.amount, p.pro.currency)); }).catch(() => {});
    if (status === "ok") await waitForPro(); else await load();
    if (status === "cancel") note("info", "Pagamento cancelado. Você pode assinar quando quiser.");
  } catch (e) {
    note("error", "Não foi possível carregar sua assinatura agora. Tente de novo em instantes.");
  }
}

async function go(path, button, errorText) {
  button.disabled = true;
  try {
    const { url } = await api(path, { method: "POST", body: {} });
    location.assign(url);
  } catch (e) {
    button.disabled = false;
    note("error", e.code === "already_pro" ? "Você já tem o plano Pro." : errorText);
  }
}

if (!configured) { show("loading", false); show("signed-out", true); } else {
  onUser((user) => {
    if (!user) { show("loading", false); show("signed-in", false); show("signed-out", true); return; }
    show("signed-out", false);
    setText("user", user.email || "");
    start();
  });
  document.getElementById("subscribe").addEventListener("click", (e) => go("/checkout", e.currentTarget, "Não foi possível abrir o pagamento. Tente de novo."));
  document.getElementById("manage").addEventListener("click", (e) => go("/portal", e.currentTarget, "Não foi possível abrir o portal. Tente de novo."));
  document.getElementById("logout").addEventListener("click", async () => { await logout(); location.assign("/"); });
}
