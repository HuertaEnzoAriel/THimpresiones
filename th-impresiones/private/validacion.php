<?php
/* Validación: revisa todo lo que llega del panel antes de guardarlo en
 * datos.json. Nunca confía en lo que mandó el navegador: tipos, largos,
 * números, ids y rutas de imagen se chequean siempre acá. Si algo no
 * cierra, devuelve un solo mensaje en palabras simples y no guarda nada. */

function th_slug(string $s): string {
    $transliterado = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
    $s = $transliterado !== false ? $transliterado : $s;
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim((string) $s, '-');
    return $s !== '' ? $s : 'item';
}

function th_id_unico(string $base, array $idsExistentes): string {
    $candidato = $base;
    $i = 1;
    while (in_array($candidato, $idsExistentes, true)) {
        $i++;
        $candidato = $base . '-' . $i;
    }
    return $candidato;
}

function th_texto(string $valor, int $max, string $campo): string {
    $valor = trim($valor);
    if (mb_strlen($valor) > $max) {
        throw new Th_ErrorValidacion("\"$campo\" es demasiado largo (máximo $max caracteres).");
    }
    return $valor;
}

function th_color_valido(string $valor): bool {
    return (bool) preg_match('/^#[0-9A-Fa-f]{6}$/', $valor);
}

final class Th_ErrorValidacion extends \RuntimeException {}

/**
 * Valida y normaliza el objeto completo recibido del panel. Devuelve el
 * array ya normalizado (con ids definitivos para lo nuevo) o lanza
 * Th_ErrorValidacion con un mensaje para mostrar directo al usuario.
 */
function th_validar_datos(array $entrada): array {
    $negocio = th_validar_negocio($entrada['negocio'] ?? []);
    $categoriasPrecios = th_validar_categorias($entrada['categoriasPrecios'] ?? [], 'categoría de precios');
    $idsCatPrecios = array_column($categoriasPrecios, 'id');
    $precios = th_validar_precios($entrada['precios'] ?? [], $idsCatPrecios);
    $calculadora = th_validar_calculadora($entrada['calculadora'] ?? [], $precios);
    $idsCalc = array_column($calculadora['trabajos'], 'id');
    $categoriasTrabajos = th_validar_categorias($entrada['categoriasTrabajos'] ?? [], 'categoría de trabajos');
    $idsCatTrabajos = array_column($categoriasTrabajos, 'id');
    $trabajos = th_validar_trabajos($entrada['trabajos'] ?? [], $idsCatTrabajos, $idsCalc);
    $ayuda = th_validar_ayuda($entrada['ayuda'] ?? []);

    return [
        'negocio' => $negocio,
        'categoriasPrecios' => $categoriasPrecios,
        'precios' => $precios,
        'categoriasTrabajos' => $categoriasTrabajos,
        'trabajos' => $trabajos,
        'calculadora' => $calculadora,
        'ayuda' => $ayuda,
    ];
}

