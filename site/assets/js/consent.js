/*
 * Banner de cookies (LGPD). Regras:
 *  - nenhum script de análise ou marketing roda antes do aceite da categoria (ver trackers-config.js);
 *  - "Rejeitar tudo" e "Aceitar tudo" têm o mesmo tamanho, a mesma cor e ficam lado a lado;
 *  - rolar, clicar ou continuar navegando NÃO é consentimento: sem escolha, tudo continua desligado;
 *  - a escolha pode ser mudada a qualquer momento pelo link "Preferências de cookies" (data-consent-open).
 * Sem innerHTML: a interface é montada pelo DOM (compatível com a CSP estrita das páginas de conta).
 */
(function () {
  "use strict";

  var KEY = "homelab.consent";
  var VERSION = 1; // subir quando a lista de categorias ou os textos mudarem: todos escolhem de novo
  var CATEGORIES = [
    { id: "analytics", title: "Análise de uso", text: "Mostra como o site é usado, sem identificar você, para melhorarmos a página." },
    { id: "marketing", title: "Marketing", text: "Mede campanhas e permite anúncios mais relevantes em outros sites." },
  ];

  var memory = null; // se o navegador bloquear o armazenamento, vale só até fechar a página
  var loaded = {};
  var root = null;

  function read() {
    var raw = null;
    try { raw = localStorage.getItem(KEY); } catch (e) { /* sem armazenamento */ }
    try {
      var v = raw ? JSON.parse(raw) : memory;
      if (v && v.version === VERSION && typeof v.analytics === "boolean" && typeof v.marketing === "boolean") return v;
    } catch (e) { /* valor corrompido: pergunta de novo */ }
    return null;
  }

  function write(choice) {
    memory = choice;
    try { localStorage.setItem(KEY, JSON.stringify(choice)); } catch (e) { /* sem armazenamento */ }
  }

  /** Prova da escolha no servidor (anônima). Falha de rede não atrapalha a navegação. */
  function sendProof(choice) {
    try {
      fetch("/api/consent", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ version: choice.version, analytics: choice.analytics, marketing: choice.marketing }),
        keepalive: true,
      }).catch(function () {});
    } catch (e) { /* ignora */ }
  }

  function trackers() { return Array.isArray(window.HOMELAB_TRACKERS) ? window.HOMELAB_TRACKERS : []; }

  function loadAllowed(choice) {
    trackers().forEach(function (t) {
      if (!choice[t.category] || loaded[t.id]) return;
      var s = document.createElement("script");
      s.async = true;
      s.src = t.src;
      s.setAttribute("data-consent-tracker", t.id);
      if (typeof t.after === "function") s.addEventListener("load", t.after);
      document.head.appendChild(s);
      loaded[t.id] = true;
    });
  }

  function deleteCookies(patterns) {
    var host = location.hostname;
    var domains = ["", host, "." + host, "." + host.split(".").slice(-2).join(".")];
    document.cookie.split(";").forEach(function (pair) {
      var name = pair.split("=")[0].trim();
      var hit = patterns.some(function (p) { return p instanceof RegExp ? p.test(name) : p === name; });
      if (!hit) return;
      domains.forEach(function (d) {
        document.cookie = name + "=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/" + (d ? "; domain=" + d : "");
      });
    });
  }

  /** Revogou uma categoria que já tinha carregado: apaga os cookies e recarrega, porque não dá para "descarregar" um script. */
  function revokeLoaded(choice) {
    var reload = false;
    trackers().forEach(function (t) {
      if (choice[t.category] || !loaded[t.id]) return;
      deleteCookies(t.cookies || []);
      reload = true;
    });
    if (reload) location.reload();
  }

  function el(tag, props, children) {
    var node = document.createElement(tag);
    Object.keys(props || {}).forEach(function (k) {
      if (k === "text") node.textContent = props[k];
      else if (k === "class") node.className = props[k];
      else node.setAttribute(k, props[k]);
    });
    (children || []).forEach(function (c) { node.appendChild(c); });
    return node;
  }

  function close() {
    if (root) { root.remove(); root = null; }
  }

  function decide(analytics, marketing) {
    var choice = { version: VERSION, analytics: analytics, marketing: marketing, at: new Date().toISOString() };
    write(choice);
    sendProof(choice);
    close();
    revokeLoaded(choice);
    loadAllowed(choice);
    document.dispatchEvent(new CustomEvent("homelab:consent", { detail: choice }));
  }

  function button(label, act, cls) {
    return el("button", { type: "button", class: cls, "data-act": act, text: label });
  }

  function open(showPanel) {
    if (root) { root.querySelector("[data-act]").focus(); return; }
    var current = read() || { analytics: false, marketing: false };

    var checks = {};
    var rows = [el("div", { class: "cc-row" }, [
      el("input", { type: "checkbox", checked: "", disabled: "", "aria-label": "Armazenamento essencial, sempre ativo" }),
      el("span", {}, [el("strong", { text: "Essenciais" }), el("small", { text: "Tema, sua escolha de cookies e o login. Necessários para o site funcionar; sempre ativos." })]),
    ])];
    CATEGORIES.forEach(function (c) {
      var input = el("input", { type: "checkbox", id: "cc-" + c.id });
      input.checked = current[c.id] === true;
      checks[c.id] = input;
      rows.push(el("label", { class: "cc-row", for: "cc-" + c.id }, [input, el("span", {}, [el("strong", { text: c.title }), el("small", { text: c.text })])]));
    });
    var panel = el("div", { class: "cc-panel", id: "cc-panel" }, rows);
    panel.hidden = !showPanel;

    var link = el("a", { href: "/privacidade/#cookies", text: "Política de privacidade e cookies" });
    var text = el("p", { id: "cc-text" }, [
      document.createTextNode("Usamos só o essencial para o site funcionar. Análise de uso e marketing ficam desligados até você escolher. Leia a "),
      link,
      document.createTextNode("."),
    ]);

    var reject = button("Rejeitar tudo", "reject", "btn hx btn-primary cc-btn");
    var accept = button("Aceitar tudo", "accept", "btn hx btn-primary cc-btn");
    var save = button("Salvar escolhas", "save", "btn hx btn-primary cc-btn");
    save.hidden = !showPanel;
    var custom = button("Personalizar", "custom", "linklike cc-custom");
    custom.setAttribute("aria-expanded", showPanel ? "true" : "false");
    custom.setAttribute("aria-controls", "cc-panel");
    custom.hidden = showPanel;

    reject.addEventListener("click", function () { decide(false, false); });
    accept.addEventListener("click", function () { decide(true, true); });
    save.addEventListener("click", function () { decide(checks.analytics.checked, checks.marketing.checked); });
    custom.addEventListener("click", function () {
      panel.hidden = false; save.hidden = false; custom.hidden = true;
      custom.setAttribute("aria-expanded", "true");
      checks.analytics.focus();
    });

    root = el("div", { class: "cc", role: "dialog", "aria-modal": "false", "aria-labelledby": "cc-title", "aria-describedby": "cc-text" }, [
      el("div", { class: "cc-body" }, [
        el("h2", { id: "cc-title", text: "Sua privacidade" }),
        text,
        panel,
        el("div", { class: "cc-actions" }, [reject, accept, save, custom]),
      ]),
    ]);
    document.body.appendChild(root);
    if (showPanel) reject.focus(); // só puxa o foco quando a pessoa pediu para abrir; no primeiro acesso não rouba o foco da página
  }

  function init() {
    var choice = read();
    if (choice) loadAllowed(choice); else open(false);
    document.addEventListener("click", function (e) {
      var trigger = e.target.closest ? e.target.closest("[data-consent-open]") : null;
      if (trigger) { e.preventDefault(); open(true); }
    });
  }

  window.homelabConsent = { get: read, open: function () { open(true); } };
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init); else init();
})();
