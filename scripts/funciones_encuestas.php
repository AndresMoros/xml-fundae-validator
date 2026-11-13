<?php

// ============================================================
// RUTAS BASE
// ============================================================
$DIRECTORIO_RAIZ = dirname(__DIR__);  // Sube un nivel desde scripts/
$DIRECTORIO_CSV = $DIRECTORIO_RAIZ . '/cuestionarios/';
$DIRECTORIO_PROCESADOS = $DIRECTORIO_RAIZ . '/procesados/';
$DIRECTORIO_XSD = __DIR__ . '/CuestionarioEvaluacion_2025.xsd';

function fix_encoding($str) {
    if (!mb_check_encoding($str, 'UTF-8')) {
        // No es UTF-8 valido -> asumimos Latin-1 y lo convertimos
        return mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1');
    }
    return $str;
}

/**
 * Detecta el delimitador de una linea CSV contando, fuera de comillas dobles,
 * las apariciones de los separadores candidatos. Gana el mas frecuente.
 */
function detectar_delimitador_csv(string $linea): string
{
    $candidatos = [';', ',', "\t", '|'];
    $conteos = array_fill_keys($candidatos, 0);
    $dentro_comillas = false;
    $len = strlen($linea);

    for ($i = 0; $i < $len; $i++) {
        $c = $linea[$i];
        if ($c === '"') {
            $dentro_comillas = !$dentro_comillas;
            continue;
        }
        if ($dentro_comillas) {
            continue;
        }
        if (isset($conteos[$c])) {
            $conteos[$c]++;
        }
    }

    $mejor = ';';
    $max = 0;
    foreach ($candidatos as $d) {
        if ($conteos[$d] > $max) {
            $max = $conteos[$d];
            $mejor = $d;
        }
    }
    return $mejor;
}

/**
 * Lee el CSV y lo devuelve como array de filas, ya con la codificacion corregida.
 * El delimitador se detecta automaticamente (; , tab o |) mirando la cabecera.
 * Lanza Exception (en vez de generar un warning) si el fichero no existe.
 */
function leer_csv_encuesta(string $ruta_csv): array
{
    global $DIRECTORIO_CSV;

    if (!is_file($ruta_csv)) {
        throw new Exception("No se ha encontrado el fichero CSV: " . basename($ruta_csv));
    }

    $fp = fopen($ruta_csv, "r");
    if ($fp === false) {
        throw new Exception("No se ha podido abrir el fichero CSV: " . basename($ruta_csv));
    }

    // Determina el separador a partir de la primera linea con contenido.
    $delimitador = ';';
    while (($linea = fgets($fp)) !== false) {
        $linea = trim($linea);
        if ($linea !== '') {
            $delimitador = detectar_delimitador_csv($linea);
            break;
        }
    }
    rewind($fp);

    $filas = [];
    // $escape explicito: PHP 8.4 avisa (deprecacion) si se omite y se usa el valor por defecto.
    while (($data = fgetcsv($fp, 10000, $delimitador, '"', '')) !== false) {
        $filas[] = array_map('fix_encoding', $data);
    }
    fclose($fp);

    if (count($filas) < 2) {
        throw new Exception("El CSV esta vacio o solo tiene la fila de cabecera.");
    }

    return $filas;
}

/**
 * Crea (si hace falta) la carpeta de destino del expediente y devuelve su ruta.
 * Lanza Exception si no se puede crear.
 */
function preparar_carpeta_destino(string $empresa, string $expediente): string
{
    global $DIRECTORIO_PROCESADOS;

    $ruta = $DIRECTORIO_PROCESADOS . "$empresa/$expediente";
    if (!file_exists($ruta) && !mkdir($ruta, 0777, true) && !is_dir($ruta)) {
        throw new Exception("No se ha podido crear la carpeta de destino: $ruta");
    }
    return $ruta;
}

/**
 * Vuelca las lineas XML a fichero. Lanza Exception si no se puede escribir,
 * y avisa (sin morir) si el fichero ya existia y no se ha sobreescrito.
 */
function escribir_xml_encuesta(string $ruta_completa, array $lineas): void
{
    if (file_exists($ruta_completa)) {
        throw new Exception("Ya existe un XML para este curso en: $ruta_completa (no se ha sobreescrito).");
    }
    $file = fopen($ruta_completa, "w");
    if ($file === false) {
        throw new Exception("No se ha podido crear el fichero XML en: $ruta_completa");
    }
    foreach ($lineas as $linea) {
        fwrite($file, $linea . PHP_EOL);
    }
    fclose($file);
}

/**
 * Valida un XML generado contra el esquema CuestionarioEvaluacion_2025.xsd.
 * Devuelve un array con 'valido' (bool) y 'errores' (lista de mensajes).
 * No lanza excepciones: el propio resultado es el estado de la validacion.
 */
