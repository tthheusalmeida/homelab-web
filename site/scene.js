/* Cena 3D de fundo: o logo do HomeLab (três hexágonos) sobre um campo de colmeia animado. */
(function () {
  "use strict";

  /* Estado compartilhado com main.js (existe mesmo sem WebGL) */
  var HL = (window.HL = window.HL || {});
  HL.palette = [0x22d3ee, 0x38bdf8, 0xa78bfa, 0xf472b6, 0xfbbf24, 0x34d399];
  HL.state = { dark: true };
  HL.setTheme = function (dark) { HL.state.dark = dark; if (HL.applyTheme) HL.applyTheme(); };

  var canvas = document.getElementById("bg");
  if (!canvas || typeof THREE === "undefined") return;

  var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var renderer;
  try {
    renderer = new THREE.WebGLRenderer({ canvas: canvas, antialias: true, alpha: true });
  } catch (e) {
    return; // sem WebGL a página funciona igual, só sem o fundo 3D
  }
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));

  var scene = new THREE.Scene();
  scene.fog = new THREE.Fog(0x0b0c0e, 16, 34);
  var camera = new THREE.PerspectiveCamera(45, 1, 0.1, 80);
  camera.position.set(0, 0, 15);

  var ambient = new THREE.AmbientLight(0xffffff, 0.55);
  var key = new THREE.DirectionalLight(0x22d3ee, 0.55);
  key.position.set(6, 8, 10);
  var fill = new THREE.PointLight(0xffffff, 0.6, 40);
  fill.position.set(-8, -4, 8);
  scene.add(ambient, key, fill);

  var ACCENT = 0x22d3ee;
  var edgeMat = new THREE.LineBasicMaterial({ color: ACCENT, transparent: true, opacity: 0.32 });
  var darkMat = new THREE.MeshStandardMaterial({ color: 0x1a1d21, metalness: 0.5, roughness: 0.5 });
  /* Cores exatas do ícone do app: #e0f2fe (claras) e #22d3ee (ciano) */
  var ICON_LIGHT = 0xe0f2fe, ICON_CYAN = 0x22d3ee;
  var logoMat = new THREE.MeshStandardMaterial({ color: ICON_LIGHT, emissive: ICON_LIGHT, emissiveIntensity: 0.32, metalness: 0, roughness: 0.55 });
  var cyanMat = new THREE.MeshStandardMaterial({ color: ICON_CYAN, emissive: ICON_CYAN, emissiveIntensity: 0.32, metalness: 0, roughness: 0.55 });
  /* Bordas do logo: cinza suave nas peças claras e azul mais escuro na ciano.
     Cada peça ganha um contorno grosso (casco um pouco maior, só a face de trás) e a linha da aresta. */
  var EDGE_GRAY = 0x9aa5b1, EDGE_BLUE = 0x0e7490;
  var grayLine = new THREE.LineBasicMaterial({ color: EDGE_GRAY, transparent: true, opacity: 0.9 });
  var blueLine = new THREE.LineBasicMaterial({ color: EDGE_BLUE, transparent: true, opacity: 0.95 });
  var grayRim = new THREE.MeshBasicMaterial({ color: EDGE_GRAY, side: THREE.BackSide });
  var glowMat = new THREE.MeshStandardMaterial({ color: 0x0e7490, emissive: ACCENT, emissiveIntensity: 0.22, metalness: 0.3, roughness: 0.4 });

  HL.applyTheme = function () {
    var d = HL.state.dark;
    var accent = d ? 0x22d3ee : 0x0891b2;
    scene.fog.color.setHex(d ? 0x0b0c0e : 0xf4f5f7);
    ambient.intensity = d ? 0.55 : 1.05;
    key.intensity = d ? 0.55 : 0.4;
    key.color.setHex(accent);
    darkMat.color.setHex(d ? 0x1a1d21 : 0xdde1e6);
    grayRim.color.setHex(d ? 0x9aa5b1 : 0x8e99a6);
    grayLine.color.copy(grayRim.color);
    glowMat.color.setHex(d ? 0x0e7490 : 0x67c8dc);
    glowMat.emissive.setHex(accent);
    edgeMat.color.setHex(accent);
    edgeMat.opacity = d ? 0.32 : 0.5;
    if (reduced) frame();
  };

  function hexGeometry(radius, height) {
    var g = new THREE.CylinderGeometry(radius, radius, height, 6);
    g.rotateX(Math.PI / 2); // eixo para a câmera, vértice para cima
    return g;
  }
  function hexPrism(radius, height, material) {
    var g = hexGeometry(radius, height);
    var mesh = new THREE.Mesh(g, material);
    mesh.add(new THREE.LineSegments(new THREE.EdgesGeometry(g), edgeMat));
    return mesh;
  }
  function smooth(x, a, b) {
    var t = Math.min(1, Math.max(0, (x - a) / (b - a)));
    return t * t * (3 - 2 * t);
  }

  /* Campo de colmeia (nasce da logo) */
  var field = new THREE.Group();
  scene.add(field);
  var cells = [];
  var SIZE = 1.35, seed = 7, maxD = 1;
  function rand() { seed = (seed * 16807) % 2147483647; return (seed - 1) / 2147483646; }
  var geo = hexGeometry(SIZE * 0.93, 0.35);
  var edgeGeo = new THREE.EdgesGeometry(geo);
  for (var q = -12; q <= 12; q++) {
    for (var r = -9; r <= 9; r++) {
      var x = SIZE * Math.sqrt(3) * (q + r / 2);
      var y = SIZE * 1.5 * r;
      if (Math.abs(x) > 24 || Math.abs(y) > 15) continue;
      var lit = rand() < 0.045;
      var m = new THREE.Mesh(geo, lit ? glowMat : darkMat);
      m.add(new THREE.LineSegments(edgeGeo, edgeMat));
      var d = Math.sqrt(x * x + y * y);
      if (d > maxD) maxD = d;
      cells.push({ m: m, x: x, y: y, z: -13 + rand() * 1.2, d: d, phase: rand() * 6.28, amp: 0.15 + rand() * 0.35 });
      field.add(m);
    }
  }
  cells.forEach(function (c) { c.d /= maxD; });

  /* Logo (igual ao ícone): um hexágono claro em cima e dois embaixo; o ciano fica embaixo à esquerda.
     Três peças separadas: cada uma vem de um canto, se junta e volta a se espalhar na rolagem. */
  function easeOut(x) { x = Math.min(1, Math.max(0, x)); return 1 - Math.pow(1 - x, 3); }
  function makeLogo() {
    function part(material, x, y, ox, oy, oz, rz) {
      var blue = material === cyanMat;
      var m = hexPrism(0.95, 0.5, material);
      m.children[0].material = blue ? blueLine : grayLine;
      return { m: m, x: x, y: y, ox: ox, oy: oy, oz: oz, rz: rz };
    }
    var g = new THREE.Group();
    var parts = [
      part(logoMat, 0, 1.12, -9, 7, 5, 2.4),        // topo: vem do canto superior esquerdo
      part(cyanMat, -0.97, -0.56, -9, -7, 3, -2.8), // ciano: canto inferior esquerdo
      part(logoMat, 0.97, -0.56, 9, -7, 6, 2.0)     // direita: canto inferior direito
    ];
    parts.forEach(function (p) { g.add(p.m); });
    return { group: g, parts: parts };
  }
  /* gather: 0 = peças espalhadas nos cantos, 1 = logo montado */
  function placeParts(L, gather, t, stagger) {
    L.parts.forEach(function (p, i) {
      var k = easeOut(gather * (1 + stagger * 2) - i * stagger);
      p.m.position.set(p.x + p.ox * (1 - k), p.y + p.oy * (1 - k), p.oz * (1 - k));
      p.m.rotation.z = p.rz * (1 - k);
      p.m.rotation.x = (1 - k) * 1.2;
    });
    L.parts[1].m.position.z += Math.sin(t * 1.1) * 0.25 * gatherSoft(gather);
  }
  function gatherSoft(g) { return g > 0.98 ? 1 : 0; }
  var L1 = makeLogo();
  var logo = L1.group;
  scene.add(logo);

  /* Rolagem suavizada e mouse */
  var sy = 0, mx = 0, my = 0, tmx = 0, tmy = 0;
  window.addEventListener("pointermove", function (e) {
    tmx = (e.clientX / window.innerWidth) * 2 - 1;
    tmy = (e.clientY / window.innerHeight) * 2 - 1;
  }, { passive: true });
  function resize() {
    renderer.setSize(window.innerWidth, window.innerHeight, false);
    camera.aspect = window.innerWidth / window.innerHeight;
    camera.updateProjectionMatrix();
  }
  window.addEventListener("resize", resize);
  resize();

  var tmpV = new THREE.Vector3();
  function pointOnPlane(px, py, z) {
    tmpV.set((px / window.innerWidth) * 2 - 1, -((py / window.innerHeight) * 2 - 1), 0.5).unproject(camera);
    tmpV.sub(camera.position).normalize();
    var dist = (z - camera.position.z) / tmpV.z;
    return camera.position.clone().add(tmpV.multiplyScalar(dist));
  }

  /* Logo 3D da seção final: no lugar do ícone, maior, flutuando para cima e para baixo */
  var finalOn = false, renderFinal = function () {};
  var fc = document.getElementById("logo3d");
  if (fc) {
    try {
      var fr = new THREE.WebGLRenderer({ canvas: fc, antialias: true, alpha: true });
      var fs = new THREE.Scene();
      var fcam = new THREE.PerspectiveCamera(32, 1, 0.1, 50);
      fcam.position.set(0, 0, 11);
      fs.add(new THREE.AmbientLight(0xffffff, 0.8));
      var fkey = new THREE.DirectionalLight(0xffffff, 0.6);
      fkey.position.set(4, 6, 8);
      fs.add(fkey);
      var L2 = makeLogo();
      fs.add(L2.group);
      placeParts(L2, 1, 0, 0);
      fr.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
      var sizeFinal = function () {
        var w = fc.clientWidth || 220, h = fc.clientHeight || 220;
        fr.setSize(w, h, false);
        fcam.aspect = w / h;
        fcam.updateProjectionMatrix();
      };
      sizeFinal();
      window.addEventListener("resize", sizeFinal);
      renderFinal = function (t) {
        L2.group.position.y = Math.sin(t * 1.4) * 0.28;
        L2.group.rotation.y = Math.sin(t * 0.6) * 0.4 + mx * 0.5;
        L2.group.rotation.x = Math.sin(t * 0.7) * 0.1 - my * 0.25;
        placeParts(L2, 1, t, 0);
        fr.render(fs, fcam);
      };
      document.documentElement.classList.add("gl-logo");
      if ("IntersectionObserver" in window) {
        new IntersectionObserver(function (es) { finalOn = es[0].isIntersecting; if (finalOn && reduced) frame(); }, { rootMargin: "80px" }).observe(fc);
      } else { finalOn = true; }
    } catch (e) { /* fica o ícone comum */ }
  }

  var clock = new THREE.Clock();
  function frame() {
    var t = reduced ? 0 : clock.getElapsedTime();
    var targetY = window.scrollY;
    sy += (targetY - sy) * (reduced ? 1 : 0.07);
    mx += (tmx - mx) * 0.05;
    my += (tmy - my) * 0.05;

    var vh = window.innerHeight;
    var heroS = sy / vh;
    var e = 1;                                              // colmeia sempre presente
    var maxScroll = Math.max(1, document.documentElement.scrollHeight - vh);
    var frac = sy / maxScroll;
    var wide = camera.aspect > 1.15;

    /* Logo: monta no início (vindo dos cantos) e se espalha de novo ao descer */
    var intro = reduced ? 1 : Math.min(1, Math.max(0, (t - 0.1) / 1.3));
    var scatter = smooth(heroS, 0.04, 0.75);
    placeParts(L1, intro * (1 - scatter), t, 0.22);
    logo.visible = scatter < 0.995;
    logo.scale.setScalar(wide ? 1.55 : 0.5);
    logo.position.x = wide ? 4.4 : 0;
    logo.position.y = (wide ? 0.2 : 3.1) + Math.sin(t * 0.9) * 0.15;
    logo.rotation.y = Math.sin(t * 0.45) * 0.35 + mx * 0.45;
    logo.rotation.x = Math.sin(t * 0.5) * 0.12 - my * 0.3;

    /* Campo: cada célula sai do centro da logo, mais perto primeiro */
    field.position.set(0, frac * 7, 0);
    field.rotation.x = -0.35 + frac * 1.1 + my * 0.06;
    field.rotation.y = mx * 0.08 + frac * 0.4;
    for (var i = 0; i < cells.length; i++) {
      var c = cells[i];
      var ce = smooth(e, c.d * 0.55, c.d * 0.55 + 0.45);
      c.m.visible = ce > 0.001;
      if (!c.m.visible) continue;
      c.m.scale.setScalar(ce);
      c.m.position.set(c.x * ce, c.y * ce, c.z * ce + Math.sin(t * 0.6 + c.phase) * c.amp);
    }
    glowMat.emissiveIntensity = 0.2 + Math.sin(t * 1.4) * 0.07;

    renderer.render(scene, camera);
    if (finalOn) renderFinal(t);
    if (!reduced) requestAnimationFrame(frame);
  }
  HL.applyTheme();
  frame();
  if (reduced) window.addEventListener("scroll", frame, { passive: true });
})();
