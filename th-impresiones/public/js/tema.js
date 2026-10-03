/* Tema: botón de modo claro/oscuro del encabezado. Respeta la preferencia del
   sistema, permite forzar un tema con data-theme, y guarda la elección en
   localStorage dentro de un try/catch para no romper si el navegador la bloquea. */
window.TH = window.TH || {};

(function () {
  const { $ } = TH.utils;

  function init() {
    const root = document.documentElement;
    const temaBtn = $("#tema");
    const ICO_LUNA = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>';
    const ICO_SOL = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>';
    const sysDark = window.matchMedia("(prefers-color-scheme: dark)");

    function esOscuro() {
      const t = root.getAttribute("data-theme");
      return t === "dark" || (t !== "light" && sysDark.matches);
    }
    function pintarTema() {
      const osc = esOscuro();
      $("#tema-ico").innerHTML = osc ? ICO_SOL : ICO_LUNA;
      $("#tema-txt").textContent = osc ? "Modo claro" : "Modo oscuro";
      temaBtn.setAttribute("aria-label", osc ? "Cambiar a modo claro" : "Cambiar a modo oscuro");
    }

    try {
      const guardado = localStorage.getItem("tema");
      if (guardado === "dark" || guardado === "light") root.setAttribute("data-theme", guardado);
    } catch (e) {}

    temaBtn.addEventListener("click", () => {
      const sig = esOscuro() ? "light" : "dark";
      root.setAttribute("data-theme", sig);
      try { localStorage.setItem("tema", sig); } catch (e) {}
      pintarTema();
    });
    sysDark.addEventListener("change", pintarTema);
    new MutationObserver(pintarTema).observe(root, { attributes: true, attributeFilter: ["data-theme"] });
    pintarTema();
  }

  TH.tema = { init };
})();