function th_validar_negocio($datos): array {
    if (!is_array($datos)) {
        throw new Th_ErrorValidacion('Faltan los datos de contacto del negocio.');
    }
    $nombre = th_texto((string) ($datos['nombre'] ?? ''), TH_MAX_TEXTO_CORTO, 'Nombre del negocio');
    if ($nombre === '') {
        throw new Th_ErrorValidacion('El nombre del negocio no puede quedar vacío.');
    }
    $whatsapp = preg_replace('/\D+/', '', (string) ($datos['whatsapp'] ?? ''));
    if ($whatsapp === '' || strlen($whatsapp) < 6 || strlen($whatsapp) > 15) {
        throw new Th_ErrorValidacion('El WhatsApp tiene que tener solo números, con el código de país (ej: 54911...).');
    }
    $telefono = th_texto((string) ($datos['telefono'] ?? ''), 60, 'Teléfono para mostrar');
    $email = th_texto((string) ($datos['email'] ?? ''), 160, 'Mail');
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Th_ErrorValidacion('Revisá el mail: no tiene un formato válido (ej: hola@tunegocio.com).');
    }
    $instagram = th_texto((string) ($datos['instagram'] ?? ''), 60, 'Instagram');
    $imagenHero = (string) ($datos['imagenHero'] ?? '');
    if ($imagenHero !== '' && !th_ruta_imagen_valida($imagenHero)) {
        throw new Th_ErrorValidacion('La imagen principal de la página no es válida. Subila de nuevo.');
    }

    $resultado = [
        'nombre' => $nombre,
        'whatsapp' => $whatsapp,
        'telefono' => $telefono,
        'email' => $email,
        'instagram' => $instagram,
        'imagenHero' => $imagenHero,
    ];

    // Horario semanal (alimenta el texto de horarios y el cartel Abierto/Cerrado).
    if (isset($datos['horariosSemana'])) {
        $resultado['horariosSemana'] = th_validar_horario_semana($datos['horariosSemana']);
        return $resultado;
    }

    // Datos viejos: lista de horarios en texto libre.
    $horarios = [];
    foreach ((is_array($datos['horarios'] ?? null) ? $datos['horarios'] : []) as $fila) {
        if (!is_array($fila)) {
            continue;
        }
        $dia = th_texto((string) ($fila['dia'] ?? ''), TH_MAX_TEXTO_CORTO, 'Día del horario');
        $horas = th_texto((string) ($fila['horas'] ?? ''), TH_MAX_TEXTO_CORTO, 'Horario');
        if ($dia === '' || $horas === '') {
            continue;
        }
        $horarios[] = ['dia' => $dia, 'horas' => $horas];
        if (count($horarios) >= 20) {
            break;
        }
    }

    $resultado['horarios'] = $horarios;
    return $resultado;
}

const TH_DIAS = ['lun' => 'lunes', 'mar' => 'martes', 'mie' => 'miércoles', 'jue' => 'jueves', 'vie' => 'viernes', 'sab' => 'sábado', 'dom' => 'domingo'];

function th_minutos(string $hhmm): int {
    [$h, $m] = array_map('intval', explode(':', $hhmm));
    return $h * 60 + $m;
}

function th_validar_horario_semana($lista): array {
    $porDia = [];
    foreach (is_array($lista) ? $lista : [] as $fila) {
        if (is_array($fila) && isset(TH_DIAS[$fila['dia'] ?? ''])) {
            $porDia[$fila['dia']] = is_array($fila['turnos'] ?? null) ? $fila['turnos'] : [];
        }
    }
    $formato = '/^([01]\d|2[0-3]):[0-5]\d$/';
    $resultado = [];
    foreach (TH_DIAS as $clave => $nombre) {
        $turnos = [];
        foreach ($porDia[$clave] ?? [] as $t) {
            if (!is_array($t)) {
                continue;
            }
            $desde = (string) ($t['desde'] ?? '');
            $hasta = (string) ($t['hasta'] ?? '');
            if ($desde === '' && $hasta === '') {
                continue;
            }
            if (!preg_match($formato, $desde) || !preg_match($formato, $hasta)) {
                throw new Th_ErrorValidacion("Revisá el horario del $nombre: completá la hora de apertura y la de cierre.");
            }
            if (th_minutos($hasta) <= th_minutos($desde)) {
                throw new Th_ErrorValidacion("El $nombre, la hora de cierre tiene que ser después de la de apertura.");
            }
            $turnos[] = ['desde' => $desde, 'hasta' => $hasta];
        }
        usort($turnos, fn($a, $b) => th_minutos($a['desde']) - th_minutos($b['desde']));
        for ($i = 1; $i < count($turnos); $i++) {
            if (th_minutos($turnos[$i]['desde']) < th_minutos($turnos[$i - 1]['hasta'])) {
                throw new Th_ErrorValidacion("El $nombre hay dos turnos que se pisan. Revisá las horas.");
            }
        }
        if (count($turnos) > 3) {
            throw new Th_ErrorValidacion("El $nombre tiene demasiados turnos (máximo 3).");
        }
        $resultado[] = ['dia' => $clave, 'turnos' => $turnos];
    }
    return $resultado;
}

