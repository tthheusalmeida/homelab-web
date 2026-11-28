/*
 * Registro de scripts de análise e marketing (analytics, pixel). Nada disso pode ficar no HTML:
 * o consent.js só injeta o script daqui DEPOIS que a pessoa aceitar a categoria, e o remove (e recarrega a página) se ela revogar.
 *
 * Para adicionar uma ferramenta, acrescente um item (e libere o domínio dela na CSP, se a página tiver uma):
 *   { id: "ga4", category: "analytics", src: "https://www.googletagmanager.com/gtag/js?id=G-XXXXXXX",
 *     cookies: [/^_ga/], after: function () { window.dataLayer = window.dataLayer || []; function gtag() { dataLayer.push(arguments); } gtag("js", new Date()); gtag("config", "G-XXXXXXX"); } }
 *   { id: "meta-pixel", category: "marketing", src: "https://connect.facebook.net/en_US/fbevents.js", cookies: [/^_fb/], after: function () { ... fbq("init", "ID"); fbq("track", "PageView"); } }
 * category: "analytics" | "marketing". cookies: nomes ou expressões dos cookies a apagar na revogação.
 * Hoje a lista está vazia: o site não carrega nenhuma ferramenta de terceiros.
 */
window.HOMELAB_TRACKERS = [];
