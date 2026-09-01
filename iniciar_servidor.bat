<?php
error_reporting(0);
ini_set('display_errors', 0);

$tipo_encuesta = isset($_GET['tipo_encuesta']) ? $_GET['tipo_encuesta'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/scripts/funciones_encuestas.php';

    try {
        $resultado = procesar_encuesta_generica($_POST, $_FILES);
        $mensaje_exito = "Archivo XML generado correctamente: " . htmlspecialchars($resultado['archivo']);
        $ruta_xml = $resultado['ruta_completa'];

        // Validar contra XSD
        $xsd_path = __DIR__ . '/scripts/CuestionarioEvaluacion_2025.xsd';
        $validacion = validar_xml_contra_xsd($ruta_xml, $xsd_path);

        if ($validacion['valido']) {
            $mensaje_validacion = '<div class="alert alert-success">XML valido contra el esquema XSD</div>';
        } else {
            $mensaje_validacion = '<div class="alert alert-error">Errores de validacion XSD:<br>' . implode('<br>', array_map('htmlspecialchars', $validacion['errores'])) . '</div>';
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Si no hay tipo de encuesta, mostrar selector
if (empty($tipo_encuesta)) {
    include __DIR__ . '/templates/head.php';
    ?>
    <body>
        <div class="container">
            <h1>Seleccionar tipo de encuesta</h1>
            <div class="card">
                <ul>
                    <li><a href="?tipo_encuesta=tf">Teleformacion</a></li>
                    <li><a href="?tipo_encuesta=presencial">Presencial</a></li>
                    <li><a href="?tipo_encuesta=mix">Mixta</a></li>
                </ul>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Cargar el formulario correspondiente
$formulario = __DIR__ . "/templates/form_{$tipo_encuesta}.php";
if (!file_exists($formulario)) {
    die("Tipo de encuesta no valido.");
}

include __DIR__ . '/templates/head.php';
include $formulario;
?>
