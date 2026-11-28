/*
 * Identificação do fornecedor (Decreto 7.962/2013): nome empresarial, CNPJ (ou CPF), endereço físico e e-mail.
 * É o ÚNICO lugar com esses dados: troque os valores *_DEFAULT abaixo quando a empresa existir.
 * Todo elemento com data-empresa="<campo>" recebe o texto; <a data-empresa="email" data-empresa-mailto> também recebe o mailto:; data-empresa-href="email" só troca o href.
 * `php tools/check-company.php` lista o que ainda está como *_DEFAULT (o deploy não deve seguir com isso).
 */
(function () {
  var EMPRESA = {
    razaoSocial: "RAZAO_SOCIAL_DEFAULT",
    cnpj: "CNPJ_DEFAULT",
    endereco: "ENDERECO_DEFAULT",
    email: "EMAIL_DEFAULT",
  };

  function fill() {
    // links só com ícone: recebem o mailto: sem trocar o conteúdo
    var links = document.querySelectorAll("[data-empresa-href='email']");
    for (var j = 0; j < links.length; j++) links[j].setAttribute("href", "mailto:" + EMPRESA.email);
    var nodes = document.querySelectorAll("[data-empresa]");
    for (var i = 0; i < nodes.length; i++) {
      var el = nodes[i];
      var value = EMPRESA[el.getAttribute("data-empresa")];
      if (typeof value !== "string") continue;
      el.textContent = value;
      if (el.hasAttribute("data-empresa-mailto")) el.setAttribute("href", "mailto:" + value);
    }
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", fill);
  else fill();
})();
