/*
 * Colmeia com "dedo" (desktop): o ponteiro funciona como um dedo apertando a colmeia.
 * O hexágono sob o ponteiro cresce e os vizinhos são empurrados para longe. Tudo se move por molas
 * (suave, com um leve retorno elástico) e passar de um hexágono para outro é contínuo.
 * Regra de ouro: um hexágono nunca fica em cima do outro. Cada par tem uma distância mínima (a de repouso,
 * mais o quanto cada um cresceu, mais uma folga extra perto do dedo) e uma passada de restrições empurra
 * quem ficar perto demais, tanto nos alvos quanto nas posições reais a cada quadro.
 * No celular a colmeia vira slide (slider.js); com movimento reduzido ou sem mouse nada disso roda.
 */
(function () {
  "use strict";
  if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
  if (!window.matchMedia("(hover: hover)").matches) return;
  var wide = window.matchMedia("(min-width: 621px)");

  var PRESS = 0.11;        // quanto o hexágono sob o dedo cresce
  var PUSH = 0.09;         // empurrão dos vizinhos diretos, em larguras de hexágono
  var REACH = 4.6;         // até onde (em larguras) o empurrão chega; decai devagar para não "afundar" uma fileira na outra
  var EXTRA = 8;           // px de folga a mais, além do espaço de repouso, entre o hexágono apertado e os vizinhos
  var STIFF = 240;         // rigidez da mola
  var DAMP = 0.62;         // 1 = sem retorno elástico; menor = mais elástico (0,62 ≈ 8% de retorno)

  Array.prototype.forEach.call(document.querySelectorAll(".honey"), init);

  function smooth(a, b, x) { var t = Math.min(1, Math.max(0, (x - a) / (b - a))); return t * t * (3 - 2 * t); }

  function init(honey) {
    var cells = Array.prototype.slice.call(honey.querySelectorAll(".hexcell"));
    var n = cells.length;
    if (!n) return;
    var s = cells.map(function () { return { x: 0, y: 0, k: 1, vx: 0, vy: 0, vk: 0 }; });
    var rest = [], restD = [], w = 1, inside = false, px = 0, py = 0, running = false, last = 0;
    var tx = new Array(n), ty = new Array(n), tk = new Array(n), under = new Array(n);
    honey.classList.add("live"); // o CSS deixa de animar translate/scale: quem move agora é a mola

    function measure() {
      var hr = honey.getBoundingClientRect();
      w = cells[0].offsetWidth || 1;
      cells.forEach(function (c, i) {
        var r = c.getBoundingClientRect();
        // o centro "de repouso" não inclui o deslocamento que a mola já aplicou
        rest[i] = { x: r.left + r.width / 2 - hr.left - s[i].x, y: r.top + r.height / 2 - hr.top - s[i].y };
      });
      for (var i = 0; i < n; i++) {
        restD[i] = [];
        for (var j = 0; j < n; j++) restD[i][j] = Math.hypot(rest[i].x - rest[j].x, rest[i].y - rest[j].y);
      }
    }

    /* Distância mínima entre dois hexágonos: a de repouso + o quanto cada um cresceu + folga perto do dedo.
       Pares que ninguém apertou ficam exatamente como em repouso (a colmeia longe do dedo não se espalha). */
    function minDist(i, j, ki, kj) {
      var gi = ki - 1, gj = kj - 1;
      return restD[i][j] + (gi + gj) * w / 2 + EXTRA * Math.max(gi, gj) / PRESS;
    }

    /* Passada de restrições: afasta pares perto demais. Quem está sob o dedo tem "massa infinita" (não se move). */
    function separate(px_, py_, ks, iterations) {
      for (var it = 0; it < iterations; it++) {
        for (var i = 0; i < n; i++) {
          for (var j = i + 1; j < n; j++) {
            if (restD[i][j] > 1.8 * w) continue; // só vizinhos próximos
            var dx = rest[j].x + px_[j] - rest[i].x - px_[i];
            var dy = rest[j].y + py_[j] - rest[i].y - py_[i];
            var d = Math.sqrt(dx * dx + dy * dy) || 0.001;
            var need = minDist(i, j, ks[i], ks[j]);
            if (d >= need) continue;
            var wi = 1 - under[i], wj = 1 - under[j], ws = wi + wj;
            if (ws < 0.001) continue;
            var c = (need - d) / d;
            px_[i] -= dx * c * (wi / ws); py_[i] -= dy * c * (wi / ws);
            px_[j] += dx * c * (wj / ws); py_[j] += dy * c * (wj / ws);
          }
        }
      }
    }

    function frame(t) {
      var dt = Math.min(0.032, (t - last) / 1000 || 0.016);
      last = t;
      var hr = honey.getBoundingClientRect();
      var c2 = 2 * Math.sqrt(STIFF) * DAMP;
      var i;

      // 1) alvos: o dedo empurra para longe; o empurrão sobe até o 1º vizinho e depois decai devagar
      for (i = 0; i < n; i++) {
        tx[i] = 0; ty[i] = 0; tk[i] = 1; under[i] = 0;
        if (!inside) continue;
        var dx = hr.left + rest[i].x - px, dy = hr.top + rest[i].y - py;
        var d = Math.sqrt(dx * dx + dy * dy);
        var u = 1 - smooth(0.34 * w, 0.72 * w, d);                       // 1 = este hexágono está sob o dedo
        var rise = smooth(0.3 * w, 1.0 * w, d);                           // sobe até o 1º vizinho...
        var fall = d <= w ? 1 : Math.max(0, 1 - (d - w) / ((REACH - 1) * w)); // ...e depois decai linearmente, devagar
        var push = PUSH * w * rise * fall * (1 - u);
        if (d > 1) { tx[i] = dx / d * push; ty[i] = dy / d * push; }
        tk[i] = 1 + PRESS * u;
        under[i] = u;
      }
      if (inside) separate(tx, ty, tk, 4); // 2) os alvos já respeitam a distância mínima

      // 3) molas levam as posições reais até os alvos
      var moving = false;
      for (i = 0; i < n; i++) {
        var o = s[i];
        o.vx += ((tx[i] - o.x) * STIFF - o.vx * c2) * dt; o.x += o.vx * dt;
        o.vy += ((ty[i] - o.y) * STIFF - o.vy * c2) * dt; o.y += o.vy * dt;
        o.vk += ((tk[i] - o.k) * STIFF - o.vk * c2) * dt; o.k += o.vk * dt;
      }
      // 4) garantia final: mesmo no meio do movimento (e com o retorno elástico) ninguém se sobrepõe
      var ax = s.map(function (o) { return o.x; }), ay = s.map(function (o) { return o.y; }), ak = s.map(function (o) { return o.k; });
      separate(ax, ay, ak, 2);
      for (i = 0; i < n; i++) {
        var q = s[i];
        q.x = ax[i]; q.y = ay[i];
        var settled = !inside && Math.abs(q.x) < 0.05 && Math.abs(q.y) < 0.05 && Math.abs(q.k - 1) < 0.0005 &&
          Math.abs(q.vx) < 0.05 && Math.abs(q.vy) < 0.05 && Math.abs(q.vk) < 0.001;
        if (settled) { q.x = q.y = q.vx = q.vy = q.vk = 0; q.k = 1; cells[i].style.removeProperty("translate"); cells[i].style.removeProperty("scale"); }
        else { moving = true; cells[i].style.translate = q.x.toFixed(2) + "px " + q.y.toFixed(2) + "px"; cells[i].style.scale = q.k.toFixed(4); }
      }
      if (moving || inside) requestAnimationFrame(frame); else running = false;
    }
    function start() { if (running) return; running = true; last = performance.now(); requestAnimationFrame(frame); }

    honey.addEventListener("pointerenter", function (e) {
      if (!wide.matches) return;
      measure(); px = e.clientX; py = e.clientY; inside = true; start();
    });
    honey.addEventListener("pointermove", function (e) { if (inside) { px = e.clientX; py = e.clientY; } });
    honey.addEventListener("pointerleave", function () { inside = false; start(); });
    window.addEventListener("resize", function () { if (inside) measure(); });
  }
})();