function validar_xml_contra_xsd(string $ruta_xml): array
{
    global $DIRECTORIO_XSD;

    $resultado = ['valido' => false, 'errores' => []];

    if (!is_file($DIRECTORIO_XSD)) {
        $resultado['errores'][] = 'No se ha encontrado el esquema XSD: ' . basename($DIRECTORIO_XSD);
        return $resultado;
    }

    if (!is_file($ruta_xml)) {
        $resultado['errores'][] = 'No se ha encontrado el XML generado para validar: ' . basename($ruta_xml);
        return $resultado;
    }

    $doc = new DOMDocument();
    if (!@$doc->load($ruta_xml)) {
        $resultado['errores'][] = 'El XML generado no esta bien formado y no se ha podido cargar.';
        return $resultado;
    }

    $anterior = libxml_use_internal_errors(true);
    libxml_clear_errors();

    $valido = $doc->schemaValidate($DIRECTORIO_XSD);
    foreach (libxml_get_errors() as $error) {
        $resultado['errores'][] = trim($error->message) . ' (linea ' . $error->line . ')';
    }

    libxml_clear_errors();
    libxml_use_internal_errors($anterior);

    $resultado['valido'] = $valido;
    return $resultado;
}

/**
 * Construye BloqueI + BloqueII, comunes a los tres tipos de encuesta.
 */
function construir_bloques_I_II(array $fila, string $expediente, string $accion, string $grupo): array
{
    $l = [];
    $l[] = ' <cuestionario>';
    $l[] = '  <BloqueI>';
    $l[] = "   <Expediente>$expediente</Expediente>";
    $l[] = "   <Num_accion>$accion</Num_accion>";
    $l[] = "   <Num_grupo>$grupo</Num_grupo>";
    $l[] = '  </BloqueI>';
    $l[] = '  <BloqueII>';

    $edad = $fila[1] ?? '';
    $l[] = "  <II_1>$edad</II_1>";
    $sexo = substr($fila[2] ?? '', 0, 1);
    $l[] = "  <II_2>$sexo</II_2>";
    $exp_num = explode(".", $fila[3] ?? '');
    $num_titulacion = $exp_num[0] ?? '';
    $l[] = "  <II_3>$num_titulacion</II_3>";
    $l[] = "  <II_31>";
    $l[] = "   <II_31_1>99</II_31_1>";
    $l[] = "   <otratitulacion>99</otratitulacion>";
    $l[] = "  </II_31>";

    $sit_laboral = intval(substr($fila[5] ?? '0', 0, 1));
    $l[] = "  <II_4>$sit_laboral</II_4>";
    $l[] = "  <II_5>";
    $prov = strtoupper(trim($fila[6] ?? ''));
    if ($prov === '' || strpos($prov, '99') === 0) {
        $prov = '99';
    }
    $l[] = $sit_laboral == 1
        ? "    <desempleado>$prov</desempleado>"
        : "    <ocupado>$prov</ocupado>";
    $l[] = "  </II_5>";

    $medio = intval(substr($fila[7] ?? '0', 0, 1));
    if ($medio == 0) {
        $medio = 99;
    }
    $otro_medio = empty($fila[8]) ? "Recomendado" : $fila[8];
    if ($medio == 6) {
        $l[] = "  <II_6>";
        $l[] = "   <existencia>6</existencia>";
        $l[] = "   <otra_existencia>$otro_medio</otra_existencia>";
        $l[] = "  </II_6>";
    } else {
        $l[] = "  <II_6>";
        $l[] = "   <existencia>$medio</existencia>";
        $l[] = "  </II_6>";
    }

    if ($sit_laboral != 1) {
        $categoria = intval(substr($fila[9] ?? '0', 0, 1));
        $otra_categoria = (string)($fila[10] ?? '');
        $jornada = intval(substr($fila[11] ?? '0', 0, 1));
        $jornada_pct = intval(substr($fila[12] ?? '0', 0, 1));
        $nempl = intval(substr($fila[13] ?? '0', 0, 1));

        if ($categoria == 6) {
            $l[] = "  <II_7>";
            $l[] = "    <categoria>6</categoria>";
            $l[] = strlen($otra_categoria) == 0
                ? "    <otra_categoria>No especificado</otra_categoria>"
                : "    <otra_categoria>$otra_categoria</otra_categoria>";
            $l[] = "  </II_7>";
        } else {
            $l[] = "  <II_7>";
            $l[] = "    <categoria>" . ($categoria != 0 ? $categoria : 1) . "</categoria>";
            $l[] = "  </II_7>";
        }

        // II_8 (horario del curso) es obligatorio para ocupados segun FUNDAE,
        // aunque el XSD lo marque minOccurs=0. 1=dentro, 2=fuera, 3=ambas,
        // 99=no contesta (cubre vacio y "9. No contesta").
        if ($jornada == 1 || $jornada == 3) {
            $l[] = "  <II_8>$jornada</II_8>";
            $pct = ($jornada_pct >= 1 && $jornada_pct <= 3) ? $jornada_pct : 99;
            $l[] = "   <II_8_1>$pct</II_8_1>";
        } elseif ($jornada == 2) {
            $l[] = "  <II_8>2</II_8>";
        } else {
            $l[] = "  <II_8>99</II_8>";
        }

        $l[] = "  <II_9>" . ($nempl == 0 ? 1 : $nempl) . "</II_9>";
    }

    $l[] = '  </BloqueII>';
    return $l;
}

