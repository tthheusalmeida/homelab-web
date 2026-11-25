/*
 * Colmeia em slide (só no celular, até 620px): cada colmeia (.honey) ganha um carrossel com os mesmos hexágonos.
 * - Os itens passam sozinhos da direita para a esquerda, em loop infinito.
 * - Mouse em cima = pausa. Arrastar move para o lado arrastado; ao soltar, o impulso diminui aos poucos
 *   (desaceleração suave) e volta ao movimento automático.
 * - Embaixo, hexágonos mostram o item atual (grande, ciano) e os vizinhos (médios); tocar num leva até ele.
 * No desktop o CSS esconde o slide e a colmeia original continua como estava.
 */
(function () {
  "use strict";
  var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var AUTO = reduced ? 0 : -26;  // px/s, para a esquerda (os itens vêm da direita)
  var RELAX = 2.2;               // 1/s: quão rápido a velocidade volta ao alvo depois de soltar
  var GAP = 14;                  // px, igual ao gap do .hs-track no CSS

  Array.prototype.forEach.call(document.querySelectorAll(".honey"), build);

  function build(honey) {
    var cells = Array.prototype.slice.call(honey.querySelectorAll(".hexcell"));
    var n = cells.length;
    if (n < 2) return;

    var root = document.createElement("div");
    root.className = "hslider" + (honey.classList.contains("sm") ? " hs-sm" : "");
    root.setAttribute("role", "region");
    root.setAttribute("aria-roledescription", "carrossel");
    root.setAttribute("aria-label", honey.getAttribute("aria-label") || "Itens");
    var viewport = el("div", "hs-viewport");
    var track = el("div", "hs-track");
    var dotsBox = el("div", "hs-dots");
    var dots = [];
    for (var i = 0; i < n; i++) {
      var d = el("button", "hs-dot");
      d.type = "button";
      d.setAttribute("aria-label", "Ir para o item " + (i + 1) + " de " + n);
      d.addEventListener("click", goTo.bind(null, i));
      dotsBox.appendChild(d);
      dots.push(d);
    }
    viewport.appendChild(track);
    root.appendChild(viewport);
    root.appendChild(dotsBox);
    honey.parentNode.insertBefore(root, honey.nextSibling);

    var vw = 0, hw = 0, step = 0, setW = 0, copies = 0;
    var pos = 0, v = AUTO, dragging = false, hover = false, visible = false, running = false;
    var cur = -1, last = 0, lastX = 0, lastT = 0, tween = null, ready = false;

    function el(tag, cls) { var e = document.createElement(tag); e.className = cls; return e; }
    function mod(a, m) { return ((a % m) + m) % m; }

    function fill() {
      track.textContent = "";
      for (var k = 0; k < copies; k++) {
        cells.forEach(function (c) {
          var copy = c.cloneNode(true);
          copy.classList.remove("reveal", "in", "d1", "d2", "d3");
          copy.removeAttribute("style");
          if (k > 0) copy.setAttribute("aria-hidden", "true"); // só a primeira série é lida por leitor de tela
          track.appendChild(copy);
        });
      }
    }

    function layout() {
      if (!root.offsetParent) return false; // escondido (desktop)
      vw = viewport.clientWidth;
      if (!track.firstChild) { copies = 3; fill(); }
      hw = track.firstChild.offsetWidth;
      step = hw + GAP;
      setW = n * step;
      var need = Math.ceil(vw / setW) + 2;
      if (need !== copies) { copies = need; fill(); }
      if (!ready) { pos = (vw - hw) / 2; ready = true; } // começa com o item 1 no centro
      return true;
    }

    function render() {
      var p = mod(pos, setW);
      track.style.transform = "translate3d(" + (p - setW) + "px,0,0)";
      var idx = mod(Math.round((vw / 2 + setW - p - hw / 2) / step), n);
      if (idx !== cur) {
        cur = idx;
        dots.forEach(function (dot, i) {
          var dist = Math.min(mod(i - idx, n), mod(idx - i, n));
          dot.setAttribute("data-d", dist > 2 ? "2" : String(dist));
          if (dist === 0) dot.setAttribute("aria-current", "true"); else dot.removeAttribute("aria-current");
        });
      }
    }

    function goTo(i) {
      if (!layout()) return;
      var p = mod(pos, setW);
      var want = mod((vw - hw) / 2 - i * step, setW);
      var delta = mod(want - p + setW / 2, setW) - setW / 2; // caminho mais curto
      tween = { from: pos, to: pos + delta, t0: performance.now(), dur: reduced ? 1 : 600 };
      start();
    }

    function frame(t) {
      running = false;
      if (!visible || !layout()) return;
      var dt = Math.min(0.05, (t - last) / 1000 || 0);
      last = t;
      if (tween) {
        var k = Math.min(1, (t - tween.t0) / tween.dur);
        pos = tween.from + (tween.to - tween.from) * (1 - Math.pow(1 - k, 3));
        v = 0;
        if (k >= 1) tween = null;
      } else if (!dragging) {
        var target = hover ? 0 : AUTO;
        v += (target - v) * (1 - Math.exp(-RELAX * dt)); // impulso do arrasto diminui até o alvo
        pos += v * dt;
      }
      render();
      // sem movimento (pausado, parado) não precisa de quadros novos
      if (tween || dragging || Math.abs(v) > 0.05 || (!hover && AUTO !== 0)) start();
    }

    function start() {
      if (running || !visible) return;
      running = true;
      last = performance.now();
      requestAnimationFrame(frame);
    }

    viewport.addEventListener("pointerdown", function (e) {
      if (e.pointerType === "mouse" && e.button !== 0) return;
      dragging = true; tween = null; v = 0;
      lastX = e.clientX; lastT = e.timeStamp;
      try { viewport.setPointerCapture(e.pointerId); } catch (err) { /* ignora */ }
      start();
    });
    viewport.addEventListener("pointermove", function (e) {
      if (!dragging) return;
      var dx = e.clientX - lastX, dtm = (e.timeStamp - lastT) / 1000;
      pos += dx; // o conteúdo acompanha o dedo/mouse
      if (dtm > 0) v = v * 0.6 + (dx / dtm) * 0.4;
      lastX = e.clientX; lastT = e.timeStamp;
      if (layout()) render();
    });
    function release(e) {
      if (!dragging) return;
      dragging = false;
      if (e.timeStamp - lastT > 120) v = 0; // parou antes de soltar: sem impulso
      start();
    }
    viewport.addEventListener("pointerup", release);
    viewport.addEventListener("pointercancel", release);
    root.addEventListener("pointerenter", function (e) { if (e.pointerType === "mouse") { hover = true; } });
    root.addEventListener("pointerleave", function (e) { if (e.pointerType === "mouse") { hover = false; start(); } });

    if ("IntersectionObserver" in window) {
      new IntersectionObserver(function (es) { visible = es[0].isIntersecting; if (visible) { if (layout()) render(); start(); } }, { rootMargin: "80px" }).observe(root);
    } else { visible = true; start(); }
    window.addEventListener("resize", function () { if (layout()) render(); start(); });
  }
})();
