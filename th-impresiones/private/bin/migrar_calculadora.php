<?php
/* Migración para la calculadora visual. Se corre UNA vez en el servidor
 * después del git pull:
 *
 *     sudo -u www-data php private/bin/migrar_calculadora.php
 *
 * Solo AGREGA lo que falta en public/data/datos.json: no cambia precios,
 * trabajos de la galería, fotos ni datos de contacto.
 *   - calculadora (trabajos, tamaños, papeles, atajos) y ayuda (checklist y
 *     preguntas), tomados de calculadora-inicial.json. Los trabajos de la
 *     calculadora que usan un precio que ya no existe se dejan afuera.
 *   - horariosSemana: se arma leyendo los horarios en texto que ya tenés
 *     (ej: "Lunes a viernes" / "9:00 a 13:00 y 16:00 a 20:00"). Si algún
 *     renglón no se entiende, no se toca y se avisa para cargarlo en el panel.
 *   - calcTrabajo en la galería, solo para los trabajos de fábrica.
 * Antes de escribir hace una copia en private/backups/ (como el panel) y
 * valida todo con las mismas reglas que el panel. Si se corre dos veces,
 * la segunda no cambia nada. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../validacion.php';

$inicial = json_decode((string) file_get_contents(__DIR__ . '/calculadora-inicial.json'), true);
if (!is_array($inicial)) {
    fwrite(STDERR, "No se pudo leer calculadora-inicial.json.\n");
    exit(1);
}

function mig_sin_acentos(string $s): string {
    $s = mb_strtolower(trim($s));
    return strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
}

/* "Lunes a viernes" -> [lun..vie]; "Sábados" -> [sab]; null si no se entiende. */
function mig_dias(string $texto): ?array {
    $claves = ['lun' => 'lunes', 'mar' => 'martes', 'mie' => 'miercoles', 'jue' => 'jueves', 'vie' => 'viernes', 'sab' => 'sabado', 'dom' => 'domingo'];
    $orden = array_keys($claves);
    $buscar = function (string $palabra) use ($claves): ?string {
        $palabra = rtrim(trim($palabra), 's');
        foreach ($claves as $k => $nombre) {
            if ($palabra === rtrim($nombre, 's') || $palabra === $k) {
                return $k;
            }
        }
        return null;
    };
    $t = mig_sin_acentos($texto);
    if (preg_match('/^(\w+)\s+(?:a|al)\s+(\w+)$/u', $t, $m)) {
        $desde = $buscar($m[1]);
        $hasta = $buscar($m[2]);
        if ($desde === null || $hasta === null) {
            return null;
        }
        $i = array_search($desde, $orden, true);
        $j = array_search($hasta, $orden, true);
        return $j >= $i ? array_slice($orden, $i, $j - $i + 1) : null;
    }
    $dias = [];
    foreach (preg_split('/\s*(?:,|\by\b)\s*/u', $t) as $parte) {
        $k = $buscar($parte);
        if ($k === null) {
            return null;
        }
        $dias[] = $k;
    }
    return $dias ?: null;
}

/* "9:00 a 13:00 y 16:00 a 20:00" -> turnos; "Cerrado" -> []; null si no se entiende. */
function mig_turnos(string $texto): ?array {
    $t = mig_sin_acentos($texto);
    if ($t === 'cerrado') {
        return [];
    }
    if (!preg_match_all('/(\d{1,2})(?:[:.](\d{2}))?\s*(?:hs?\.?)?\s*(?:a|-|–|hasta)\s*(\d{1,2})(?:[:.](\d{2}))?/u', $t, $m, PREG_SET_ORDER)) {
        return null;
    }
    $turnos = [];
    foreach ($m as $x) {
        $turnos[] = [
            'desde' => sprintf('%02d:%02d', (int) $x[1], (int) ($x[2] ?? 0)),
            'hasta' => sprintf('%02d:%02d', (int) $x[3], (int) ($x[4] ?? 0)),
        ];
    }
    return $turnos;
}

function mig_horario_semana(array $horarios): ?array {
    if (!$horarios) {
        return null;
    }
    $semana = [];
    foreach (['lun', 'mar', 'mie', 'jue', 'vie', 'sab', 'dom'] as $k) {
        $semana[$k] = [];
    }
    foreach ($horarios as $fila) {
        $dias = mig_dias((string) ($fila['dia'] ?? ''));
        $turnos = mig_turnos((string) ($fila['horas'] ?? ''));
        if ($dias === null || $turnos === null) {
            return null;
        }
        foreach ($dias as $k) {
            $semana[$k] = $turnos;
        }
    }
    $resultado = [];
    foreach ($semana as $k => $turnos) {
        $resultado[] = ['dia' => $k, 'turnos' => $turnos];
    }
    return $resultado;
}