/**
 * Normaliza a 0/1 los valores tipo t_sino que en el CSV pueden venir como "2".
 */
function normalizar_sino($valor): int
{
    $v = intval(substr((string)$valor, 0, 1));
    return $v == 2 ? 0 : $v;
}

/**
 * Construye el inicio de BloqueIII (III_1 a III_3), que debe ir antes de III_4.
 */
function construir_inicio_bloque_III(array $fila): array
{
    $l = [];
    $l[] = "   <III_1_1>" . ($fila[14] ?? '') . "</III_1_1>";
    $l[] = "   <III_1_2>" . ($fila[15] ?? '') . "</III_1_2>";
    $l[] = "   <III_2_1>" . ($fila[16] ?? '') . "</III_2_1>";
    $l[] = "   <III_2_2>" . ($fila[17] ?? '') . "</III_2_2>";
    $l[] = "   <III_3_1>" . ($fila[18] ?? '') . "</III_3_1>";
    $pregunta_3_2 = ($fila[19] ?? '') == 0 ? 4 : ($fila[19] ?? '');
    $l[] = "   <III_3_2>$pregunta_3_2</III_3_2>";
    return $l;
}

/**
 * Construye el final de BloqueIII (III_6 a III_11) + fecha_cumplimentacion + cierre
 * de cuestionario. $incluir_6 y $incluir_7 controlan, por separado, si van III_6 y
 * III_7 (opcionales segun modalidad).
 */
function construir_final_bloque_III(array $fila, bool $incluir_6, bool $incluir_7, string $fecha_form): array
{
    $l = [];
    if ($incluir_6) {
        $l[] = "   <III_6_1>" . ($fila[26] ?? '') . "</III_6_1>";
        $l[] = "   <III_6_2>" . ($fila[27] ?? '') . "</III_6_2>";
    }
    if ($incluir_7) {
        $l[] = "   <III_7_1>" . ($fila[28] ?? '') . "</III_7_1>";
        $l[] = "   <III_7_2>" . ($fila[29] ?? '') . "</III_7_2>";
    }

    $l[] = "   <III_8_1>" . normalizar_sino($fila[30] ?? 0) . "</III_8_1>";
    $l[] = "   <III_8_2>" . normalizar_sino($fila[31] ?? 0) . "</III_8_2>";
    $l[] = "   <III_9_1>" . ($fila[32] ?? '') . "</III_9_1>";
    $l[] = "   <III_9_2>" . ($fila[33] ?? '') . "</III_9_2>";
    $l[] = "   <III_9_3>" . ($fila[34] ?? '') . "</III_9_3>";
    $l[] = "   <III_9_4>" . ($fila[35] ?? '') . "</III_9_4>";
    $l[] = "   <III_9_5>" . ($fila[36] ?? '') . "</III_9_5>";
    $l[] = "   <III_10>" . ($fila[37] ?? '') . "</III_10>";
    $l[] = "   <III_10_1>" . normalizar_sino($fila[38] ?? 0) . "</III_10_1>";
    $l[] = "   <III_11>" . ($fila[39] ?? '') . "</III_11>";
    $l[] = '  </BloqueIII>';

    // La fecha siempre es la ultima columna real de la fila (soporta CSV de 41 o 46 columnas)
    $idx_fecha = count($fila) - 1;
    $fecha = (!isset($fila[$idx_fecha]) || $fila[$idx_fecha] == 0) ? $fecha_form : $fila[$idx_fecha];
    $l[] = "   <fecha_cumplimentacion>$fecha</fecha_cumplimentacion>";
    $l[] = ' </cuestionario>';

    return $l;
}

