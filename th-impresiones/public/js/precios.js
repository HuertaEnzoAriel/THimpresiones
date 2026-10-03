/* Precios: objeto que va a contener las categorías y la lista de precios.
   Lo completa js/datos.js al cargar data/datos.json. byId() queda disponible
   desde el arranque para que calculadora.js la pueda usar sin esperar nada más. */
window.TH = window.TH || {};

TH.precios = {
  categorias: [],
  items: [],
  byId(id) {
    return TH.precios.items.find(i => i.id === id);
  }
};
