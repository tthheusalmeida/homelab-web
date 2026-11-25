/* aplica o tema antes de pintar (arquivo externo para a CSP estrita das páginas de conta) */
(function () {
  var t = null;
  try { t = localStorage.getItem("homelab.landing.theme"); } catch (e) {}
  if (t !== "light" && t !== "dark") t = window.matchMedia("(prefers-color-scheme: light)").matches ? "light" : "dark";
  document.documentElement.setAttribute("data-theme", t);
})();
