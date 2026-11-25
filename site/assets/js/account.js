/* Base das páginas de conta: Firebase Auth (login Google) e chamadas autenticadas à /api do mesmo domínio. */
import { initializeApp } from "https://www.gstatic.com/firebasejs/10.14.1/firebase-app.js";
import {
  getAuth, GoogleAuthProvider, onAuthStateChanged, signInWithPopup, signOut,
} from "https://www.gstatic.com/firebasejs/10.14.1/firebase-auth.js";
import { firebaseConfig } from "../../firebase-config.js";

const app = initializeApp(firebaseConfig);
export const auth = getAuth(app);

export const configured = !String(firebaseConfig.apiKey).startsWith("COLE_");

export function onUser(callback) { return onAuthStateChanged(auth, callback); }

export async function loginWithGoogle() {
  const provider = new GoogleAuthProvider();
  provider.setCustomParameters({ prompt: "select_account" });
  return signInWithPopup(auth, provider);
}

export function logout() { return signOut(auth); }

/** Chama a API com o ID token do usuário. Nunca manda a chave ou o preço: o servidor decide. */
export async function api(path, { method = "GET", body } = {}) {
  const headers = { Accept: "application/json" };
  const user = auth.currentUser;
  if (user) headers.Authorization = "Bearer " + (await user.getIdToken());
  if (body !== undefined) headers["Content-Type"] = "application/json";
  const res = await fetch("/api" + path, { method, headers, body: body === undefined ? undefined : JSON.stringify(body) });
  let data = {};
  try { data = await res.json(); } catch (e) { /* corpo vazio */ }
  if (!res.ok) { const err = new Error(data.error || "erro"); err.status = res.status; err.code = data.error; throw err; }
  return data;
}

export function setText(id, text) { const el = document.getElementById(id); if (el) el.textContent = text; }
export function show(id, visible) { const el = document.getElementById(id); if (el) el.hidden = !visible; }
