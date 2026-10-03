/* Precios: categorías y lista de trabajos con sus escalas de precio por cantidad.
   tiers = [[cantidad desde, precio por unidad], ...], ordenados de menor a mayor. */
window.TH = window.TH || {};

(function () {
  const categorias = [
    { id: "oficina", nombre: "Copias e impresiones", color: "#EFBE41" },
    { id: "planos", nombre: "Planos y afiches", color: "#E06D93" },
    { id: "comercial", nombre: "Papelería comercial", color: "#3A9AC2" },
    { id: "especiales", nombre: "Stickers, lonas y anillados", color: "#E98346" }
  ];

  const items = [
    { id: "a4-bn", cat: "oficina", nombre: "A4 blanco y negro", spec: "75 a 80 g · simple faz", unidad: "hoja", plural: "hojas", tiers: [[1,120],[50,90],[200,70]] },
    { id: "a4-color", cat: "oficina", nombre: "A4 color", spec: "80 g · simple faz", unidad: "hoja", plural: "hojas", tiers: [[1,350],[50,280],[200,220]] },
    { id: "a3-bn", cat: "oficina", nombre: "A3 blanco y negro", spec: "80 g · simple faz", unidad: "hoja", plural: "hojas", tiers: [[1,250],[50,190],[200,150]] },
    { id: "a3-color", cat: "oficina", nombre: "A3 color", spec: "90 g · simple faz", unidad: "hoja", plural: "hojas", tiers: [[1,700],[50,560],[200,450]] },
    { id: "foto", cat: "oficina", nombre: "Foto 10 × 15 cm", spec: "papel fotográfico brillante", unidad: "foto", plural: "fotos", tiers: [[1,600],[20,500],[50,420]] },
    { id: "plano-a2", cat: "planos", nombre: "Plano A2 (42 × 59,4 cm)", spec: "línea en blanco y negro · 80 g", unidad: "plano", plural: "planos", tiers: [[1,1200],[10,1000]] },
    { id: "plano-a1", cat: "planos", nombre: "Plano A1 (59,4 × 84,1 cm)", spec: "línea en blanco y negro · 80 g", unidad: "plano", plural: "planos", tiers: [[1,2000],[10,1700]] },
    { id: "plano-a0", cat: "planos", nombre: "Plano A0 (84,1 × 118,9 cm)", spec: "línea en blanco y negro · 80 g", unidad: "plano", plural: "planos", tiers: [[1,3200],[10,2800]] },
    { id: "afiche", cat: "planos", nombre: "Afiche color A2", spec: "papel ilustración 150 g", unidad: "afiche", plural: "afiches", tiers: [[1,4500],[10,3800]] },
    { id: "tarjetas", cat: "comercial", nombre: "Tarjetas personales 9 × 5 cm", spec: "300 g · ambas caras · mate o brillante", unidad: "pack de 100", plural: "packs de 100", tiers: [[1,9500],[3,8500],[10,7500]] },
    { id: "flyers", cat: "comercial", nombre: "Flyers A5", spec: "150 g · ambas caras", unidad: "pack de 100", plural: "packs de 100", tiers: [[1,14000],[3,12500],[10,11000]] },
    { id: "triptico", cat: "comercial", nombre: "Trípticos A4", spec: "150 g · ambas caras · plegados", unidad: "pack de 100", plural: "packs de 100", tiers: [[1,32000],[3,29000]] },
    { id: "stickers", cat: "especiales", nombre: "Stickers en vinilo con corte", spec: "vinilo blanco · laminado", unidad: "plancha A4", plural: "planchas A4", tiers: [[1,2800],[10,2400],[30,2000]] },
    { id: "lona", cat: "especiales", nombre: "Banner en lona con ojales", spec: "lona 13 oz · impresión color", unidad: "m²", plural: "m²", tiers: [[1,16000],[5,14500],[10,13000]] },
    { id: "anillado", cat: "especiales", nombre: "Anillado", spec: "tapa transparente y contratapa · hasta 100 hojas", unidad: "anillado", plural: "anillados", tiers: [[1,2500],[10,2200]] }
  ];

  function byId(id) {
    return items.find(i => i.id === id);
  }

  TH.precios = { categorias, items, byId };
})();
