/* Panel: helpers compartidos por todas las páginas del panel — pedidos con
   token CSRF, formato de dinero, avisos (toast), confirmación dentro de la
   página (nunca confirm()/alert() del navegador) y el control de la barra
   fija "Guardar cambios". Se expone todo bajo window.Panel. */
(function () {
  function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
  }

  async function apiFetch(url, opciones = {}) {
    const esFormData = opciones.body instanceof FormData;
    const headers = Object.assign(
      esFormData ? { 'X-CSRF-Token': csrfToken() } : { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
      opciones.headers || {}
    );
    const resp = await fetch(url, Object.assign({}, opciones, { headers }));
    let cuerpo = null;
    try { cuerpo = await resp.json(); } catch (e) { /* respuesta sin cuerpo JSON */ }
    if (!resp.ok) {
      const err = new Error((cuerpo && cuerpo.error) || 'Ocurrió un error. Probá de nuevo.');
      err.conflicto = resp.status === 409;
      err.sesionVencida = resp.status === 401;
      err.cuerpo = cuerpo;
      throw err;
    }
    return cuerpo;
  }

  const money = n => new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS', maximumFractionDigits: 0 }).format(n || 0);

  function avisar(mensaje) {
    const toast = document.getElementById('panel-toast');
    if (!toast) return;
    toast.textContent = mensaje;
    toast.hidden = false;
    clearTimeout(toast._t);
    toast._t = setTimeout(() => { toast.hidden = true; }, 3200);
  }

  function mostrarDeshacer(mensaje, onDeshacer) {
    const toast = document.getElementById('panel-toast');
    if (!toast) return;
    clearTimeout(toast._t);
    toast.textContent = '';
    const span = document.createElement('span');
    span.textContent = mensaje;
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-ghost btn-sm';
    btn.textContent = 'Deshacer';
    toast.append(span, btn);
    toast.hidden = false;
    let activo = true;
    const cerrar = () => { if (!activo) return; activo = false; toast.hidden = true; };
    btn.addEventListener('click', () => { cerrar(); onDeshacer(); });
    toast._t = setTimeout(cerrar, 6000);
  }

  function confirmarEnLinea(botonOrigen, mensaje, onConfirmar) {
    const contenedor = document.createElement('div');
    contenedor.className = 'confirmar';
    const p = document.createElement('p');
    p.textContent = mensaje;
    const acciones = document.createElement('div');
    acciones.className = 'acciones';
    const cancelar = document.createElement('button');
    cancelar.type = 'button';
    cancelar.className = 'btn btn-ghost btn-sm';
    cancelar.textContent = 'Cancelar';
    const confirmar = document.createElement('button');
    confirmar.type = 'button';
    confirmar.className = 'btn btn-primary btn-sm';
    confirmar.textContent = 'Sí, eliminar';
    acciones.append(cancelar, confirmar);
    contenedor.append(p, acciones);
    botonOrigen.replaceWith(contenedor);
    cancelar.addEventListener('click', () => contenedor.replaceWith(botonOrigen));
    confirmar.addEventListener('click', () => { contenedor.remove(); onConfirmar(); });
  }

  /* Controla la barra fija "Guardar cambios": guarda el objeto completo de
     datos.json contra api/guardar.php, usando un token de versión para
     detectar si alguien más guardó encima mientras se editaba. */
  function crearControladorGuardado({ boton, estado, obtenerDatos, alGuardarOk }) {
    let version = null;
    let sucio = false;

    function marcarCambios() {
      sucio = true;
      boton.disabled = false;
      estado.textContent = 'Cambios sin guardar';
      estado.className = 'estado pendiente';
    }
    function marcarGuardado() {
      sucio = false;
      boton.disabled = true;
      estado.textContent = 'Guardado ✓';
      estado.className = 'estado ok';
    }

    async function cargar() {
      const r = await apiFetch('api/datos.php');
      version = r.version;
      return r.datos;
    }

    async function guardar() {
      boton.disabled = true;
      try {
        const r = await apiFetch('api/guardar.php', {
          method: 'POST',
          body: JSON.stringify({ version, datos: obtenerDatos() })
        });
        version = r.version;
        marcarGuardado();
        avisar('Guardado ✓');
        if (alGuardarOk) alGuardarOk(r.datos);
      } catch (e) {
        if (e.conflicto) {
          avisar('Alguien más guardó cambios mientras editabas esto. Recargá la página para ver lo último.');
        } else if (e.sesionVencida) {
          avisar('Tu sesión venció. Recargá la página para volver a ingresar.');
        } else {
          avisar(e.message);
        }
        boton.disabled = !sucio;
      }
    }

    boton.addEventListener('click', guardar);
    boton.disabled = true;
    window.addEventListener('beforeunload', e => {
      if (sucio) { e.preventDefault(); e.returnValue = ''; }
    });

    return {
      cargar,
      marcarCambios,
      marcarGuardado,
      get version() { return version; },
      set version(v) { version = v; }
    };
  }

  window.Panel = { csrfToken, apiFetch, money, avisar, mostrarDeshacer, confirmarEnLinea, crearControladorGuardado };
})();
