/*
 * Snackbar: porte para JS puro do snackbar do app desktop (mesma estrutura, tempos e animações).
 * Três posições, uma mensagem por vez em cada: "corner" (sucesso, canto direito), "center" (info, centro)
 * e "alert" (erro, centro, abaixo). Cada mensagem tem ícone, texto, ação opcional, botão de fechar e uma
 * barra de contagem regressiva. Tempos do desktop: 3 s; erros ficam pelo menos 6 s; a saída leva 240 ms.
 */
const DEFAULT_MS = 3000;
const ERROR_MIN_MS = 6000;
const EXIT_MS = 240;
const NS = "http://www.w3.org/2000/svg";

const KINDS = {
  success: { slot: "corner", icon: "check" },
  info: { slot: "center", icon: "sparkles" },
  error: { slot: "alert", icon: "alert" },
};

// Ícones no traço do Lucide (o mesmo conjunto do desktop), viewBox 24x24.
const ICONS = {
  check: [["circle", { cx: 12, cy: 12, r: 10 }], ["path", { d: "m16 9-5.5 5.5L8 12" }]],
  alert: [["circle", { cx: 12, cy: 12, r: 10 }], ["line", { x1: 12, x2: 12, y1: 8, y2: 12 }], ["line", { x1: 12, x2: 12.01, y1: 16, y2: 16 }]],
  sparkles: [
    ["path", { d: "M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z" }],
    ["path", { d: "M20 2v4" }], ["path", { d: "M22 4h-4" }], ["circle", { cx: 4, cy: 20, r: 2 }],
  ],
  close: [["path", { d: "M18 6 6 18" }], ["path", { d: "m6 6 12 12" }]],
};

function icon(name, className) {
  const svg = document.createElementNS(NS, "svg");
  svg.setAttribute("viewBox", "0 0 24 24");
  svg.setAttribute("aria-hidden", "true");
  svg.setAttribute("class", className);
  for (const [tag, attrs] of ICONS[name]) {
    const node = document.createElementNS(NS, tag);
    for (const [k, v] of Object.entries(attrs)) node.setAttribute(k, String(v));
    svg.append(node);
  }
  return svg;
}

const slots = {};      // slot -> elemento que recebe a mensagem
const timers = {};     // slot -> timeout da mensagem atual
let seq = 0;

function ensureRoot() {
  const root = document.getElementById("snack");
  if (!root || slots.corner) return root;
  const make = (region, names) => {
    const box = document.createElement("div");
    box.className = "snack-region";
    box.dataset.region = region;
    box.setAttribute("aria-live", "polite");
    for (const name of names) { slots[name] = document.createElement("div"); slots[name].className = "snack-slot"; box.append(slots[name]); }
    root.append(box);
  };
  make("corner", ["corner"]);
  make("center", ["center", "alert"]);
  return root;
}

function leave(slot) {
  clearTimeout(timers[slot]);
  const el = slots[slot].firstElementChild;
  if (!el || el.classList.contains("leaving")) return;
  el.classList.add("leaving");
  timers[slot] = setTimeout(() => el.remove(), EXIT_MS);
}

/**
 * @param {"success"|"info"|"error"} kind
 * @param {string} text
 * @param {{action?: {label: string, onClick: () => void}, duration?: number}} [options]
 */
export function snack(kind, text, { action, duration } = {}) {
  if (!ensureRoot()) return;
  const { slot, icon: iconName } = KINDS[kind] || KINDS.success;
  const ms = duration ?? (kind === "error" ? Math.max(DEFAULT_MS, ERROR_MIN_MS) : DEFAULT_MS);

  clearTimeout(timers[slot]);
  const el = document.createElement("div");
  el.className = "snack";
  el.dataset.kind = kind;
  el.dataset.id = String(++seq);
  el.setAttribute("role", kind === "error" ? "alert" : "status");

  const msg = document.createElement("span");
  msg.className = "snack-text";
  msg.textContent = text;
  el.append(icon(iconName, "snack-ico"), msg);

  if (action) {
    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "snack-action";
    btn.textContent = action.label;
    btn.addEventListener("click", () => { action.onClick(); leave(slot); });
    el.append(btn);
  }

  const close = document.createElement("button");
  close.type = "button";
  close.className = "snack-close";
  close.setAttribute("aria-label", "Fechar");
  close.append(icon("close", "snack-ico"));
  close.addEventListener("click", () => leave(slot));
  el.append(close);

  const bar = document.createElement("span");
  bar.className = "snack-bar";
  bar.setAttribute("aria-hidden", "true");
  bar.style.animationDuration = ms + "ms"; // via CSSOM: permitido pela CSP estrita
  el.append(bar);

  slots[slot].replaceChildren(el);
  timers[slot] = setTimeout(() => leave(slot), ms);
}
