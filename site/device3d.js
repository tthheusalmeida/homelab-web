/* Notebook e tablet 3D de verdade (Three.js) para a história de recursos.
   Os modelos são geometria (alumínio, teclas, tela, dobradiça); só a tela usa as capturas do app como textura.
   main.js publica o estado da rolagem em HL.story = { step, open, p } e este arquivo só lê.
   Sem WebGL, a página mantém o notebook em CSS (classe .d3 não é aplicada). */
(function () {
  "use strict";
  var HL = (window.HL = window.HL || { palette: [0x22d3ee], state: { dark: true } });
  var wrap = document.getElementById("laptop-wrap");
  if (!wrap || typeof THREE === "undefined") return;

  var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var phoneMq = window.matchMedia("(max-width: 620px)");

  var host = document.createElement("div");
  host.className = "device3d";
  host.setAttribute("aria-hidden", "true");
  var glow = document.createElement("div");
  glow.className = "ambi3d";
  var canvas = document.createElement("canvas");
  host.appendChild(glow);
  host.appendChild(canvas);

  var renderer;
  try {
    renderer = new THREE.WebGLRenderer({ canvas: canvas, antialias: true, alpha: true });
  } catch (e) {
    return;
  }
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
  renderer.outputEncoding = THREE.sRGBEncoding;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.05;

  var scene = new THREE.Scene();
  var camera = new THREE.PerspectiveCamera(28, 1, 0.1, 60);

  /* ───── Reflexos: uma "sala" com duas caixas de luz, só para o alumínio ter o que refletir ───── */
  (function () {
    var env = new THREE.Scene();
    env.background = new THREE.Color(0x15181d);
    function box(w, h, x, y, z, v) {
      var m = new THREE.Mesh(new THREE.PlaneGeometry(w, h), new THREE.MeshBasicMaterial({ color: new THREE.Color(v, v, v), side: THREE.DoubleSide }));
      m.position.set(x, y, z);
      m.lookAt(0, 0, 0);
      env.add(m);
    }
    box(10, 6, 0, 9, 2, 5);
    box(3, 9, -9, 2, 4, 3.2);
    box(3, 9, 9, 1, -3, 1.6);
    box(14, 2, 0, -2, 10, 1.4);
    var pm = new THREE.PMREMGenerator(renderer);
    scene.environment = pm.fromScene(env, 0.03).texture;
    pm.dispose();
  })();
  var key = new THREE.DirectionalLight(0xffffff, 0.5);
  key.position.set(3, 6, 6);
  scene.add(key);

  /* ───── Materiais ───── */
  var alu = new THREE.MeshStandardMaterial({ color: 0x3a3d43, metalness: 0.9, roughness: 0.34 });
  var aluDark = new THREE.MeshStandardMaterial({ color: 0x24262b, metalness: 0.85, roughness: 0.4 });
  var black = new THREE.MeshStandardMaterial({ color: 0x050506, metalness: 0.1, roughness: 0.18 });
  var keyMat = new THREE.MeshStandardMaterial({ color: 0x0d0e11, metalness: 0.2, roughness: 0.55 });
  var padMat = new THREE.MeshStandardMaterial({ color: 0x2c2f35, metalness: 0.7, roughness: 0.3 });

  function applyTheme() {
    var d = HL.state.dark;
    alu.color.setHex(d ? 0x2b2d32 : 0xd6dade);
    aluDark.color.setHex(d ? 0x1c1e22 : 0xb4bac2);
    padMat.color.setHex(d ? 0x23252a : 0xc4c9d0);
    alu.envMapIntensity = aluDark.envMapIntensity = padMat.envMapIntensity = d ? 0.4 : 1.15;
    useTheme(d ? "dark" : "light");
  }

  /* ───── Geometrias ───── */
  function rrPath(s, w, h, r) {
    var x = -w / 2, y = -h / 2;
    s.moveTo(x + r, y);
    s.lineTo(x + w - r, y); s.quadraticCurveTo(x + w, y, x + w, y + r);
    s.lineTo(x + w, y + h - r); s.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
    s.lineTo(x + r, y + h); s.quadraticCurveTo(x, y + h, x, y + h - r);
    s.lineTo(x, y + r); s.quadraticCurveTo(x, y, x + r, y);
    return s;
  }
  /* placa de cantos arredondados e chanfrados, centrada na origem; a espessura vai no eixo z */
  function slab(w, h, d, r, b) {
    var s = rrPath(new THREE.Shape(), w - 2 * b, h - 2 * b, Math.max(0.001, r - b));
    var g = new THREE.ExtrudeGeometry(s, { depth: d - 2 * b, bevelEnabled: true, bevelThickness: b, bevelSize: b, bevelSegments: 4, curveSegments: 12 });
    g.translate(0, 0, -(d - 2 * b) / 2);
    return g;
  }
  function plate(w, h, r) { return new THREE.ShapeGeometry(rrPath(new THREE.Shape(), w, h, r), 10); }
  function mesh(g, m, x, y, z) { var o = new THREE.Mesh(g, m); o.position.set(x || 0, y || 0, z || 0); return o; }

  /* ───── Tela: seis capturas empilhadas, a atual fica opaca e as outras somem (crossfade) ───── */
  var NAMES = ["biblioteca", "nova-tarefa", "mapa-mental", "infografico", "leitura", "paleta"];
  var SW = 3.1, SH = SW * 820 / 1360;
  var loader = new THREE.TextureLoader();
  var texCache = { dark: [], light: [] };
  var shotMats = NAMES.map(function (n, i) {
    var m = new THREE.MeshBasicMaterial({ transparent: true, opacity: i === 0 ? 1 : 0, depthWrite: false, toneMapped: false });
    return m;
  });
  var maxAniso = renderer.capabilities.getMaxAnisotropy();
  function loadSet(theme, then) {
    NAMES.forEach(function (n, i) {
      if (texCache[theme][i]) return;
      loader.load("assets/app/" + n + "-" + theme + "-1040.png", function (t) {
        t.encoding = THREE.sRGBEncoding;
        t.anisotropy = maxAniso;
        texCache[theme][i] = t;
        if (HL.state.dark === (theme === "dark")) { setMap(i); dirty = true; }
        if (then && !wrap.classList.contains("d3")) then();
      });
    });
  }
  function useTheme(theme) {
    shotMats.forEach(function (m, i) { setMap(i); });
    loadSet(theme, showWhenReady);
    syncClips();
    dirty = true;
  }

  /* ───── Clipes opcionais: um vídeo curto por recurso, no lugar da captura parada ─────
     Um passo só tem clipe se o <li> dele em .story-steps tiver data-video="nome". Os arquivos ficam em
     assets/app/video/<nome>-<tema>.webm|mp4. O vídeo só é pedido quando o passo aparece e a seção está na tela;
     enquanto carrega (ou se falhar, ou com movimento reduzido / economia de dados), fica a captura parada. */
  var stepEls = document.querySelectorAll("[data-story] .story-steps li");
  var clipOf = NAMES.map(function (n, i) { return (stepEls[i] && stepEls[i].getAttribute("data-video")) || ""; });
  var conn = navigator.connection || {};
  var canClips = !reduced && !conn.saveData && clipOf.some(Boolean);
  var preferWebm = (function () {
    var v = document.createElement("video");
    return !!(v.canPlayType && v.canPlayType('video/webm; codecs="vp9"'));
  })();
  var clips = [];
  function themeName() { return HL.state.dark ? "dark" : "light"; }
  function clipPlaying(i) { var c = clips[i]; return !!(c && c.ready && !c.failed && i === curStep && onScreen && !document.hidden); }
  /* a textura do passo: o vídeo enquanto toca; senão, a captura do tema atual */
  function setMap(i) {
    var c = clips[i];
    var tex = clipPlaying(i) ? c.tex : texCache[themeName()][i];
    if (tex && shotMats[i].map !== tex) { shotMats[i].map = tex; shotMats[i].needsUpdate = true; dirty = true; }
  }
  function dropClip(i) {
    var c = clips[i];
    if (!c) return;
    c.video.pause();
    c.video.removeAttribute("src");
    c.video.load();
    if (c.tex) c.tex.dispose();
    clips[i] = null;
  }
  function makeClip(i, theme) {
    var video = document.createElement("video");
    video.muted = true; video.loop = true; video.playsInline = true;
    video.setAttribute("playsinline", ""); video.preload = "auto";
    var c = { video: video, theme: theme, ready: false, failed: false, tex: null };
    video.addEventListener("loadeddata", function () {
      c.tex = new THREE.VideoTexture(video);
      c.tex.encoding = THREE.sRGBEncoding;
      c.tex.generateMipmaps = false;
      c.ready = true;
      setMap(i);
    });
    video.addEventListener("error", function () { c.failed = true; setMap(i); });
    video.src = "assets/app/video/" + clipOf[i] + "-" + theme + (preferWebm ? ".webm" : ".mp4");
    return c;
  }
  /* liga/pausa os vídeos conforme o passo atual, a seção na tela e a aba visível */
  function syncClips() {
    if (!canClips) return;
    clipOf.forEach(function (name, i) {
      if (!name) return;
      var c = clips[i];
      if (c && c.theme !== themeName()) { dropClip(i); c = null; }
      var want = onScreen && !document.hidden && i === curStep;
      if (want && !c) c = clips[i] = makeClip(i, themeName());
      if (!c || c.failed) return;
      if (want) {
        if (c.video.paused) { var p = c.video.play(); if (p && p.catch) p.catch(function () { /* autoplay barrado: fica a captura */ }); }
      } else if (!c.video.paused) {
        c.video.pause();
        if (i !== curStep) c.video.currentTime = 0; // ao voltar, o clipe recomeça do início
      }
      setMap(i);
    });
  }
  document.addEventListener("visibilitychange", syncClips);

  var screenGeo = new THREE.PlaneGeometry(SW, SH);
  function makeScreen() {
    var g = new THREE.Group();
    shotMats.forEach(function (m, i) {
      var p = new THREE.Mesh(screenGeo, m);
      p.renderOrder = i + 1;
      g.add(p);
    });
    /* reflexo suave na tela */
    var c = document.createElement("canvas");
    c.width = 64; c.height = 64;
    var x = c.getContext("2d"), gr = x.createLinearGradient(0, 0, 64, 64);
    gr.addColorStop(0, "rgba(255,255,255,0.16)"); gr.addColorStop(0.42, "rgba(255,255,255,0)");
    x.fillStyle = gr; x.fillRect(0, 0, 64, 64);
    var gl = new THREE.Mesh(screenGeo, new THREE.MeshBasicMaterial({ map: new THREE.CanvasTexture(c), transparent: true, depthWrite: false, toneMapped: false }));
    gl.renderOrder = 20; gl.position.z = 0.002;
    g.add(gl);
    return g;
  }

  /* ───── Logo do app nas costas da tampa (três hexágonos) ───── */
  function makeLogo() {
    var g = new THREE.Group();
    var light = new THREE.MeshStandardMaterial({ color: 0xe0f2fe, emissive: 0xe0f2fe, emissiveIntensity: 0.25, roughness: 0.5 });
    var cyan = new THREE.MeshStandardMaterial({ color: 0x22d3ee, emissive: 0x22d3ee, emissiveIntensity: 0.3, roughness: 0.5 });
    var hx = new THREE.CylinderGeometry(0.95, 0.95, 0.5, 6);
    hx.rotateX(Math.PI / 2);
    [[0, 1.12, light], [-0.97, -0.56, cyan], [0.97, -0.56, light]].forEach(function (p) { g.add(mesh(hx, p[2], p[0], p[1], 0)); });
    g.scale.setScalar(0.17);
    g.scale.z = 0.05;
    return g;
  }

  /* ───── Notebook ───── */
  var LW = 3.3, LD = 2.2, BASE_H = 0.11, LID_H = 2.15, LID_T = 0.07;
  var laptop = new THREE.Group();
  var lidPivot = new THREE.Group();
  var keyGlow, keyCount = 0, keyInt = [];
  (function () {
    var base = mesh(slab(LW, LD, BASE_H, 0.14, 0.03), alu, 0, 0, 0);
    base.rotation.x = -Math.PI / 2;
    base.position.y = BASE_H / 2;
    laptop.add(base);
    var top = BASE_H;

    /* teclas (instanciadas) e a camada de brilho de cada uma */
    var layout = [
      [1.4, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1],
      [1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1.6],
      [1.5, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1.1],
      [1.8, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 2.1],
      [2.3, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 2.6],
      [1, 1, 1, 1.3, 5.6, 1.3, 1, 1, 1, 1]
    ];
    var kw = 3.0, gap = 0.035, z = -0.93, items = [];
    layout.forEach(function (row, ri) {
      var kh = ri === 0 ? 0.095 : 0.15;
      var sum = row.reduce(function (a, b) { return a + b; }, 0);
      var unit = (kw - gap * (row.length - 1)) / sum, x = -kw / 2;
      row.forEach(function (w) {
        var wd = unit * w;
        items.push({ x: x + wd / 2, z: z + kh / 2, w: wd, h: kh });
        x += wd + gap;
      });
      z += kh + gap;
    });
    keyCount = items.length;
    var keys = new THREE.InstancedMesh(new THREE.BoxGeometry(1, 1, 1), keyMat, keyCount);
    var glowPlane = new THREE.PlaneGeometry(1, 1);
    glowPlane.rotateX(-Math.PI / 2);
    keyGlow = new THREE.InstancedMesh(glowPlane, new THREE.MeshBasicMaterial({ blending: THREE.AdditiveBlending, transparent: true, depthWrite: false, toneMapped: false }), keyCount);
    var m = new THREE.Matrix4(), q = new THREE.Quaternion(), p = new THREE.Vector3(), s = new THREE.Vector3(), c0 = new THREE.Color(0, 0, 0);
    items.forEach(function (it, i) {
      m.compose(p.set(it.x, top + 0.014, it.z), q, s.set(it.w, 0.028, it.h));
      keys.setMatrixAt(i, m);
      m.compose(p.set(it.x, top + 0.0295, it.z), q, s.set(it.w * 0.86, 1, it.h * 0.78));
      keyGlow.setMatrixAt(i, m);
      keyGlow.setColorAt(i, c0);
      keyInt.push(0);
    });
    keyGlow.renderOrder = 5;
    laptop.add(keys, keyGlow);

    /* trackpad levemente rebaixado */
    var pad = mesh(plate(1.25, 0.72, 0.06), padMat, 0, top + 0.002, 0.66);
    pad.rotation.x = -Math.PI / 2;
    laptop.add(pad);

    /* dobradiça */
    var hinge = mesh(new THREE.CylinderGeometry(0.045, 0.045, 2.7, 20), aluDark, 0, top + 0.02, -LD / 2 + 0.07);
    hinge.rotation.z = Math.PI / 2;
    laptop.add(hinge);

    /* tampa: gira em torno da dobradiça; aberta ~105°, fechada deitada sobre o teclado */
    lidPivot.position.set(0, top + 0.02, -LD / 2 + 0.07);
    var lid = mesh(slab(LW, LID_H, LID_T, 0.14, 0.025), alu, 0, LID_H / 2 + 0.02, -LID_T / 2);
    var glass = mesh(plate(LW - 0.08, LID_H - 0.08, 0.07), black, 0, LID_H / 2 + 0.02, 0.0012);
    var screen = makeScreen();
    screen.position.set(0, LID_H / 2 + 0.02 - 0.012, 0.004);
    var cam = mesh(new THREE.CircleGeometry(0.028, 16), new THREE.MeshBasicMaterial({ color: 0x16233a }), 0, LID_H - 0.07, 0.006);
    var logo = makeLogo();
    logo.rotation.y = Math.PI;
    logo.position.set(0, LID_H / 2 + 0.02, -LID_T - 0.004);
    lidPivot.add(lid, glass, screen, cam, logo);
    laptop.add(lidPivot);
  })();
  scene.add(laptop);

  /* ───── Tablet ───── */
  var tablet = new THREE.Group();
  (function () {
    var TW = SW + 0.3, TH = SH + 0.3;
    var body = mesh(slab(TW, TH, 0.1, 0.2, 0.03), alu, 0, 0, -0.05);
    var glass = mesh(plate(TW - 0.07, TH - 0.07, 0.17), black, 0, 0, 0.0012);
    var screen = makeScreen();
    screen.position.z = 0.004;
    var cam = mesh(new THREE.CircleGeometry(0.03, 16), new THREE.MeshBasicMaterial({ color: 0x16233a }), 0, TH / 2 - 0.075, 0.006);
    var back = makeLogo();
    back.rotation.y = Math.PI;
    back.position.set(0, 0, -0.103);
    tablet.add(body, glass, screen, cam, back);
  })();
  tablet.visible = false;
  scene.add(tablet);

  /* sombra de contato (mancha suave) sob o notebook */
  var shadow = (function () {
    var c = document.createElement("canvas");
    c.width = c.height = 128;
    var x = c.getContext("2d"), g = x.createRadialGradient(64, 64, 4, 64, 64, 62);
    g.addColorStop(0, "rgba(0,0,0,0.55)"); g.addColorStop(1, "rgba(0,0,0,0)");
    x.fillStyle = g; x.fillRect(0, 0, 128, 128);
    var m = new THREE.Mesh(new THREE.PlaneGeometry(1, 1), new THREE.MeshBasicMaterial({ map: new THREE.CanvasTexture(c), transparent: true, depthWrite: false, toneMapped: false }));
    m.rotation.x = -Math.PI / 2;
    m.position.y = -0.004;
    m.scale.set(5.2, 3.6, 1);
    return m;
  })();
  scene.add(shadow);

  /* ───── Câmera: enquadra o modelo atual qualquer que seja a proporção da área ───── */
  var phone = phoneMq.matches;
  function resize() {
    var w = host.clientWidth || 600, h = host.clientHeight || 460;
    renderer.setSize(w, h, false);
    camera.aspect = w / h;
    camera.updateProjectionMatrix();
    dirty = true;
  }
  function frameCamera() {
    var t = Math.tan(THREE.MathUtils.degToRad(camera.fov / 2));
    var halfW = phone ? 1.72 : 2.35, halfH = phone ? 1.2 : 1.75;
    var dist = Math.max(halfH / t, halfW / (t * camera.aspect));
    var el = phone ? 0.1 : 0.3;
    var ty = phone ? 0 : 0.85;
    camera.position.set(0, ty + Math.sin(el) * dist, Math.cos(el) * dist);
    camera.lookAt(0, ty, 0);
  }

  /* ───── Estado por quadro ───── */
  var dirty = true, lastPulse = 0, curStep = -1;
  var pal = HL.palette.map(function (c) { return new THREE.Color(c); });
  var tmpC = new THREE.Color(), amb = new THREE.Color(0x22d3ee);
  var smoothOpen = 0, onScreen = true, running = false;
  var CLOSED = Math.PI / 2 - 0.02, OPEN = -0.2;
  var REST_Y = -0.32;
  var mx = 0, my = 0, tmx = 0, tmy = 0, lastT = 0;
  if (!reduced) window.addEventListener("pointermove", function (e) {
    if (e.pointerType === "touch") return;
    /* conta o MOVIMENTO do mouse, não onde ele está: parado em qualquer lado, o modelo volta à posição de repouso */
    tmx = Math.max(-1, Math.min(1, tmx + (e.movementX || 0) * 0.004));
    tmy = Math.max(-1, Math.min(1, tmy + (e.movementY || 0) * 0.004));
  }, { passive: true });

  function pulseKeys() {
    for (var n = 0; n < 4; n++) keyInt[Math.floor(Math.random() * keyCount)] = 1;
  }

  function update(now) {
    var st = HL.story || { step: 0, open: 1, p: 0 };
    if (st.step !== curStep) {
      curStep = st.step;
      syncClips();
      amb.copy(pal[Math.max(0, curStep) % pal.length]);
      pulseKeys();
      host.style.setProperty("--amb", "#" + amb.getHexString());
    }
    shotMats.forEach(function (m, i) {
      var target = i === curStep ? 1 : 0;
      m.opacity += (target - m.opacity) * (reduced ? 1 : 0.14);
      if (Math.abs(target - m.opacity) < 0.003) m.opacity = target;
      m.visible = m.opacity > 0.003;
    });
    var o = reduced ? st.open : st.open;
    host.style.setProperty("--open", o.toFixed(3));

    /* Parado numa posição em ângulo (a profundidade fica clara); só se mexe quando o mouse mexe */
    // o impulso do mouse some aos poucos (por tempo, não por quadro: igual em qualquer taxa de quadros)
    var dt = Math.min(0.1, Math.max(0, (now - lastT) / 1000));
    lastT = now;
    var decay = Math.pow(0.08, dt), follow = 1 - Math.pow(0.0067, dt);
    tmx *= decay; tmy *= decay;
    mx += (tmx - mx) * follow;
    my += (tmy - my) * follow;
    var ry = REST_Y + mx * 0.3;
    var rx = my * 0.08;
    if (phone) {
      laptop.visible = false;
      tablet.visible = true;
      tablet.rotation.set(rx - (1 - o) * 0.9, ry * 0.9, 0);
      tablet.scale.setScalar(0.82 + 0.18 * o);
      shadow.visible = false;
    } else {
      tablet.visible = false;
      laptop.visible = true;
      shadow.visible = true;
      laptop.rotation.set(rx, ry, 0);
      lidPivot.rotation.x = CLOSED + (OPEN - CLOSED) * o;
      shadow.material.opacity = 0.4 + 0.6 * o;
    }

    /* teclas acesas: o brilho decai a cada quadro e a cor segue o recurso atual */
    if (!phone) {
      if (o > 0.95 && !reduced && now - lastPulse > 700) { lastPulse = now; pulseKeys(); }
      for (var i = 0; i < keyCount; i++) {
        var v = keyInt[i] = keyInt[i] * 0.94;
        keyGlow.setColorAt(i, tmpC.copy(amb).multiplyScalar(v > 0.02 ? v * 0.6 : 0));
      }
      keyGlow.instanceColor.needsUpdate = true;
    }
    frameCamera();
    renderer.render(scene, camera);
  }

  function loop(now) {
    if (!onScreen) { running = false; return; }
    update(now);
    requestAnimationFrame(loop);
  }
  function kick() {
    if (running || !onScreen) return;
    running = true;
    requestAnimationFrame(loop);
  }

  /* ───── Liga tudo ───── */
  function showWhenReady() {
    if (wrap.classList.contains("d3")) return;
    wrap.classList.add("d3");
    resize();
  }
  wrap.insertBefore(host, wrap.firstChild);
  window.addEventListener("resize", resize);
  var onPhone = function () { phone = phoneMq.matches; resize(); };
  if (phoneMq.addEventListener) phoneMq.addEventListener("change", onPhone); else phoneMq.addListener(onPhone);
  if ("IntersectionObserver" in window) {
    new IntersectionObserver(function (es) { onScreen = es[0].isIntersecting; syncClips(); if (onScreen) kick(); }, { rootMargin: "120px" }).observe(host);
  }
  var prevApply = HL.applyTheme;
  HL.applyTheme = function () { if (prevApply) prevApply(); applyTheme(); };
  applyTheme();
  resize();
  kick();
})();
