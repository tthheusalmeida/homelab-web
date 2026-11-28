/* Área de assinatura: mostra o plano (vindo do servidor) e leva ao pagamento/portal do Stripe. */
import { api, configured, logout, onUser, setText, show } from "./account.js";
import { snack } from "./snackbar.js";

const status = new URLSearchParams(location.search).get("status");
const fmtDate = (s) => new Date(s * 1000).toLocaleDateString("pt-BR");
const fmtMoney = (cents, cur) => (cents / 100).toLocaleString("pt-BR", { style: "currency", currency: String(cur).toUpperCase() });
const DAY = 86400;
const PRO_NOTE = "Estude à vontade; o app confirma a assinatura online a cada 30 dias.";

/** Avisos e erros aparecem como snackbar (kind = "success", "info" ou "error"). */
function note(id, message, options) { snack(id, message, options); }

/** Um único lugar decide o que cada trecho da página mostra para cada situação do plano. */
function paint({ state, name, detail, pill, trialBadge, proBadge, proNote, cta, current }) {
  document.getElementById("status").dataset.state = state;
  setText("plan-name", name);
  setText("plan-detail", detail);
  setText("plan-pill", pill);
  setText("pro-note", proNote || PRO_NOTE);
  setText("pro-badge", proBadge);
  setText("trial-badge", trialBadge || "");
  show("trial-badge", Boolean(trialBadge));
  const card = (id, on, warn) => {
    const el = document.getElementById(id);
    el.classList.toggle("is-current", on);
    el.classList.toggle("warn", on && Boolean(warn));
  };
  card("trial-card", current === "trial");
  card("pro-card", current === "pro", state === "cancel");
  show("subscribe", cta === "subscribe");
  show("manage", cta === "manage");
  show("signin", cta === "signin");
}

function renderSignedOut() {
  paint({
    state: "expired", name: "Você não está logado", detail: "Entre com sua conta Google para ver seu plano e assinar o Pro.",
    pill: "VISITANTE", proBadge: "PRO", cta: "signin", current: "",
  });
}

/** Desistência em até 7 dias (CDC, art. 49): só aparece para quem ainda está no prazo. */
function paintWithdrawal(me) {
  const until = me.plan === "pro" ? me.withdrawalUntil : null;
  show("withdraw", Boolean(until));
  if (until) setText("withdraw-text", "Você pode desistir da compra até " + fmtDate(until) + ", sem justificar, com reembolso integral.");
  resetWithdrawButton();
}

function render(me) {
  paintWithdrawal(me);
  const now = Date.now() / 1000;
  if (me.plan === "pro") {
    const cancel = me.cancelsAtPeriodEnd;
    paint({
      state: cancel ? "cancel" : "pro", name: "HomeLab Pro",
      detail: cancel ? "Cancelada: seus recursos valem até " + fmtDate(me.proValidUntil) : "Assinatura ativa. Renova em " + fmtDate(me.proValidUntil),
      pill: cancel ? "CANCELADA" : "ATIVO", proBadge: cancel ? "CANCELADA" : "ATIVO",
      proNote: cancel ? "Você pode reativar a qualquer momento antes de " + fmtDate(me.proValidUntil) + "." : "Obrigado por apoiar o HomeLab! Renova em " + fmtDate(me.proValidUntil) + ".",
      cta: "manage", current: "pro",
    });
  } else if (me.plan === "trial") {
    const days = Math.max(1, Math.ceil((me.trialEndsAt - now) / DAY));
    paint({
      state: "trial", name: "Teste grátis", detail: days === 1 ? "Termina hoje ou amanhã" : "Faltam " + days + " dias",
      pill: "TESTE", trialBadge: "SEU PLANO", proBadge: "PRO", cta: "subscribe", current: "trial",
    });
  } else {
    paint({
      state: "expired", name: "Teste encerrado",
      detail: "Você continua vendo os resumos e transcrições que já fez. Assine para voltar a gerar.",
      pill: "ENCERRADO", trialBadge: "ENCERRADO", proBadge: "PRO", cta: "subscribe", current: "",
    });
  }
}

async function load() {
  const me = await api("/me");
  render(me);
  return me;
}

