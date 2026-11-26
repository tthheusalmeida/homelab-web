(function () {
  "use strict";
  var HL = (window.HL = window.HL || { palette: [0x22d3ee], state: {} });
  var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var root = document.documentElement;

  /* ───── Tema claro/escuro ───── */
  function isDark() { return root.getAttribute("data-theme") !== "light"; }
  function syncMeta() {
    var m = document.querySelector('meta[name="theme-color"]');
    if (m) m.setAttribute("content", isDark() ? "#0b0c0e" : "#f4f5f7");
  }
  document.getElementById("theme-toggle").addEventListener("click", function () {
    var next = isDark() ? "light" : "dark";
    root.setAttribute("data-theme", next);
    try { localStorage.setItem("homelab.landing.theme", next); } catch (e) {}
    syncMeta();
    if (HL.setTheme) HL.setTheme(next === "dark");
  });
  syncMeta();
  if (HL.setTheme) HL.setTheme(isDark());

  /* ───── Barra de navegação ───── */
  var nav = document.getElementById("nav");
  function onNav() { nav.classList.toggle("scrolled", window.scrollY > 24); }
  window.addEventListener("scroll", onNav, { passive: true });
  onNav();

  /* ───── Revelar ao entrar na tela (inclui a sequência 1 → seta → 2 ...) ───── */
  var revealEls = document.querySelectorAll(".reveal, #flow");
  if ("IntersectionObserver" in window && !reduced) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) { e.target.classList.add("in"); io.unobserve(e.target); if (e.target.id === "flow") startFlow(); }
      });
    }, { threshold: 0.2, rootMargin: "0px 0px -8% 0px" });
    revealEls.forEach(function (el) { io.observe(el); });
  } else {
    revealEls.forEach(function (el) { el.classList.add("in"); });
    document.querySelectorAll("#flow [data-seq]").forEach(function (el) { el.classList.add("go", "done"); });
  }

  /* ───── Cartões inclinam suavemente com o mouse ───── */
  if (!reduced && window.matchMedia("(hover: hover)").matches) {
    document.querySelectorAll(".tilt").forEach(function (card) {
      card.addEventListener("pointermove", function (e) {
        var r = card.getBoundingClientRect();
        var x = (e.clientX - r.left) / r.width - 0.5;
        var y = (e.clientY - r.top) / r.height - 0.5;
        card.style.transform = "perspective(800px) rotateY(" + x * 7 + "deg) rotateX(" + -y * 7 + "deg) translateY(-4px)";
      });
      card.addEventListener("pointerleave", function () { card.style.transform = ""; });
    });
  }

  /* A colmeia com "dedo" (hover) agora vive em honey.js; no celular ela vira slide (slider.js). */

  /* ───── Teclado do notebook ───── */
  var keysEl = document.getElementById("keys");
  var keyEls = [];
  if (keysEl) {
    var layout = [
      [1.4, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1],
      [1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1.6],
      [1.5, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1.1],
      [1.8, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 2.1],
      [2.3, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 2.6],
      [1, 1, 1, 1.3, 5.6, 1.3, 1, 1, 1, 1]
    ];
    layout.forEach(function (row) {
      var r = document.createElement("div");
      r.className = "krow";
      row.forEach(function (w) {
        var k = document.createElement("span");
        k.className = "key";
        k.style.flex = w + " 1 0";
        r.appendChild(k);
        keyEls.push(k);
      });
      keysEl.appendChild(r);
    });
  }
  var keyTimer = 0;
  function pulseKeys() {
    if (reduced || !keyEls.length) return;
    keyEls.forEach(function (k) { k.classList.remove("on"); });
    for (var n = 0; n < 4; n++) keyEls[Math.floor(Math.random() * keyEls.length)].classList.add("on");
  }

  /* ───── História do notebook ───── */
  var story = document.querySelector("[data-story]");
  var shots = story.querySelectorAll(".shot");
  var wrap = document.getElementById("laptop-wrap");
  var steps = story.querySelectorAll(".story-steps li");
  var dots = story.querySelectorAll(".progress-dots i");
  var laptop = document.getElementById("laptop");
  var pal = HL.palette.map(function (c) { return "#" + c.toString(16).padStart(6, "0"); });
  var total = steps.length;
  var current = -1;
  var smoothP = 0, targetP = 0, smoothOpen = 0, targetOpen = 0;

  steps.forEach(function (li, i) { li.style.setProperty("--c", pal[i % pal.length]); });
  dots.forEach(function (d, i) { d.style.setProperty("--c", pal[i % pal.length]); });

  function setStep(i) {
    if (i === current) return;
    current = i;
    shots.forEach(function (s, k) { s.classList.toggle("is-active", k === i); });
    steps.forEach(function (s, k) {
      s.classList.toggle("active", k === i);
      s.classList.toggle("done", k < i);
    });
    dots.forEach(function (d, k) { d.classList.toggle("on", k === i); });
    if (wrap) wrap.style.setProperty("--amb", pal[i % pal.length]);
    pulseKeys();
  }

  function readStory() {
    var rect = story.getBoundingClientRect();
    var vh = window.innerHeight;
    var span = Math.max(1, rect.height - vh);
    targetP = Math.min(0.9999, Math.max(0, -rect.top / span));
    // o notebook começa fechado e abre conforme a seção sobe na tela
    targetOpen = Math.min(1, Math.max(0, (vh * 0.6 - rect.top) / (vh * 0.7)));
    setStep(Math.floor(targetP * total));

  }
  window.addEventListener("scroll", readStory, { passive: true });
  window.addEventListener("resize", readStory);
  readStory();
  if (current < 0) setStep(0);

  // o notebook gira continuamente com a rolagem (e não aos saltos)
  var lastKey = 0;
  function tick(now) {
    smoothP += (targetP - smoothP) * 0.08;
    smoothOpen += (targetOpen - smoothOpen) * (reduced ? 1 : 0.12);
    var op = smoothOpen * smoothOpen * (3 - 2 * smoothOpen);
    laptop.style.setProperty("--open", op.toFixed(3));
    HL.story = { step: current, open: op, p: smoothP }; // lido por device3d.js (notebook/tablet 3D)
    if (!reduced) {
      var ry = Math.sin(smoothP * Math.PI * 2.2) * 9 - 2;
      var rx = 2 + Math.cos(smoothP * Math.PI * 1.6) * 2;
      laptop.style.setProperty("--ry", ry.toFixed(2) + "deg");
      laptop.style.setProperty("--rx", rx.toFixed(2) + "deg");
      if (op > 0.95 && now - lastKey > 700) { lastKey = now; pulseKeys(); }
    }
    requestAnimationFrame(tick);
  }
  requestAnimationFrame(tick);


  /* ───── "Como usar": cada animação chama a próxima (passo → fluxo → passo ...) ───── */
  function startFlow() {
    var seq = Array.prototype.slice.call(document.querySelectorAll("#flow [data-seq]"));
    function play(i) {
      if (i >= seq.length) return;
      var el = seq[i], last = el.querySelector("[data-last]") || el.querySelector(".cell:last-child"), finished = false;
      function finish() {
        if (finished) return;
        finished = true;
        el.classList.add("done");
        play(i + 1);
      }
      if (last) last.addEventListener("animationend", function (ev) { if (ev.target === last) finish(); });
      el.classList.add("go");
      setTimeout(finish, 2200); // reserva, se o navegador não avisar o fim da animação
    }
    play(0);
  }

  /* ───── Clique nos recursos: rola até o ponto da história ───── */
  function goToStep(i) {
    var story = document.querySelector("[data-story]");
    var span = story.offsetHeight - window.innerHeight;
    // Pula direto (sem rolagem suave): a seção é "sticky", então o pulo não aparece na tela, e a rolagem suave
    // passaria por todos os recursos do caminho, trocando captura, texto e cor várias vezes (a "tremida").
    var root = document.documentElement;
    root.style.scrollBehavior = "auto"; // o CSS global tem scroll-behavior: smooth
    window.scrollTo(0, story.offsetTop + span * ((i + 0.5) / total));
    root.style.scrollBehavior = "";
  }
  document.querySelectorAll(".story-steps li, .progress-dots i").forEach(function (el) {
    var i = Number(el.getAttribute("data-i"));
    el.addEventListener("click", function () { goToStep(i); });
    el.addEventListener("keydown", function (e) {
      if (e.key === "Enter" || e.key === " ") { e.preventDefault(); goToStep(i); }
    });
  });

  /* ───── Voltar ao topo: aparece após o primeiro scroll e mostra o progresso da página ───── */
  var toTop = document.getElementById("to-top");
  if (toTop) {
    var ringFg = toTop.querySelector(".tt-fg");
    var topTick = false;
    var updateTop = function () {
      topTick = false;
      var y = window.pageYOffset;
      var max = Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
      var p = Math.min(1, y / max);
      ringFg.style.strokeDashoffset = String(100 * (1 - p));
      toTop.classList.toggle("show", y > 40);
    };
    window.addEventListener("scroll", function () { if (!topTick) { topTick = true; requestAnimationFrame(updateTop); } }, { passive: true });
    window.addEventListener("resize", updateTop);
    toTop.addEventListener("click", function () { window.scrollTo({ top: 0, behavior: reduced ? "auto" : "smooth" }); });
    updateTop();
  }

  /* ───── Preço do Pro: vem do servidor (que lê do Stripe); se a API não responder, fica o texto do HTML ───── */
  var priceEl = document.querySelector("[data-pro-price]");
  if (priceEl && window.fetch && location.protocol.indexOf("http") === 0) {
    fetch("/api/plans", { headers: { Accept: "application/json" } })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (p) {
        if (p && p.pro && p.pro.amount > 0) {
          priceEl.textContent = (p.pro.amount / 100).toLocaleString("pt-BR", { style: "currency", currency: String(p.pro.currency).toUpperCase() });
        }
      })
      .catch(function () {});
  }
})();
