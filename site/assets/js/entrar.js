/* Login no navegador. Com ?app=1&port=...&state=..., devolve o login ao app desktop pela porta local. */
import { api, configured, loginWithGoogle, onUser, setText, show } from "./account.js";

const params = new URLSearchParams(location.search);
const fromApp = params.get("app") === "1";
const port = Number(params.get("port"));
const state = params.get("state") || "";
const appFlowValid = fromApp && Number.isInteger(port) && port >= 1024 && port <= 65535 && /^[A-Za-z0-9_-]{16,128}$/.test(state);
const NEXT = { assinaturas: "/assinaturas/" };
const next = NEXT[params.get("next")] || null;

function fail(message) { setText("error-text", message); show("error", true); show("info", false); }
function info(message) { setText("info-text", message); show("info", true); show("error", false); }

function backToApp(user) {
  // O refresh token vai só para 127.0.0.1 (este computador) e o app confere o "state" que ele mesmo gerou.
  const url = "http://127.0.0.1:" + port + "/callback?state=" + encodeURIComponent(state) + "&refresh_token=" + encodeURIComponent(user.refreshToken);
  info("Pronto! Voltando para o app…");
  setText("subtitle", "Você já pode fechar esta aba.");
  location.replace(url);
}

const loginLabel = document.getElementById("login").textContent;
function setBusy(busy) {
  const btn = document.getElementById("login");
  btn.disabled = busy;
  btn.classList.toggle("loading", busy);
  btn.textContent = busy ? "Entrando…" : loginLabel;
}

async function afterLogin(user) {
  // O botão continua no lugar (só fica "carregando") até a resposta chegar: o card não muda de altura.
  setBusy(true);
  try {
    await api("/me"); // o primeiro acesso cria o registro e inicia o trial (no servidor)
  } catch (e) {
    fail("Não foi possível confirmar sua conta agora. Tente de novo em instantes.");
    setBusy(false);
    return;
  }
  if (appFlowValid) return backToApp(user);
  if (next) return location.replace(next);
  document.querySelector(".acc-card").classList.add("done");
  setText("eyebrow", "root@homelab:~$ sessão iniciada");
  setText("title", "Você está logado");
  setText("subtitle", user.email || "");
  show("login", false);
  show("continue", true);
}

if (fromApp && !appFlowValid) fail("Link do app inválido. Abra o login de novo pelo aplicativo.");
if (!configured) fail("Login ainda não configurado neste ambiente.");

if (configured) {
  onUser((user) => { if (user) afterLogin(user); });
  const loginBtn = document.getElementById("login");
  loginBtn.addEventListener("click", async () => {
    show("error", false);
    loginBtn.classList.add("loading");
    try { await loginWithGoogle(); } catch (e) {
      if (e && e.code === "auth/popup-blocked") fail("O navegador bloqueou a janela de login. Permita pop-ups para este site e tente de novo.");
      else if (e && e.code !== "auth/popup-closed-by-user" && e.code !== "auth/cancelled-popup-request") fail("Não foi possível entrar com o Google. Tente de novo.");
    } finally {
      if (!loginBtn.disabled) loginBtn.classList.remove("loading"); // se o /me já está em andamento, o spinner continua
    }
  });
}
