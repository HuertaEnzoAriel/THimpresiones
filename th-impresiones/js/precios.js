/* Precios: categorías y lista de trabajos con sus escalas de precio por cantidad.
   tiers = [[cantidad desde, precio por unidad], ...], ordenados de menor a mayor.
   Ajustado a lo que imprime una Epson EcoTank L8050: tamaño máximo A4, sin
   fotocopias, sin doble faz automática, con fotos sin bordes y CD/DVD/PVC directo. */
window.TH = window.TH || {};

(function () {
  const categorias = [
    { id: "oficina", nombre: "Impresiones", color: "#EFBE41" },
    { id: "fotos", nombre: "Fotografías", color: "#E06D93" },
    { id: "comercial", nombre: "Papelería comercial", color: "#3A9AC2" },
    { id: "especiales", nombre: "Especiales", color: "#E98346" }
  ];

  const items = [
    { id: "a4-bn", cat: "oficina", nombre: "A4 blanco y negro", spec: "75 a 80 g · simple faz", unidad: "hoja", plural: "hojas", tiers: [[1,120],[50,90],[200,70]] },
    { id: "a4-color", cat: "oficina", nombre: "A4 color", spec: "80 g · simple faz", unidad: "hoja", plural: "hojas", tiers: [[1,350],[50,280],[200,220]] },
    { id: "oficio-color", cat: "oficina", nombre: "Oficio color", spec: "80 g · simple faz", unidad: "hoja", plural: "hojas", tiers: [[1,350],[50,280],[200,220]] },

    { id: "foto-10x15", cat: "fotos", nombre: "Foto 10 × 15 cm", spec: "papel fotográfico brillante · sin bordes", unidad: "foto", plural: "fotos", tiers: [[1,600],[20,500],[50,420]] },
    { id: "foto-13x18", cat: "fotos", nombre: "Foto 13 × 18 cm", spec: "papel fotográfico brillante · sin bordes", unidad: "foto", plural: "fotos", tiers: [[1,900],[20,750],[50,650]] }, // PRECIO DE EJEMPLO, AJUSTAR
    { id: "foto-20x25", cat: "fotos", nombre: "Foto 20 × 25 cm (8 × 10\")", spec: "papel fotográfico brillante · sin bordes", unidad: "foto", plural: "fotos", tiers: [[1,1600],[20,1350],[50,1150]] }, // PRECIO DE EJEMPLO, AJUSTAR
    { id: "foto-a4", cat: "fotos", nombre: "Foto A4 sin bordes", spec: "papel fotográfico brillante · sin bordes", unidad: "foto", plural: "fotos", tiers: [[1,2200],[20,1900],[50,1650]] }, // PRECIO DE EJEMPLO, AJUSTAR

    { id: "tarjetas", cat: "comercial", nombre: "Tarjetas personales 9 × 5 cm", spec: "papel fotográfico o ilustración · doble faz manual · mate o brillante", unidad: "pack de 100", plural: "packs de 100", tiers: [[1,9500],[3,8500],[10,7500]] },
    { id: "flyers", cat: "comercial", nombre: "Flyers A5", spec: "papel fotográfico o ilustración · doble faz manual", unidad: "pack de 100", plural: "packs de 100", tiers: [[1,14000],[3,12500],[10,11000]] },
    { id: "triptico", cat: "comercial", nombre: "Trípticos A4", spec: "papel fotográfico o ilustración · doble faz manual · plegados", unidad: "pack de 100", plural: "packs de 100", tiers: [[1,32000],[3,29000]] },

    { id: "stickers", cat: "especiales", nombre: "Stickers en papel adhesivo", spec: "plancha A4 · papel adhesivo", unidad: "plancha A4", plural: "planchas A4", tiers: [[1,2800],[10,2400],[30,2000]] },
    { id: "cd-dvd", cat: "especiales", nombre: "CD / DVD impreso", spec: "impresión directa sobre CD o DVD", unidad: "unidad", plural: "unidades", tiers: [[1,1500],[10,1200],[50,950]] }, // PRECIO DE EJEMPLO, AJUSTAR
    { id: "credencial-pvc", cat: "especiales", nombre: "Credencial PVC 54 × 86 mm", spec: "impresión directa sobre PVC", unidad: "unidad", plural: "unidades", tiers: [[1,1800],[10,1450],[50,1150]] }, // PRECIO DE EJEMPLO, AJUSTAR
    { id: "anillado", cat: "especiales", nombre: "Anillado", spec: "tapa transparente y contratapa · hasta 100 hojas", unidad: "anillado", plural: "anillados", tiers: [[1,2500],[10,2200]] }
  ];

  function byId(id) {
    return items.find(i => i.id === id);
  }

  TH.precios = { categorias, items, byId };
})();