function th_validar_categorias($lista, string $etiqueta): array {
    if (!is_array($lista)) {
        return [];
    }
    $resultado = [];
    $ids = [];
    foreach ($lista as $fila) {
        if (!is_array($fila)) {
            continue;
        }
        $nombre = th_texto((string) ($fila['nombre'] ?? ''), 60, "Nombre de $etiqueta");
        if ($nombre === '') {
            throw new Th_ErrorValidacion("Una $etiqueta no puede tener el nombre vacío.");
        }
        $color = (string) ($fila['color'] ?? '');
        if (!th_color_valido($color)) {
            throw new Th_ErrorValidacion("El color de \"$nombre\" no es válido. Elegí uno de la paleta.");
        }
        $id = (string) ($fila['id'] ?? '');
        if ($id === '' || !preg_match('/^[a-z0-9-]+$/', $id)) {
            $id = th_id_unico(th_slug($nombre), $ids);
        }
        if (in_array($id, $ids, true)) {
            $id = th_id_unico($id, $ids);
        }
        $ids[] = $id;
        $resultado[] = ['id' => $id, 'nombre' => $nombre, 'color' => strtoupper($color)];
    }
    return $resultado;
}

function th_validar_precios($lista, array $idsCategoriasValidas): array {
    if (!is_array($lista)) {
        return [];
    }
    $resultado = [];
    $ids = [];
    foreach ($lista as $fila) {
        if (!is_array($fila)) {
            continue;
        }
        $nombre = th_texto((string) ($fila['nombre'] ?? ''), TH_MAX_TEXTO_CORTO, 'Nombre del trabajo');
        if ($nombre === '') {
            throw new Th_ErrorValidacion('Un trabajo de la lista de precios se quedó sin nombre.');
        }
        $categoria = (string) ($fila['categoria'] ?? '');
        if (!in_array($categoria, $idsCategoriasValidas, true)) {
            throw new Th_ErrorValidacion("\"$nombre\" no tiene una categoría válida. Elegí una de la lista.");
        }
        $detalle = th_texto((string) ($fila['detalle'] ?? ''), TH_MAX_TEXTO_LARGO, "Descripción de \"$nombre\"");
        $unidad = th_texto((string) ($fila['unidad'] ?? ''), 40, "Unidad de \"$nombre\"");
        $plural = th_texto((string) ($fila['plural'] ?? ''), 40, "Unidad (plural) de \"$nombre\"");
        if ($unidad === '' || $plural === '') {
            throw new Th_ErrorValidacion("A \"$nombre\" le falta decir la unidad (ej: \"hoja\" / \"hojas\").");
        }

        $escalasEntrada = is_array($fila['escalas'] ?? null) ? $fila['escalas'] : [];
        if (count($escalasEntrada) < 1) {
            throw new Th_ErrorValidacion("\"$nombre\" necesita al menos un precio por cantidad.");
        }
        $escalas = [];
        foreach ($escalasEntrada as $e) {
            if (!is_array($e)) {
                continue;
            }
            $desde = filter_var($e['desde'] ?? null, FILTER_VALIDATE_INT);
            $precio = filter_var($e['precio'] ?? null, FILTER_VALIDATE_INT);
            if ($desde === false || $desde === null || $desde < 1) {
                throw new Th_ErrorValidacion("En \"$nombre\", la cantidad \"desde\" tiene que ser 1 o más.");
            }
            if ($precio === false || $precio === null || $precio < 0) {
                throw new Th_ErrorValidacion("En \"$nombre\", el precio no puede ser negativo.");
            }
            $escalas[] = ['desde' => $desde, 'precio' => $precio];
        }
        usort($escalas, fn($a, $b) => $a['desde'] - $b['desde']);
        if ($escalas[0]['desde'] !== 1) {
            throw new Th_ErrorValidacion("En \"$nombre\", el primer precio por cantidad tiene que empezar en 1.");
        }
        $vistos = [];
        foreach ($escalas as $e) {
            if (in_array($e['desde'], $vistos, true)) {
                throw new Th_ErrorValidacion("En \"$nombre\", hay dos precios por cantidad que empiezan en el mismo número.");
            }
            $vistos[] = $e['desde'];
        }

        $id = (string) ($fila['id'] ?? '');
        if ($id === '' || !preg_match('/^[a-z0-9-]+$/', $id)) {
            $id = th_id_unico(th_slug($nombre), $ids);
        }
        if (in_array($id, $ids, true)) {
            $id = th_id_unico($id, $ids);
        }
        $ids[] = $id;

        $resultado[] = [
            'id' => $id,
            'categoria' => $categoria,
            'nombre' => $nombre,
            'detalle' => $detalle,
            'unidad' => $unidad,
            'plural' => $plural,
            'escalas' => $escalas,
        ];
    }
    return $resultado;
}

