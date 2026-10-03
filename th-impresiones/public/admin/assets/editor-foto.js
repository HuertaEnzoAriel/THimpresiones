/* Editor de foto: antes de subir una imagen, deja recortarla, girarla (en
   pasos de 90°) y moverla dentro del recuadro, para que el resultado quede
   bien encuadrado en las tarjetas de la página (que usan object-fit:cover).
   Todo pasa en el navegador: se dibuja el resultado en un <canvas> y se
   genera un archivo JPEG nuevo, que es el que se termina subiendo. Se expone
   como Panel.editarFoto(file, opciones) -> Promise<File|null> (null si se
   cancela). Depende de que admin.js ya haya definido window.Panel. */
(function () {
  const ZOOM_MAX = 4;

  function clamp(v, min, max) { return Math.min(max, Math.max(min, v)); }
  function distancia(a, b) { return Math.hypot(a.x - b.x, a.y - b.y); }

  function editarFoto(file, opciones = {}) {
    const aspAncho = opciones.aspectoAncho || 4;
    const aspAlto = opciones.aspectoAlto || 3;
    const salidaAncho = opciones.salidaAncho || 1280;
    const salidaAlto = Math.round(salidaAncho * (aspAlto / aspAncho));

    return new Promise(resolve => {
      const urlObjeto = URL.createObjectURL(file);
      let resuelto = false;
      function terminar(valor) {
        if (resuelto) return;
        resuelto = true;
        URL.revokeObjectURL(urlObjeto);
        document.body.classList.remove('ed-bloqueado');
        overlay.remove();
        resolve(valor);
      }

      const overlay = document.createElement('div');
      overlay.className = 'ed-overlay';
      overlay.innerHTML =
        '<div class="ed-modal" role="dialog" aria-modal="true" aria-label="Ajustar foto">' +
          '<div class="ed-cab">Ajustar foto</div>' +
          '<div class="ed-viewport"><img class="ed-img" alt="" draggable="false"></div>' +
          '<p class="ayuda ed-ayuda">Arrastrá la foto para moverla. Usá el control de zoom (o la rueda del mouse) para acercar, y girar si hace falta.</p>' +
          '<div class="ed-controles">' +
            '<div class="ed-zoom">' +
              '<label for="ed-zoom-rango">Zoom</label>' +
              '<input type="range" id="ed-zoom-rango" min="1" max="' + ZOOM_MAX + '" step="0.01" value="1">' +
            '</div>' +
            '<div class="ed-rotar">' +
              '<button type="button" class="btn btn-ghost btn-sm" data-rotar="-90" title="Girar a la izquierda">⟲</button>' +
              '<button type="button" class="btn btn-ghost btn-sm" data-rotar="90" title="Girar a la derecha">⟳</button>' +
              '<button type="button" class="btn btn-ghost btn-sm" data-reiniciar>Reiniciar</button>' +
            '</div>' +
          '</div>' +
          '<div class="ed-acciones">' +
            '<button type="button" class="btn btn-ghost" data-cancelar>Cancelar</button>' +
            '<button type="button" class="btn btn-primary" data-usar>Usar esta foto</button>' +
          '</div>' +
        '</div>';
      overlay.querySelector('.ed-viewport').style.setProperty('--ed-ar', aspAncho + '/' + aspAlto);
      document.body.appendChild(overlay);
      document.body.classList.add('ed-bloqueado');

      const viewport = overlay.querySelector('.ed-viewport');
      const img = overlay.querySelector('.ed-img');
      const sliderZoom = overlay.querySelector('#ed-zoom-rango');

      let rot = 0, zoomFactor = 1, offsetX = 0, offsetY = 0, scaleActual = 1;

      function render() {
        if (!img.naturalWidth) return;
        const Wd = viewport.clientWidth, Hd = viewport.clientHeight;
        const effW = (rot % 180 === 0) ? img.naturalWidth : img.naturalHeight;
        const effH = (rot % 180 === 0) ? img.naturalHeight : img.naturalWidth;
        const baseScale = Math.max(Wd / effW, Hd / effH);
        scaleActual = baseScale * zoomFactor;

        const extX = (effW * scaleActual) / 2;
        const extY = (effH * scaleActual) / 2;
        const maxOffX = Math.max(0, extX - Wd / 2);
        const maxOffY = Math.max(0, extY - Hd / 2);
        offsetX = clamp(offsetX, -maxOffX, maxOffX);
        offsetY = clamp(offsetY, -maxOffY, maxOffY);

        img.style.transform =
          'translate(' + offsetX + 'px,' + offsetY + 'px) translate(-50%,-50%) ' +
          'rotate(' + rot + 'deg) scale(' + scaleActual + ')';
      }

      function alCargarImagen() {
        img.style.width = img.naturalWidth + 'px';
        img.style.height = img.naturalHeight + 'px';
        render();
      }
      img.addEventListener('load', alCargarImagen);
      img.addEventListener('error', () => {
        Panel.avisar('No pudimos abrir esa foto. Probá con otra.');
        terminar(null);
      });
      img.src = urlObjeto;

      // Arrastrar para mover, con soporte de pellizco (pinch) con dos dedos.
      const pointers = new Map();
      let arrastre = null;
      let pellizco = null;

      viewport.addEventListener('pointerdown', e => {
        viewport.setPointerCapture(e.pointerId);
        pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
        if (pointers.size === 1) {
          arrastre = { startX: e.clientX, startY: e.clientY, startOffX: offsetX, startOffY: offsetY };
          pellizco = null;
        } else if (pointers.size === 2) {
          arrastre = null;
          const pts = Array.from(pointers.values());
          pellizco = { startDist: distancia(pts[0], pts[1]), startZoom: zoomFactor };
        }
      });
      viewport.addEventListener('pointermove', e => {
        if (!pointers.has(e.pointerId)) return;
        pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
        if (pointers.size === 1 && arrastre) {
          offsetX = arrastre.startOffX + (e.clientX - arrastre.startX);
          offsetY = arrastre.startOffY + (e.clientY - arrastre.startY);
          render();
        } else if (pointers.size === 2 && pellizco) {
          const pts = Array.from(pointers.values());
          const d = distancia(pts[0], pts[1]);
          zoomFactor = clamp(pellizco.startZoom * (d / pellizco.startDist), 1, ZOOM_MAX);
          sliderZoom.value = String(zoomFactor);
          render();
        }
      });
      function soltarPuntero(e) {
        pointers.delete(e.pointerId);
        if (pointers.size === 1) {
          const pt = Array.from(pointers.values())[0];
          arrastre = { startX: pt.x, startY: pt.y, startOffX: offsetX, startOffY: offsetY };
          pellizco = null;
        } else if (pointers.size === 0) {
          arrastre = null;
          pellizco = null;
        }
      }
      viewport.addEventListener('pointerup', soltarPuntero);
      viewport.addEventListener('pointercancel', soltarPuntero);

      viewport.addEventListener('wheel', e => {
        e.preventDefault();
        zoomFactor = clamp(zoomFactor * (1 - e.deltaY * 0.0015), 1, ZOOM_MAX);
        sliderZoom.value = String(zoomFactor);
        render();
      }, { passive: false });

      sliderZoom.addEventListener('input', () => {
        zoomFactor = parseFloat(sliderZoom.value) || 1;
        render();
      });

      overlay.querySelectorAll('[data-rotar]').forEach(btn => {
        btn.addEventListener('click', () => {
          rot = (rot + parseInt(btn.dataset.rotar, 10) + 360) % 360;
          offsetX = 0; offsetY = 0; zoomFactor = 1;
          sliderZoom.value = '1';
          render();
        });
      });
      overlay.querySelector('[data-reiniciar]').addEventListener('click', () => {
        rot = 0; offsetX = 0; offsetY = 0; zoomFactor = 1;
        sliderZoom.value = '1';
        render();
      });

      overlay.querySelector('[data-cancelar]').addEventListener('click', () => terminar(null));
      overlay.addEventListener('keydown', e => { if (e.key === 'Escape') terminar(null); });
      overlay.tabIndex = -1;
      setTimeout(() => overlay.focus(), 0);

      overlay.querySelector('[data-usar]').addEventListener('click', () => {
        render();
        const canvas = document.createElement('canvas');
        canvas.width = salidaAncho;
        canvas.height = salidaAlto;
        const Wd = viewport.clientWidth;
        const k = salidaAncho / Wd;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#000';
        ctx.fillRect(0, 0, salidaAncho, salidaAlto);
        ctx.save();
        ctx.translate(salidaAncho / 2, salidaAlto / 2);
        ctx.translate(offsetX * k, offsetY * k);
        ctx.rotate(rot * Math.PI / 180);
        ctx.scale(scaleActual * k, scaleActual * k);
        ctx.drawImage(img, -img.naturalWidth / 2, -img.naturalHeight / 2);
        ctx.restore();
        canvas.toBlob(blob => {
          if (!blob) { Panel.avisar('No pudimos procesar la foto. Probá de nuevo.'); terminar(null); return; }
          const nombre = (file.name || 'foto').replace(/\.[^.]+$/, '') + '-editada.jpg';
          terminar(new File([blob], nombre, { type: 'image/jpeg' }));
        }, 'image/jpeg', 0.9);
      });
    });
  }

  window.Panel = window.Panel || {};
  window.Panel.editarFoto = editarFoto;
})();
