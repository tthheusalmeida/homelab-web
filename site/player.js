/* Vídeos da landing em tela maior.
   - Qualquer [data-clip] ganha um botão (hexágono) que aparece no hover e abre o modal.
   - Os cartões de "E tem mais" tocam uma prévia no hover e abrem o modal no clique.
   - O notebook 3D tem o botão também: abre o clipe do passo atual (ou a captura, se o passo não tem clipe).
   Arquivos: assets/app/video/<nome>-<tema>[-hd].webm|mp4 e <nome>-<tema>.jpg (poster). */
(function () {
  "use strict";
  var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var conn = navigator.connection || {};
  var finePointer = window.matchMedia("(hover: hover) and (pointer: fine)").matches;
  var probe = document.createElement("video");
  var webm = !!(probe.canPlayType && probe.canPlayType('video/webm; codecs="vp9"'));

  var LIST = [
    { id: "mapa-mental", title: "Mapa mental", desc: "A IA monta o mapa do resumo em colmeia. Edite, guarde e baixe em PNG.", where: "Página › Mapa mental" },
    { id: "paleta", title: "Busca rápida", desc: "Ctrl K abre a busca em qualquer tela. Navegue só com o teclado.", where: "Ctrl K" },
    { id: "livros", title: "Livros", desc: "Agrupe páginas com sumário, busca e progresso de leitura.", where: "Biblioteca › Livros" },
    { id: "buscar", title: "Buscar no texto", desc: "Ctrl F acha a frase inteira e destaca cada ocorrência.", where: "Páginas › Ctrl F" },
    { id: "transcricao", title: "Transcrição com tempo", desc: "Marcas de tempo e linha do tempo que abrem o vídeo no ponto certo.", where: "Página › Transcrição" },
    { id: "visual", title: "Seu visual", desc: "Tema claro ou escuro e a cor de destaque que você escolher.", where: "Configurações › Aparência" },
    { id: "consumo", title: "Consumo da IA", desc: "Acompanhe requisições e custo, sem surpresa na fatura.", where: "IA › Consumo" },
    { id: "backup", title: "Backup em um clique", desc: "Um único .zip com o que você escolher levar.", where: "Configurações › Backup" }
  ];
  var STEP_STILL = ["biblioteca", "nova-tarefa", "mapa-mental", "infografico", "leitura", "paleta"];
  function byId(id) { for (var i = 0; i < LIST.length; i++) if (LIST[i].id === id) return LIST[i]; return null; }
  function theme() { return document.documentElement.getAttribute("data-theme") === "light" ? "light" : "dark"; }
  function clipUrl(id, hd) { return "assets/app/video/" + id + "-" + theme() + (hd ? "-hd" : "") + (webm ? ".webm" : ".mp4"); }

  var ICON = {
    expand: '<svg viewBox="0 0 24 24"><path d="M14 4h6v6M10 20H4v-6M20 4l-7 7M4 20l7-7"/></svg>',
    play: '<svg viewBox="0 0 24 24"><path d="M8 5.5v13l11-6.5z"/></svg>',
    pause: '<svg viewBox="0 0 24 24"><path d="M7 5h3.5v14H7zM13.5 5H17v14h-3.5z"/></svg>',
    close: '<svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg>',
    full: '<svg viewBox="0 0 24 24"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg>',
    loop: '<svg viewBox="0 0 24 24"><path d="M17 2l3 3-3 3M4 11V9a4 4 0 0 1 4-4h12M7 22l-3-3 3-3M20 13v2a4 4 0 0 1-4 4H4"/></svg>',
    prev: '<svg viewBox="0 0 24 24"><path d="m15 5-7 7 7 7"/></svg>',
    next: '<svg viewBox="0 0 24 24"><path d="m9 5 7 7-7 7"/></svg>'
  };

  /* ───────── Botão de ampliar ───────── */
  function addMax(container, open, label) {
    var b = document.createElement("button");
    b.type = "button";
    b.className = "max-btn";
    b.setAttribute("aria-label", label || "Ampliar");
    b.innerHTML = ICON.expand;
    b.addEventListener("click", function (e) { e.stopPropagation(); open(b); });
    container.appendChild(b);
    return b;
  }

  /* ───────── Modal ───────── */
  var dlg, el = {}, vid = null, rafId = 0, lastTrigger = null, current = null, speeds = [1, 1.5, 2, 0.5], speedIdx = 0, loop = true, dragging = false;

  function build() {
    if (dlg) return;
    dlg = document.createElement("dialog");
    dlg.className = "vmodal";
    dlg.setAttribute("aria-label", "Vídeo ampliado");
    dlg.innerHTML =
      '<div class="vm-shell">' +
      '<header class="vm-head"><div class="vm-titles"><h3 class="vm-title"></h3><p class="vm-desc"></p></div><span class="where vm-where"></span><button class="vm-btn vm-close" type="button" aria-label="Fechar">' + ICON.close + "</button></header>" +
      '<div class="vm-stage"><div class="vm-media"></div><button class="vm-center" type="button" aria-label="Reproduzir ou pausar"></button></div>' +
      '<div class="vm-ctrl">' +
      '<button class="vm-btn vm-play" type="button" aria-label="Reproduzir ou pausar">' + ICON.play + "</button>" +
      '<span class="vm-time">0:00 / 0:00</span>' +
      '<div class="vbar" role="slider" tabindex="0" aria-label="Posição do vídeo" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><i class="vb-track"></i><i class="vb-buf"></i><i class="vb-played"></i><b class="vb-thumb"></b></div>' +
      '<button class="vm-btn vm-speed" type="button" aria-label="Velocidade">1×</button>' +
      '<button class="vm-btn vm-loop" type="button" aria-pressed="true" aria-label="Repetir">' + ICON.loop + "</button>" +
      '<button class="vm-btn vm-full" type="button" aria-label="Tela cheia">' + ICON.full + "</button>" +
      "</div>" +
      '<nav class="vm-list" aria-label="Outros vídeos"></nav>' +
      "</div>";
    document.body.appendChild(dlg);
    ["shell", "title", "desc", "where", "close", "stage", "media", "center", "play", "time", "bar", "speed", "loop", "full", "list"].forEach(function (k) {
      el[k] = dlg.querySelector(k === "bar" ? ".vbar" : ".vm-" + k);
    });
    el.buf = dlg.querySelector(".vb-buf"); el.played = dlg.querySelector(".vb-played"); el.thumb = dlg.querySelector(".vb-thumb");

    el.close.addEventListener("click", function () { dlg.close(); });
    dlg.addEventListener("click", function (e) { if (e.target === dlg) dlg.close(); });
    dlg.addEventListener("close", onClose);
    el.play.addEventListener("click", toggle);
    el.center.addEventListener("click", toggle);
    el.speed.addEventListener("click", function () {
      speedIdx = (speedIdx + 1) % speeds.length;
      el.speed.textContent = String(speeds[speedIdx]).replace(".", ",") + "×";
      if (vid) vid.playbackRate = speeds[speedIdx];
    });
    el.loop.addEventListener("click", function () {
      loop = !loop;
      el.loop.setAttribute("aria-pressed", String(loop));
      if (vid) vid.loop = loop;
    });
    el.full.addEventListener("click", function () {
      var d = document;
      if (d.fullscreenElement) d.exitFullscreen(); else if (el.shell.requestFullscreen) el.shell.requestFullscreen();
    });

    /* barra de progresso: arrastar ou clicar leva o vídeo até o ponto */
    function seekAt(e) {
      if (!vid || !isFinite(vid.duration)) return;
      var r = el.bar.getBoundingClientRect();
      var k = Math.max(0, Math.min(1, (e.clientX - r.left) / r.width));
      vid.currentTime = k * vid.duration;
      paint();
    }
    el.bar.addEventListener("pointerdown", function (e) { dragging = true; el.bar.setPointerCapture(e.pointerId); seekAt(e); });
    el.bar.addEventListener("pointermove", function (e) { if (dragging) seekAt(e); });
    el.bar.addEventListener("pointerup", function () { dragging = false; });
    el.bar.addEventListener("pointercancel", function () { dragging = false; });

    dlg.addEventListener("keydown", function (e) {
      if (e.key === " " && e.target.tagName !== "BUTTON") { e.preventDefault(); toggle(); }
      else if (e.key === "ArrowLeft" || e.key === "ArrowRight") {
        if (vid && isFinite(vid.duration)) { e.preventDefault(); vid.currentTime = Math.max(0, Math.min(vid.duration, vid.currentTime + (e.key === "ArrowLeft" ? -2 : 2))); paint(); }
      } else if (e.key === "f" || e.key === "F") el.full.click();
    });
  }

  function fmt(t) { t = Math.max(0, Math.floor(t || 0)); return Math.floor(t / 60) + ":" + String(t % 60).padStart(2, "0"); }
  function paint() {
    if (!vid) return;
    var d = vid.duration, t = vid.currentTime;
    var k = isFinite(d) && d > 0 ? t / d : 0;
    var b = 0;
    try { if (vid.buffered.length) b = vid.buffered.end(vid.buffered.length - 1) / d; } catch (e) { /* sem buffer */ }
    el.played.style.width = (k * 100) + "%";
    el.thumb.style.left = (k * 100) + "%";
    el.buf.style.width = (Math.min(1, b || 0) * 100) + "%";
    el.bar.setAttribute("aria-valuenow", String(Math.round(k * 100)));
    el.time.textContent = fmt(t) + " / " + fmt(isFinite(d) ? d : 0);
  }
  function tick() { paint(); rafId = vid && !vid.paused ? requestAnimationFrame(tick) : 0; }
  function syncPlayIcon() {
    var playing = vid && !vid.paused;
    el.play.innerHTML = playing ? ICON.pause : ICON.play;
    el.stage.classList.toggle("is-paused", !playing);
    if (playing && !rafId) rafId = requestAnimationFrame(tick);
  }
  function toggle() {
    if (!vid) return;
    if (vid.paused) { var p = vid.play(); if (p && p.catch) p.catch(function () {}); } else vid.pause();
  }

  function setMeta(title, desc, where) {
    el.title.textContent = title; el.desc.textContent = desc; el.where.textContent = where || "";
    el.where.style.display = where ? "" : "none";
  }
  function renderList(activeId) {
    el.list.textContent = "";
    el.list.hidden = !activeId;
    if (!activeId) return;
    LIST.forEach(function (c) {
      var b = document.createElement("button");
      b.type = "button";
      b.className = "vm-chip" + (c.id === activeId ? " on" : "");
      b.textContent = c.title;
      b.addEventListener("click", function () { show(c.id); });
      el.list.appendChild(b);
    });
  }
  function clearMedia() {
    if (vid) { vid.pause(); vid.removeAttribute("src"); vid.load(); }
    vid = null;
    if (rafId) cancelAnimationFrame(rafId);
    rafId = 0;
    el.media.textContent = "";
  }
  function setCtrlEnabled(on) { dlg.classList.toggle("is-still", !on); }

  /* mostra um clipe: tenta o arquivo HD, depois o leve; se faltar, mostra a captura parada */
  function show(id) {
    var c = byId(id);
    if (!c) return;
    current = id;
    clearMedia();
    setMeta(c.title, c.desc, c.where);
    renderList(id);
    setCtrlEnabled(true);
    var v = document.createElement("video");
    v.className = "vm-video"; v.muted = true; v.loop = loop; v.playsInline = true; v.preload = "auto";
    v.setAttribute("playsinline", "");
    v.poster = "assets/app/video/" + id + "-" + theme() + ".jpg";
    var triedLight = false;
    v.addEventListener("error", function () {
      if (!triedLight) { triedLight = true; v.src = clipUrl(id, false); return; }
      showStill("assets/app/" + id + "-" + theme() + ".png", "Este vídeo ainda não está disponível.");
    });
    ["timeupdate", "progress", "loadedmetadata", "durationchange", "seeked"].forEach(function (n) { v.addEventListener(n, paint); });
    v.addEventListener("play", syncPlayIcon); v.addEventListener("pause", syncPlayIcon);
    v.addEventListener("loadeddata", function () { var p = v.play(); if (p && p.catch) p.catch(function () {}); });
    v.playbackRate = speeds[speedIdx];
    v.src = clipUrl(id, true);
    el.media.appendChild(v);
    vid = v;
    paint(); syncPlayIcon();
  }
  function showStill(src, note) {
    if (vid) { vid.pause(); }
    vid = null;
    el.media.textContent = "";
    var im = document.createElement("img");
    im.className = "vm-img"; im.src = src; im.alt = el.title.textContent;
    el.media.appendChild(im);
    if (note) { var n = document.createElement("p"); n.className = "vm-note"; n.textContent = note; el.media.appendChild(n); }
    setCtrlEnabled(false);
    el.time.textContent = "";
  }
  function showStillFor(name, title, desc) {
    clearMedia();
    setMeta(title, desc, "");
    renderList(null);
    showStill("assets/app/" + name + "-" + theme() + ".png", "");
  }

  function openModal(trigger) {
    build();
    lastTrigger = trigger || null;
    if (!dlg.open) dlg.showModal();
  }
  function onClose() {
    clearMedia();
    current = null;
    if (document.fullscreenElement) document.exitFullscreen();
    if (lastTrigger && lastTrigger.focus) lastTrigger.focus();
  }
  function openClip(id, trigger) { openModal(trigger); show(id); }

  /* ───────── Ligações ───────── */
  // hero e outros blocos com data-clip (o cartão tem a própria ligação abaixo)
  Array.prototype.forEach.call(document.querySelectorAll("[data-clip]:not(.clip-card)"), function (host) {
    var id = host.getAttribute("data-clip");
    addMax(host, function (b) { openClip(id, b); }, "Ampliar o vídeo: " + (byId(id) ? byId(id).title : id));
  });

  // cartões de "E tem mais": prévia no hover, modal no clique
  var previews = [];
  Array.prototype.forEach.call(document.querySelectorAll(".clip-card"), function (card) {
    var id = card.getAttribute("data-clip");
    var media = card.querySelector(".clip-media");
    var thumb = card.querySelector(".clip-thumb");
    var video = card.querySelector("video");
    addMax(media, function (b) { openClip(id, b); }, "Ampliar o vídeo: " + (byId(id) ? byId(id).title : id));
    thumb.addEventListener("click", function () { openClip(id, thumb); });

    var failed = false;
    function start() {
      if (reduced || conn.saveData || failed) return;
      if (!video.getAttribute("src")) {
        video.src = clipUrl(id, false);
        video.addEventListener("error", function () { failed = true; video.classList.remove("on"); });
        video.addEventListener("playing", function () { video.classList.add("on"); });
      }
      var p = video.play(); if (p && p.catch) p.catch(function () {});
    }
    function stop() { video.pause(); video.classList.remove("on"); try { video.currentTime = 0; } catch (e) { /* ainda sem dados */ } }
    if (finePointer) {
      media.addEventListener("pointerenter", start);
      media.addEventListener("pointerleave", stop);
    }
    media.addEventListener("focusin", start);
    media.addEventListener("focusout", stop);
    previews.push({ video: video });
  });
  // trocou o tema: as prévias carregam de novo no tema certo
  new MutationObserver(function () {
    previews.forEach(function (p) { p.video.pause(); p.video.classList.remove("on"); p.video.removeAttribute("src"); });
  }).observe(document.documentElement, { attributes: true, attributeFilter: ["data-theme"] });

  // notebook 3D: ampliar o passo atual
  var wrap = document.getElementById("laptop-wrap");
  if (wrap) {
    var steps = document.querySelectorAll("[data-story] .story-steps li");
    addMax(wrap, function (b) {
      var i = (window.HL && HL.story && HL.story.step >= 0) ? HL.story.step : 0;
      var name = STEP_STILL[i] || STEP_STILL[0];
      var li = steps[i];
      if (byId(name) && li && li.getAttribute("data-video")) openClip(name, b);
      else {
        openModal(b);
        showStillFor(name, li ? li.querySelector("h3").textContent : name, li ? li.querySelector("p").textContent : "");
      }
    }, "Ampliar a tela do app");
    wrap.classList.add("has-max");
  }
})();