function th_ruta_imagen_valida(string $valor): bool {
    return (bool) preg_match('#^(img/muestras|uploads)/[A-Za-z0-9_-]+\.(jpg|jpeg|png|webp|svg)$#i', $valor);
}

function th_validar_trabajos($lista, array $idsCategoriasValidas, array $idsCalculadora = []): array {
    if (!is_array($lista)) {
        return [];
    }
    $resultado = [];
    $ids = [];
    foreach ($lista as $fila) {
        if (!is_array($fila)) {
            continue;
        }
        $titulo = th_texto((string) ($fila['titulo'] ?? ''), TH_MAX_TEXTO_CORTO, 'Título del trabajo');
        if ($titulo === '') {
            throw new Th_ErrorValidacion('Un trabajo de la galería se quedó sin título.');
        }
        $categoria = (string) ($fila['categoria'] ?? '');
        if (!in_array($categoria, $idsCategoriasValidas, true)) {
            throw new Th_ErrorValidacion("\"$titulo\" no tiene una categoría válida. Elegí una de la lista.");
        }
        $especificacion = th_texto((string) ($fila['especificacion'] ?? ''), TH_MAX_TEXTO_LARGO, "Especificación de \"$titulo\"");
        $descripcion = th_texto((string) ($fila['descripcion'] ?? ''), TH_MAX_TEXTO_LARGO, "Descripción de \"$titulo\"");
        $imagenes = [];
        $crudas = is_array($fila['imagenes'] ?? null) ? $fila['imagenes'] : [];
        if (!$crudas && !empty($fila['imagen'])) {
            $crudas = [$fila['imagen']];
        }
        foreach ($crudas as $ruta) {
            $ruta = (string) $ruta;
            if ($ruta === '' || in_array($ruta, $imagenes, true)) {
                continue;
            }
            if (!th_ruta_imagen_valida($ruta)) {
                throw new Th_ErrorValidacion("Una de las fotos de \"$titulo\" no es válida. Subila de nuevo.");
            }
            $imagenes[] = $ruta;
        }
        if (!$imagenes) {
            throw new Th_ErrorValidacion("A \"$titulo\" le falta una foto. Subí una antes de guardar.");
        }
        if (count($imagenes) > 12) {
            throw new Th_ErrorValidacion("\"$titulo\" tiene demasiadas fotos (máximo 12).");
        }
        $imagen = $imagenes[0];
        $visible = !isset($fila['visible']) || $fila['visible'] !== false;
        // Trabajo de la calculadora que preselecciona "Pedir algo parecido" ('' = WhatsApp).
        $calcTrabajo = (string) ($fila['calcTrabajo'] ?? '');
        if (!in_array($calcTrabajo, $idsCalculadora, true)) {
            $calcTrabajo = '';
        }

        $id = (string) ($fila['id'] ?? '');
        if ($id === '' || !preg_match('/^[a-z0-9-]+$/', $id)) {
            $id = th_id_unico(th_slug($titulo), $ids);
        }
        if (in_array($id, $ids, true)) {
            $id = th_id_unico($id, $ids);
        }
        $ids[] = $id;

        $resultado[] = [
            'id' => $id,
            'titulo' => $titulo,
            'especificacion' => $especificacion,
            'descripcion' => $descripcion,
            'categoria' => $categoria,
            'imagen' => $imagen,
            'imagenes' => $imagenes,
            'visible' => $visible,
            'calcTrabajo' => $calcTrabajo,
        ];
    }
    return $resultado;
}

