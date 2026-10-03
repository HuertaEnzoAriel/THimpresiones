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
    $categoriasTrabajos = th_validar_categorias($entrada['categoriasTrabajos'] ?? [], 'categoría de trabajos');
    $idsCatTrabajos = array_column($categoriasTrabajos, 'id');
    $trabajos = th_validar_trabajos($entrada['trabajos'] ?? [], $idsCatTrabajos);

    return [
        'negocio' => $negocio,
        'categoriasPrecios' => $categoriasPrecios,
        'precios' => $precios,
        'categoriasTrabajos' => $categoriasTrabajos,
        'trabajos' => $trabajos,
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

    return [
        'nombre' => $nombre,
        'whatsapp' => $whatsapp,
        'telefono' => $telefono,
        'email' => $email,
        'instagram' => $instagram,
        'horarios' => $horarios,
    ];
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

function th_validar_trabajos($lista, array $idsCategoriasValidas): array {
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
        $imagen = (string) ($fila['imagen'] ?? '');
        if ($imagen === '' || !th_ruta_imagen_valida($imagen)) {
            throw new Th_ErrorValidacion("A \"$titulo\" le falta una foto. Subí una antes de guardar.");
        }
        $visible = !isset($fila['visible']) || $fila['visible'] !== false;

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
            'visible' => $visible,
        ];
    }
    return $resultado;
}
