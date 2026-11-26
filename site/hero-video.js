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

  function theme() { return document.documentElement.getAttribute("data-theme") === "light" ? "light" : "dark"; }
  function load() {
    var t = theme();
    if (t === loadedTheme || failed) return;
    loadedTheme = t;
    video.classList.remove("on");
    video.src = "assets/app/video/mapa-mental-" + t + (webm ? ".webm" : ".mp4");
    sync();
  }
  function sync() {
    if (failed || !loadedTheme) return;
    if (visible && !document.hidden) {
      var p = video.play();
      if (p && p.catch) p.catch(function () { /* autoplay barrado: fica a captura */ });
    } else video.pause();
  }
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