/** Encuesta modalidad PRESENCIAL (equivalente a encuestas2.php) */
function procesar_encuesta_presencial(array $post): string
{
    return procesar_encuesta_generica($post, function (array $fila) {
        $l = [];
        $l[] = '   <III_4_1>';
        $l[] = "    <III_4_1_Formador>" . ($fila[21] ?? '') . "</III_4_1_Formador>";
        $l[] = '   </III_4_1>';
        $l[] = '   <III_4_2>';
        $l[] = "    <III_4_2_Formador>" . ($fila[23] ?? '') . "</III_4_2_Formador>";
        $l[] = '   </III_4_2>';
        $l[] = "   <III_5_1>" . ($fila[24] ?? '') . "</III_5_1>";
        $l[] = "   <III_5_2>" . ($fila[25] ?? '') . "</III_5_2>";
        return $l;
    }, incluir_6: true, incluir_7: false);
}

/** Encuesta modalidad TELEFORMACION (equivalente a encuestas3.php) */
function procesar_encuesta_teleformacion(array $post): string
{
    return procesar_encuesta_generica($post, function (array $fila) {
        $l = [];
        $l[] = '   <III_4_1>';
        $l[] = "    <III_4_1_Tutor>" . ($fila[21] ?? '') . "</III_4_1_Tutor>";
        $l[] = '   </III_4_1>';
        $l[] = '   <III_4_2>';
        $l[] = "    <III_4_2_Tutor>" . ($fila[23] ?? '') . "</III_4_2_Tutor>";
        $l[] = '   </III_4_2>';
        $l[] = "   <III_5_1>" . ($fila[24] ?? '') . "</III_5_1>";
        $l[] = "   <III_5_2>" . ($fila[25] ?? '') . "</III_5_2>";
        return $l;
    }, incluir_6: false, incluir_7: true);
}

/** Encuesta modalidad MIXTA (equivalente a encuestas4.php) */
function procesar_encuesta_mixta(array $post): string
{
    return procesar_encuesta_generica($post, function (array $fila) {
        $l = [];
        $l[] = '   <III_4_1>';
        $l[] = "    <III_4_1_Formador>" . ($fila[20] ?? '') . "</III_4_1_Formador>";
        $l[] = "    <III_4_1_Tutor>" . ($fila[21] ?? '') . "</III_4_1_Tutor>";
        $l[] = '   </III_4_1>';
        $l[] = '   <III_4_2>';
        $l[] = "    <III_4_2_Formador>" . ($fila[22] ?? '') . "</III_4_2_Formador>";
        $l[] = "    <III_4_2_Tutor>" . ($fila[23] ?? '') . "</III_4_2_Tutor>";
        $l[] = '   </III_4_2>';
        $l[] = "   <III_5_1>" . ($fila[24] ?? '') . "</III_5_1>";
        $l[] = "   <III_5_2>" . ($fila[25] ?? '') . "</III_5_2>";
        return $l;
    }, incluir_6: true, incluir_7: true);
}

/**
 * Motor comun: valida los datos del formulario, lee el CSV, arma el XML fila a fila
 * y lo escribe a disco. $bloque_4_5 es una funcion que anade el III_4_1/III_4_2/III_5
 * propio de cada modalidad. Devuelve la ruta del fichero generado.
 */
function procesar_encuesta_generica(array $post, callable $bloque_4_5, bool $incluir_6, bool $incluir_7): string
{
    global $DIRECTORIO_CSV;

    foreach (['archivo', 'empresa', 'expediente', 'accion', 'grupo', 'fecha'] as $campo) {
        if (empty($post[$campo])) {
            throw new Exception("Falta el campo obligatorio: $campo");
        }
    }

    $fichero = $post['csv_ruta'] ?? ($DIRECTORIO_CSV . trim($post['archivo']) . '.csv');
    $empresa = trim($post['empresa']);
    $expediente = trim($post['expediente']);
    $accion = trim($post['accion']);
    $grupo = trim($post['grupo']);
    $fecha_form = $post['fecha'];
    $curso = "$accion-$grupo.xml";

    $filas = leer_csv_encuesta($fichero);
    $ruta = preparar_carpeta_destino($empresa, $expediente);

    $lineas = ['<?xml version="1.0" encoding="UTF-8"?>', '<cuestionarios>'];
    for ($i = 1; $i < count($filas); $i++) {
        $fila = $filas[$i];
        $lineas = array_merge(
            $lineas,
            construir_bloques_I_II($fila, $expediente, $accion, $grupo),
            ['  <BloqueIII>'],
            construir_inicio_bloque_III($fila),
            $bloque_4_5($fila),
            construir_final_bloque_III($fila, $incluir_6, $incluir_7, $fecha_form)
        );
    }
    $lineas[] = '</cuestionarios>';

    $ruta_completa = "$ruta/$curso";
    escribir_xml_encuesta($ruta_completa, $lineas);

    return $ruta_completa;
}
?>
