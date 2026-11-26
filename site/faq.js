/* Perguntas frequentes: abrir e fechar com animação de altura (a seta gira junto).
   Sem JS ou com movimento reduzido, o <details> nativo continua funcionando. */
(function () {
  "use strict";
  var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var DUR = 420, EASE = "cubic-bezier(0.4, 0, 0.2, 1)";

  Array.prototype.forEach.call(document.querySelectorAll(".faq details"), function (d) {
    var sum = d.querySelector("summary");
    var body = d.querySelector("p");
    var anim = null, bodyAnim = null;
    if (!sum || !body) return;

    function settle(open) {
      d.open = open;
      d.style.overflow = "";
      d.style.height = "";
      anim = bodyAnim = null;
    }
    function run(open) {
      var from = d.offsetHeight;
      if (anim) anim.cancel();
      if (bodyAnim) bodyAnim.cancel();
      d.style.overflow = "hidden";
      d.classList.toggle("is-open", open);
      if (open) d.open = true;
      var to = sum.offsetHeight;
      if (open) { d.style.height = "auto"; to = d.scrollHeight; d.style.height = from + "px"; } // altura final = o <details> já aberto
      anim = d.animate({ height: [from + "px", to + "px"] }, { duration: DUR, easing: EASE });
      bodyAnim = body.animate(
        open ? { opacity: [0, 1], transform: ["translateY(-8px)", "none"] } : { opacity: [1, 0], transform: ["none", "translateY(-8px)"] },
        { duration: DUR * (open ? 0.9 : 0.6), easing: EASE, delay: open ? DUR * 0.12 : 0, fill: "both" }
      );
      anim.onfinish = function () { settle(open); };
      anim.oncancel = function () { /* interrompido por um novo clique */ };
    }

    sum.addEventListener("click", function (e) {
      e.preventDefault();
      var willOpen = !d.classList.contains("is-open");
      if (reduced || !d.animate) { d.open = willOpen; d.classList.toggle("is-open", willOpen); return; }
      run(willOpen);
    });
  });
})();
