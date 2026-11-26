/* Vídeo do hero: o mapa mental sendo gerado no app. Só toca com a janela na tela e a aba visível;
   sem o arquivo, com movimento reduzido ou economia de dados, fica a captura parada (poster). */
(function () {
  "use strict";
  var video = document.getElementById("hero-video");
  if (!video) return;
  var conn = navigator.connection || {};
  if (window.matchMedia("(prefers-reduced-motion: reduce)").matches || conn.saveData) return;

  var webm = !!(video.canPlayType && video.canPlayType('video/webm; codecs="vp9"'));
  var loadedTheme = "", visible = false, failed = false;
  /* Celular: no lugar do logo 3D, o hero toca todos os clipes em fila, com uma barra de progresso só */
  var QUEUE = ["mapa-mental", "paleta", "livros", "buscar", "transcricao", "visual", "consumo", "backup"];
  var TITLES = ["Mapa mental", "Busca rápida", "Livros", "Buscar no texto", "Transcrição com tempo", "Seu visual", "Consumo da IA", "Backup"];
  var narrow = window.matchMedia("(max-width: 960px)");
  var idx = 0, prog = document.querySelector(".hero-prog i"), title = document.querySelector(".hero-shot-bar span");
  function queued() { return narrow.matches; }
  function clipName() { return queued() ? QUEUE[idx] : "mapa-mental"; }
  function paintProg() {
    if (!queued() || !prog) return;
    var d = video.duration, k = isFinite(d) && d > 0 ? video.currentTime / d : 0;
    prog.style.width = (((idx + k) / QUEUE.length) * 100) + "%";
  }

  var forceLoad = false;
  function theme() { return document.documentElement.getAttribute("data-theme") === "light" ? "light" : "dark"; }
  function load() {
    var t = theme();
    if ((t === loadedTheme && !forceLoad) || failed) return;
    forceLoad = false;
    loadedTheme = t;
    if (!queued()) video.classList.remove("on");
    video.loop = !queued();
    if (title) title.textContent = queued() ? TITLES[idx] : "HomeLab";
    video.src = "assets/app/video/" + clipName() + "-" + t + (webm ? ".webm" : ".mp4");
    sync();
  }
  function sync() {
    if (failed || !loadedTheme) return;
    if (visible && !document.hidden) {
      var p = video.play();
      if (p && p.catch) p.catch(function () { /* autoplay barrado: fica a captura */ });
    } else video.pause();
  }
  video.addEventListener("timeupdate", paintProg);
  video.addEventListener("ended", function () {
    if (!queued()) return;
    idx = (idx + 1) % QUEUE.length; forceLoad = true; load();
  });
  narrow.addEventListener && narrow.addEventListener("change", function () { idx = 0; forceLoad = true; if (visible) load(); });
  video.addEventListener("playing", function () { video.classList.add("on"); });
  video.addEventListener("error", function () { failed = true; video.classList.remove("on"); });

  if ("IntersectionObserver" in window) {
    new IntersectionObserver(function (es) {
      visible = es[0].isIntersecting;
      if (visible) load();
      sync();
    }, { threshold: 0.25 }).observe(video);
  } else { visible = true; load(); }
  document.addEventListener("visibilitychange", sync);
  new MutationObserver(function () { if (visible) load(); else loadedTheme = ""; })
    .observe(document.documentElement, { attributes: true, attributeFilter: ["data-theme"] });
})();
