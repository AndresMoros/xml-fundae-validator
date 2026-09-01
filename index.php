<?php
// No mostrar nunca la pantalla de error nativa de PHP: todo pasa por nuestros
// propios cuadros de aviso en el HTML.
ini_set('display_errors', '0');
error_reporting(E_ALL);

require(__DIR__ . '/templates/head.php');
require_once __DIR__ . '/scripts/funciones_encuestas.php';

$error = null;
$mensaje_ok = null;
$ruta_generada = null;
$validacion = null;

// Convierte cualquier warning/notice de PHP (ej. "Undefined array key") en una
// excepción capturable por nuestro try/catch, en vez de imprimirse suelto.
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

// Red de seguridad: captura errores fatales que ni el try/catch ni
// set_error_handler pueden interceptar (parse errors, memoria agotada,
// función inexistente, etc.) y los muestra igual dentro de un <div> propio.
register_shutdown_function(function () {
    $e = error_get_last();
    $fatales = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if ($e && in_array($e['type'], $fatales, true)) {
        echo '<div class="alert alert-error">'
            . '<strong>Error fatal:</strong> ' . htmlspecialchars($e['message'])
            . '<br><code>' . htmlspecialchars($e['file']) . ':' . $e['line'] . '</code>'
            . '</div>';
    }
});

$tipo_actual = $_GET['tipo_encuesta'] ?? null;

// El campo "archivo" solo existe en el 2º formulario (el de datos del curso).
if (isset($_POST['archivo'])) {
    try {
        // Si se arrastró un CSV, se procesa ese fichero tal cual, en vez de
        // buscarlo por nombre en la carpeta cuestionarios/.
        if (!empty($_FILES['archivo_subido']) && $_FILES['archivo_subido']['error'] === UPLOAD_ERR_OK) {
            $nombre_subido = basename($_FILES['archivo_subido']['name']);
            if (strtolower(pathinfo($nombre_subido, PATHINFO_EXTENSION)) !== 'csv') {
                throw new Exception('El archivo arrastrado debe ser un CSV (.csv).');
            }
            $_POST['csv_ruta'] = $_FILES['archivo_subido']['tmp_name'];
            $_POST['archivo'] = pathinfo($nombre_subido, PATHINFO_FILENAME);
        }

        switch ($tipo_actual) {
            case 'tf':
                $ruta_generada = procesar_encuesta_teleformacion($_POST);
                break;
            case 'presencial':
                $ruta_generada = procesar_encuesta_presencial($_POST);
                break;
            case 'mix':
                $ruta_generada = procesar_encuesta_mixta($_POST);
                break;
            default:
                throw new Exception('Tipo de encuesta no reconocido.');
        }
        $mensaje_ok = 'El XML se ha generado correctamente.';
        $validacion = validar_xml_contra_xsd($ruta_generada);
    } catch (Throwable $e) {
        // Incluye tanto nuestras Exception "de negocio" como los warnings/notices
        // convertidos arriba. $e->getMessage() es justo el texto útil para depurar.
        $error = $e->getMessage();
    }
}

restore_error_handler();
?>
<!DOCTYPE html>
<html>
<body>
    <h1>Encuestas Teleformación</h1>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <strong>Error:</strong> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($mensaje_ok): ?>

        <!-- Pantalla de éxito: sustituye a los formularios -->
        <div class="alert alert-success">
            <p><?= htmlspecialchars($mensaje_ok) ?></p>
            <p><code><?= htmlspecialchars($ruta_generada) ?></code></p>
        </div>

        <?php if ($validacion && $validacion['valido']): ?>
            <div class="alert alert-success">
                <strong>Validación XSD:</strong> el XML generado es válido contra el esquema <code>CuestionarioEvaluacion_2025.xsd</code>.
            </div>
        <?php elseif ($validacion): ?>
            <div class="alert alert-error">
                <strong>Validación XSD:</strong> el XML se generó, pero NO valida contra el esquema.
                <ul>
                    <?php foreach ($validacion['errores'] as $error_validacion): ?>
                        <li><?= htmlspecialchars($error_validacion) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <a class="btn" href="?">Volver al cuestionario</a>

    <?php else: ?>

        <form action="" method="get" id="selector-tipo">
            <h2>Escoge el tipo de encuesta a validar</h2>
            <label>Encuesta Teleformación</label>
            <input type="radio" name="tipo_encuesta" value="tf" <?= $tipo_actual === 'tf' ? 'checked' : '' ?>><br />
            <label>Encuesta Presencial</label>
            <input type="radio" name="tipo_encuesta" value="presencial" <?= $tipo_actual === 'presencial' ? 'checked' : '' ?>><br />
            <label>Encuesta cursos MIX</label>
            <input type="radio" name="tipo_encuesta" value="mix" <?= $tipo_actual === 'mix' ? 'checked' : '' ?>><br />
        </form>

        <script>
        document.querySelectorAll('#selector-tipo input[name="tipo_encuesta"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                this.form.submit();
            });
        });
        </script>

        <?php
        if ($tipo_actual) {
            switch ($tipo_actual) {
                case 'tf':
                    include(__DIR__ . '/templates/form_tf.php');
                    break;
                case 'presencial':
                    include(__DIR__ . '/templates/form_presenciales.php');
                    break;
                case 'mix':
                    include(__DIR__ . '/templates/form_mix.php');
                    break;
            }
        }
        ?>

        <script>
        // Arrastrar y soltar un CSV sobre el campo "Archivo": se adjunta el
        // fichero real (input oculto) para procesar el documento soltado, y se
        // rellena el nombre visible sin la extensión .csv.
        (function () {
            var campo = document.querySelector('input[name="archivo"]');
            if (!campo) return;
            var subido = document.querySelector('input[name="archivo_subido"]');

            ['dragenter', 'dragover'].forEach(function (tipo) {
                campo.addEventListener(tipo, function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    campo.classList.add('drop-over');
                });
            });

            campo.addEventListener('dragleave', function (e) {
                e.preventDefault();
                e.stopPropagation();
                campo.classList.remove('drop-over');
            });

            campo.addEventListener('drop', function (e) {
                e.preventDefault();
                e.stopPropagation();
                campo.classList.remove('drop-over');
                var fichero = e.dataTransfer.files && e.dataTransfer.files[0];
                if (!fichero) return;
                campo.value = fichero.name.replace(/\.csv$/i, '');
                if (subido && window.DataTransfer) {
                    var dt = new DataTransfer();
                    dt.items.add(fichero);
                    subido.files = dt.files;
                }
            });
        })();
        </script>

    <?php endif; ?>
</body>
</html>