$avisos = [];
$cambios = [];

$resultado = th_con_lock_datos(function () use ($inicial, &$avisos, &$cambios) {
    $actual = th_leer_datos();
    if ($actual === null) {
        return ['error' => 'No se pudo leer public/data/datos.json.'];
    }
    $datos = $actual['datos'];
    $idsPrecios = array_column($datos['precios'] ?? [], 'id');

    // 1) Calculadora
    if (empty($datos['calculadora']['trabajos'])) {
        $calc = $inicial['calculadora'];
        $calc['trabajos'] = array_values(array_filter($calc['trabajos'], function ($t) use ($idsPrecios, &$avisos) {
            if (!in_array($t['precioId'], $idsPrecios, true)) {
                $avisos[] = "No se agregó \"{$t['nombre']}\" a la calculadora: no encontré su precio \"{$t['precioId']}\" en tu lista. Agregalo desde el panel si lo querés.";
                return false;
            }
            return true;
        }));
        $idsCalc = array_column($calc['trabajos'], 'id');
        $calc['atajos'] = array_values(array_filter($calc['atajos'], fn($a) => in_array($a['trabajo'], $idsCalc, true)));
        $datos['calculadora'] = $calc;
        $cambios[] = 'Calculadora: ' . count($calc['trabajos']) . ' trabajos, ' . count($calc['tamanos']) . ' tamaños, ' . count($calc['papeles']) . ' papeles, ' . count($calc['atajos']) . ' atajos.';
    }

    // 2) Checklist y preguntas
    if (!isset($datos['ayuda'])) {
        $datos['ayuda'] = $inicial['ayuda'];
        $cambios[] = 'Checklist (' . count($inicial['ayuda']['checklist']) . ' puntos) y preguntas frecuentes (' . count($inicial['ayuda']['preguntas']) . ').';
    }

    // 3) Horario semanal a partir del texto actual
    if (!isset($datos['negocio']['horariosSemana'])) {
        $semana = mig_horario_semana($datos['negocio']['horarios'] ?? []);
        if ($semana === null) {
            $avisos[] = 'No pude entender tus horarios en texto: quedan como están (sin cartel Abierto/Cerrado). Cargalos día por día en el panel, pantalla "Contacto y horarios".';
        } else {
            $datos['negocio']['horariosSemana'] = $semana;
            $cambios[] = 'Horario semanal armado desde tus horarios actuales (revisalo en el panel).';
        }
    }

    // 4) Galería -> calculadora (solo trabajos de fábrica que no tengan vínculo)
    $vinculados = 0;
    foreach ($datos['trabajos'] ?? [] as $i => $t) {
        $destino = $inicial['galeriaACalculadora'][$t['id'] ?? ''] ?? '';
        if (!isset($t['calcTrabajo']) && $destino !== '') {
            $datos['trabajos'][$i]['calcTrabajo'] = $destino;
            $vinculados++;
        }
    }
    if ($vinculados) {
        $cambios[] = "Galería: $vinculados trabajos vinculados a \"Pedir algo parecido\".";
    }

    if (!$cambios) {
        return ['nada' => true];
    }

    try {
        $validados = th_validar_datos($datos);
    } catch (Th_ErrorValidacion $e) {
        return ['error' => 'No se guardó nada: ' . $e->getMessage()];
    }
    th_backup_datos();
    if (!th_escribir_datos_atomico($validados)) {
        return ['error' => 'No se pudo escribir datos.json (revisá permisos). No se cambió nada.'];
    }
    return ['ok' => true];
});

if (!empty($resultado['error'])) {
    fwrite(STDERR, $resultado['error'] . "\n");
    exit(1);
}
if (!empty($resultado['nada'])) {
    echo "No había nada para migrar: datos.json ya tiene todo.\n";
    exit(0);
}
echo "Listo. Se hizo una copia del datos.json anterior en private/backups/.\n";
foreach ($cambios as $c) {
    echo "  + $c\n";
}
foreach ($avisos as $a) {
    echo "  ! $a\n";
}