async function waitForPro() {
  note("info", "Pagamento recebido! Confirmando sua assinatura…", { duration: 30000 });
  for (let i = 0; i < 15; i++) {
    const me = await load();
    if (me.plan === "pro") return note("success", "Tudo certo! Seu plano Pro está ativo. Reabra o app para liberar os recursos.");
    await new Promise((r) => setTimeout(r, 2000));
  }
  note("info", "Ainda estamos confirmando. Atualize esta página em alguns instantes.");
}

async function start() {
  try {
    api("/plans").then((p) => { if (p.pro) { const price = fmtMoney(p.pro.amount, p.pro.currency); setText("price", price); setText("price-terms", price); } }).catch(() => {});
    if (status === "ok") await waitForPro(); else await load();
    if (status === "cancel") note("info", "Pagamento cancelado. Você pode assinar quando quiser.");
  } catch (e) {
    document.getElementById("status").dataset.state = "expired";
    setText("plan-name", "Não foi possível carregar");
    setText("plan-pill", "ERRO");
    note("error", "Não foi possível carregar sua assinatura agora. Tente de novo em instantes.");
  }
}

async function go(path, button, errorText) {
  button.disabled = true;
  button.classList.add("loading");
  try {
    const { url } = await api(path, { method: "POST", body: {} });
    location.assign(url);
  } catch (e) {
    button.disabled = false;
    button.classList.remove("loading");
    if (e.code === "terms_required") return location.assign("/entrar/?next=assinaturas"); // aceitar os termos vigentes antes de pagar
    if (e.code === "age_required") return note("error", "Falta verificar a sua idade para assinar. Em breve essa etapa estará disponível.");
    note("error", e.code === "already_pro" ? "Você já tem o plano Pro." : errorText);
  }
}

const WITHDRAW_LABEL = "Desistir da compra e pedir reembolso";
let withdrawTimer = 0;
function resetWithdrawButton() {
  clearTimeout(withdrawTimer);
  const btn = document.getElementById("withdraw-btn");
  btn.textContent = WITHDRAW_LABEL;
  btn.dataset.confirm = "";
  btn.disabled = false;
  btn.classList.remove("loading");
}

/** Dois cliques: o primeiro pede confirmação (some sozinho em 8 s), o segundo desiste e pede o reembolso integral. */
async function withdraw(btn) {
  if (btn.dataset.confirm !== "1") {
    btn.dataset.confirm = "1";
    btn.textContent = "Confirmar: cancelar e reembolsar";
    withdrawTimer = setTimeout(resetWithdrawButton, 8000);
    return;
  }
  clearTimeout(withdrawTimer);
  btn.disabled = true;
  btn.classList.add("loading");
  try {
    await api("/refund", { method: "POST", body: {} });
    note("success", "Pronto! Sua assinatura foi cancelada e o reembolso integral foi solicitado. O prazo para aparecer na fatura depende da operadora do cartão.", { duration: 12000 });
    await load();
  } catch (e) {
    resetWithdrawButton();
    const messages = {
      withdrawal_period_over: "O prazo de 7 dias já passou. Você ainda pode cancelar em “Gerenciar assinatura”.",
      already_withdrawn: "Esta compra já foi cancelada e reembolsada.",
    };
    note("error", messages[e.code] || "Não foi possível pedir o reembolso agora. Tente de novo ou escreva para o nosso e-mail de contato.");
    load().catch(() => {});
  }
}

if (!configured) { renderSignedOut(); } else {
  onUser((user) => {
    show("userbar", Boolean(user));
    if (!user) return renderSignedOut();
    setText("user", user.email || "");
    start();
  });
  document.getElementById("subscribe").addEventListener("click", (e) => go("/checkout", e.currentTarget, "Não foi possível abrir o pagamento. Tente de novo."));
  document.getElementById("manage").addEventListener("click", (e) => go("/portal", e.currentTarget, "Não foi possível abrir o portal. Tente de novo."));
  document.getElementById("withdraw-btn").addEventListener("click", (e) => withdraw(e.currentTarget));
  document.getElementById("logout").addEventListener("click", async () => { await logout(); location.assign("/"); });
}
