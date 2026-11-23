/* Horario: a partir del horario semanal (negocio.horariosSemana, de lunes a
   domingo, cada día con sus turnos "desde"/"hasta") arma el texto agrupado
   ("Lunes a viernes: 9:00 a 13:00 y 16:00 a 20:00") y calcula si el local
   está abierto ahora, siempre con la hora de Argentina aunque quien mira
   esté en otro huso. No depende de nada más: el panel también lo usa. */
window.TH = window.TH || {};

(function () {
  const ZONA = "America/Argentina/San_Juan";
  const DIAS = ["lun", "mar", "mie", "jue", "vie", "sab", "dom"];
  const NOMBRE = { lun: "lunes", mar: "martes", mie: "miércoles", jue: "jueves", vie: "viernes", sab: "sábado", dom: "domingo" };
  const PLURAL = { lun: "Lunes", mar: "Martes", mie: "Miércoles", jue: "Jueves", vie: "Viernes", sab: "Sábados", dom: "Domingos" };

  const hora = s => String(s).replace(/^0(\d)/, "$1");
  const aMin = s => { const [h, m] = String(s).split(":").map(Number); return h * 60 + m; };
  const cap = s => s.charAt(0).toUpperCase() + s.slice(1);

  // Devuelve siempre 7 días en orden lun..dom, aunque falte alguno.
  function normalizar(semana) {
    const porDia = {};
    (Array.isArray(semana) ? semana : []).forEach(d => { if (d && DIAS.includes(d.dia)) porDia[d.dia] = Array.isArray(d.turnos) ? d.turnos : []; });
    return DIAS.map(k => ({ dia: k, turnos: (porDia[k] || []).slice().sort((a, b) => aMin(a.desde) - aMin(b.desde)) }));
  }

  function textoTurnos(turnos) {
    return turnos.length ? turnos.map(t => hora(t.desde) + " a " + hora(t.hasta)).join(" y ") : "Cerrado";
  }

  /* Agrupa días seguidos con el mismo horario: [{dia: "Lunes a viernes", horas: "..."}] */
  function agrupar(semana) {
    const dias = normalizar(semana);
    const grupos = [];
    dias.forEach(d => {
      const horas = textoTurnos(d.turnos);
      const ult = grupos[grupos.length - 1];
      if (ult && ult.horas === horas) ult.hasta = d.dia; else grupos.push({ desde: d.dia, hasta: d.dia, horas });
    });
    return grupos.map(g => {
      const n = DIAS.indexOf(g.hasta) - DIAS.indexOf(g.desde) + 1;
      const dia = n === 1 ? PLURAL[g.desde] : cap(NOMBRE[g.desde]) + (n === 2 ? " y " : " a ") + NOMBRE[g.hasta];
      return { dia, horas: g.horas };
    });
  }

  // Día (0 = lunes) y minutos desde la medianoche, en hora de Argentina.
  function ahoraArgentina(fecha) {
    const partes = new Intl.DateTimeFormat("en-US", { timeZone: ZONA, weekday: "short", hour: "2-digit", minute: "2-digit", hourCycle: "h23" })
      .formatToParts(fecha || new Date());
    const v = t => (partes.find(p => p.type === t) || {}).value;
    const dia = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"].indexOf(v("weekday"));
    return { dia: dia < 0 ? 0 : dia, min: (Number(v("hour")) % 24) * 60 + Number(v("minute")) };
  }

  /* { abierto: bool, texto: "Abierto ahora · cierra a las 13:00" } o null si no hay horario cargado. */
  function estado(semana, fecha) {
    const dias = normalizar(semana);
    if (!dias.some(d => d.turnos.length)) return null;
    const { dia, min } = ahoraArgentina(fecha);
    const hoy = dias[dia].turnos;
    const actual = hoy.find(t => min >= aMin(t.desde) && min < aMin(t.hasta));
    if (actual) return { abierto: true, texto: "Abierto ahora · cierra a las " + hora(actual.hasta) };
    const luego = hoy.find(t => aMin(t.desde) > min);
    if (luego) return { abierto: false, texto: "Cerrado · abre hoy a las " + hora(luego.desde) };
    for (let i = 1; i <= 7; i++) {
      const d = dias[(dia + i) % 7];
      if (d.turnos.length) {
        const cuando = i === 1 ? "mañana" : "el " + NOMBRE[d.dia];
        return { abierto: false, texto: "Cerrado · abre " + cuando + " a las " + hora(d.turnos[0].desde) };
      }
    }
    return null;
  }

  TH.horario = { DIAS, NOMBRE, normalizar, agrupar, estado, textoTurnos };
})();
