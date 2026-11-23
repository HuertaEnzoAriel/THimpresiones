/* Datos: carga data/datos.json una sola vez al iniciar la página pública y lo
   reparte en TH.config, TH.precios, TH.categoriasTrabajos, TH.trabajos (solo
   los trabajos marcados como visibles), TH.calc (calculadora: trabajos,
   tamaños, papeles y atajos) y TH.ayuda (checklist y preguntas). Si el archivo no existe o está mal
   formado, TH.datos.cargar() devuelve false para que main.js muestre un aviso
   en vez de romper la página. */
window.TH = window.TH || {};

(function () {
  async function cargar() {
    try {
      const resp = await fetch("data/datos.json", { cache: "no-store" });
      if (!resp.ok) throw new Error("HTTP " + resp.status);
      const datos = await resp.json();

      TH.config = datos.negocio || {};
      TH.precios.categorias = Array.isArray(datos.categoriasPrecios) ? datos.categoriasPrecios : [];
      TH.precios.items = Array.isArray(datos.precios) ? datos.precios : [];
      TH.categoriasTrabajos = Array.isArray(datos.categoriasTrabajos) ? datos.categoriasTrabajos : [];
      TH.trabajos = (Array.isArray(datos.trabajos) ? datos.trabajos : []).filter(t => t.visible !== false);
      TH.calc = datos.calculadora && typeof datos.calculadora === "object" ? datos.calculadora : {};
      TH.ayuda = datos.ayuda && typeof datos.ayuda === "object" ? datos.ayuda : {};

      return true;
    } catch (e) {
      return false;
    }
  }

  TH.datos = { cargar };
})();