/* ===== Calculadora ===== */

const TH_ICONOS_CALC = ['hoja', 'foto', 'tarjeta', 'flyer', 'triptico', 'sticker', 'cd', 'credencial', 'anillado', 'otro'];
const TH_PASOS_CALC = ['tamano', 'faz', 'color', 'papel'];

/* Asigna un id válido y único (si viene vacío o repetido, lo arma desde el nombre). */
function th_id_para(array $fila, string $nombre, array $ids): string {
    $id = (string) ($fila['id'] ?? '');
    if ($id === '' || !preg_match('/^[a-z0-9-]+$/', $id) || strlen($id) > 60) {
        $id = th_slug($nombre);
    }
    return th_id_unico($id, $ids);
}

function th_entero($valor, int $min, int $max, ?int $porDefecto = null): ?int {
    $n = filter_var($valor, FILTER_VALIDATE_INT);
    if ($n === false || $n === null) {
        return $porDefecto;
    }
    return max($min, min($max, $n));
}

function th_validar_calculadora($c, array $precios): array {
    if (!is_array($c)) {
        $c = [];
    }
    $idsPrecios = array_column($precios, 'id');

    // Tamaños
    $tamanos = [];
    $ids = [];
    foreach (is_array($c['tamanos'] ?? null) ? $c['tamanos'] : [] as $fila) {
        if (!is_array($fila)) {
            continue;
        }
        $nombre = th_texto((string) ($fila['nombre'] ?? ''), 40, 'Nombre del tamaño');
        if ($nombre === '') {
            throw new Th_ErrorValidacion('Un tamaño de la calculadora se quedó sin nombre.');
        }
        $ancho = filter_var($fila['ancho'] ?? null, FILTER_VALIDATE_FLOAT);
        $alto = filter_var($fila['alto'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($ancho === false || $alto === false || $ancho < 1 || $alto < 1 || $ancho > 200 || $alto > 200) {
            throw new Th_ErrorValidacion("El tamaño \"$nombre\" necesita ancho y alto en cm (entre 1 y 200).");
        }
        $id = th_id_para($fila, $nombre, $ids);
        $ids[] = $id;
        $tamanos[] = [
            'id' => $id,
            'nombre' => $nombre,
            'ancho' => round($ancho, 1),
            'alto' => round($alto, 1),
            'referencia' => th_texto((string) ($fila['referencia'] ?? ''), 80, "Referencia de \"$nombre\""),
        ];
    }
    if (count($tamanos) > 40) {
        throw new Th_ErrorValidacion('Hay demasiados tamaños (máximo 40).');
    }

    // Papeles
    $papeles = [];
    $ids = [];
    foreach (is_array($c['papeles'] ?? null) ? $c['papeles'] : [] as $fila) {
        if (!is_array($fila)) {
            continue;
        }
        $nombre = th_texto((string) ($fila['nombre'] ?? ''), 60, 'Nombre del papel');
        if ($nombre === '') {
            throw new Th_ErrorValidacion('Un papel de la calculadora se quedó sin nombre.');
        }
        $gramaje = th_entero($fila['gramaje'] ?? null, 1, 2000);
        if ($gramaje === null || $gramaje < 30) {
            throw new Th_ErrorValidacion("El papel \"$nombre\" necesita el gramaje en números (ej: 90).");
        }
        $id = th_id_para($fila, $nombre . '-' . $gramaje, $ids);
        $ids[] = $id;
        $papeles[] = [
            'id' => $id,
            'nombre' => $nombre,
            'gramaje' => $gramaje,
            'comparacion' => th_texto((string) ($fila['comparacion'] ?? ''), 120, "Comparación de \"$nombre\""),
        ];
    }
    if (count($papeles) > 40) {
        throw new Th_ErrorValidacion('Hay demasiados papeles (máximo 40).');
    }

    $opcionesValidas = [
        'tamano' => array_column($tamanos, 'id'),
        'faz' => ['simple', 'doble'],
        'color' => ['bn', 'color'],
        'papel' => array_column($papeles, 'id'),
    ];

    // Trabajos
    $trabajos = [];
    $ids = [];
    foreach (is_array($c['trabajos'] ?? null) ? $c['trabajos'] : [] as $fila) {
        if (!is_array($fila)) {
            continue;
        }
        $nombre = th_texto((string) ($fila['nombre'] ?? ''), 40, 'Nombre del trabajo de la calculadora');
        if ($nombre === '') {
            throw new Th_ErrorValidacion('Un trabajo de la calculadora se quedó sin nombre.');
        }
        $precioId = (string) ($fila['precioId'] ?? '');
        if (!in_array($precioId, $idsPrecios, true)) {
            throw new Th_ErrorValidacion("El trabajo \"$nombre\" de la calculadora no tiene un precio de la lista (o usaba uno que se borró). Elegí uno en la pantalla Calculadora.");
        }
        $icono = (string) ($fila['icono'] ?? '');
        if (!in_array($icono, TH_ICONOS_CALC, true)) {
            $icono = 'otro';
        }
        $cantidades = [];
        foreach (is_array($fila['cantidades'] ?? null) ? $fila['cantidades'] : [] as $n) {
            $n = th_entero($n, 1, 9999);
            if ($n !== null && !in_array($n, $cantidades, true)) {
                $cantidades[] = $n;
            }
        }
        sort($cantidades);
        $cantidades = array_slice($cantidades, 0, 8);

        $pasos = [];
        foreach (TH_PASOS_CALC as $paso) {
            $ops = [];
            $vistos = [];
            $hayDefecto = false;
            $crudas = $fila['pasos'][$paso] ?? null;
            foreach (is_array($crudas) ? $crudas : [] as $o) {
                if (!is_array($o)) {
                    continue;
                }
                $oid = (string) ($o['id'] ?? '');
                // Si la opción ya no existe (ej: se borró ese tamaño), se descarta sola.
                if (!in_array($oid, $opcionesValidas[$paso], true) || in_array($oid, $vistos, true)) {
                    continue;
                }
                $vistos[] = $oid;
                $precioOpcion = (string) ($o['precioId'] ?? '');
                $op = [
                    'id' => $oid,
                    'recargo' => th_entero($o['recargo'] ?? 0, -90, 500, 0),
                    'precioId' => in_array($precioOpcion, $idsPrecios, true) ? $precioOpcion : '',
                ];
                if (!empty($o['defecto']) && !$hayDefecto) {
                    $op['defecto'] = true;
                    $hayDefecto = true;
                }
                $ops[] = $op;
            }
            $pasos[$paso] = $ops;
        }

        $id = th_id_para($fila, $nombre, $ids);
        $ids[] = $id;
        $trabajos[] = [
            'id' => $id,
            'nombre' => $nombre,
            'icono' => $icono,
            'precioId' => $precioId,
            'entrega' => th_texto((string) ($fila['entrega'] ?? ''), 60, "Tiempo de entrega de \"$nombre\""),
            'cuentaPaginas' => !empty($fila['cuentaPaginas']),
            'cantidades' => $cantidades,
            'cantidadInicial' => th_entero($fila['cantidadInicial'] ?? 1, 1, 9999, 1),
            'pasos' => $pasos,
        ];
    }
    if (count($trabajos) > 30) {
        throw new Th_ErrorValidacion('Hay demasiados trabajos en la calculadora (máximo 30).');
    }

    // Atajos
    $atajos = [];
    $ids = [];
    $porId = array_column($trabajos, null, 'id');
    foreach (is_array($c['atajos'] ?? null) ? $c['atajos'] : [] as $fila) {
        if (!is_array($fila)) {
            continue;
        }
        $nombre = th_texto((string) ($fila['nombre'] ?? ''), 40, 'Nombre del atajo');
        if ($nombre === '') {
            throw new Th_ErrorValidacion('Un atajo de la calculadora se quedó sin nombre.');
        }
        $trabajo = (string) ($fila['trabajo'] ?? '');
        if (!isset($porId[$trabajo])) {
            throw new Th_ErrorValidacion("El atajo \"$nombre\" usa un trabajo que ya no está en la calculadora. Elegí otro.");
        }
        $opciones = [];
        $pedidas = is_array($fila['opciones'] ?? null) ? $fila['opciones'] : [];
        foreach (TH_PASOS_CALC as $paso) {
            $elegida = (string) ($pedidas[$paso] ?? '');
            if (in_array($elegida, array_column($porId[$trabajo]['pasos'][$paso], 'id'), true)) {
                $opciones[$paso] = $elegida;
            }
        }
        $id = th_id_para($fila, $nombre, $ids);
        $ids[] = $id;
        $atajos[] = [
            'id' => $id,
            'nombre' => $nombre,
            'trabajo' => $trabajo,
            'cantidad' => th_entero($fila['cantidad'] ?? 1, 1, 9999, 1),
            'opciones' => (object) $opciones,
        ];
    }
    if (count($atajos) > 12) {
        throw new Th_ErrorValidacion('Hay demasiados atajos (máximo 12).');
    }

    return ['tamanos' => $tamanos, 'papeles' => $papeles, 'trabajos' => $trabajos, 'atajos' => $atajos];
}

/* ===== Checklist de archivo y preguntas frecuentes ===== */
function th_validar_ayuda($a): array {
    if (!is_array($a)) {
        $a = [];
    }
    $checklist = [];
    foreach (is_array($a['checklist'] ?? null) ? $a['checklist'] : [] as $txt) {
        $txt = th_texto(is_scalar($txt) ? (string) $txt : '', 160, 'Punto del checklist');
        if ($txt !== '') {
            $checklist[] = $txt;
        }
    }
    if (count($checklist) > 20) {
        throw new Th_ErrorValidacion('El checklist tiene demasiados puntos (máximo 20).');
    }
    $preguntas = [];
    foreach (is_array($a['preguntas'] ?? null) ? $a['preguntas'] : [] as $fila) {
        if (!is_array($fila)) {
            continue;
        }
        $pregunta = th_texto((string) ($fila['pregunta'] ?? ''), 160, 'Pregunta');
        $respuesta = th_texto((string) ($fila['respuesta'] ?? ''), 1000, "Respuesta de \"$pregunta\"");
        if ($pregunta === '' && $respuesta === '') {
            continue;
        }
        if ($pregunta === '' || $respuesta === '') {
            throw new Th_ErrorValidacion('Cada pregunta frecuente necesita la pregunta y la respuesta.');
        }
        $preguntas[] = ['pregunta' => $pregunta, 'respuesta' => $respuesta];
    }
    if (count($preguntas) > 30) {
        throw new Th_ErrorValidacion('Hay demasiadas preguntas (máximo 30).');
    }
    return ['checklist' => $checklist, 'preguntas' => $preguntas];
}
