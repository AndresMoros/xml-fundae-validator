Sistema PHP para procesar encuestas de calidad desde CSV a XML validable contra esquema XSD.

## Descripción

Aplicacion PHP (compatible con XAMPP o PHP nativo) que convierte archivos CSV con respuestas de cuestionarios de calidad en archivos XML estructurados segun el esquema `CuestionarioEvaluacion_2025.xsd`.

Caracteristicas principales:
- Conversion automatica de CSV a XML
- Soporte para 3 modalidades: Presencial, Mixta y Teleformacion
- Validacion integrada contra XSD
- Deteccion automatica de formato CSV (original/nuevo)
- Interfaz con arrastrar y soltar archivos
- Organizacion automatica por empresa y expediente

## Instalacion

### Requisitos previos

- PHP 7.4 o superior
- Extension DOM (para validacion XSD)
- Opcional: XAMPP, MAMP, o cualquier servidor web

### Clonar el repositorio

```bash
git clone https://github.com/AndresMoros/xml-fundae-validator.git
cd xml-fundae-validator
```

### Configuracion

1.  **Estructura de directorios:**
    ```bash
    mkdir -p cuestionarios procesados
    ```

2.  **Permisos:**
    ```bash
    chmod 755 cuestionarios/ procesados/
    ```

3.  **Servidor web:**
    - Opcion A (XAMPP): Copia a `htdocs/xml-fundae-validator/`
    - Opcion B (PHP nativo): Ejecuta `iniciar_servidor.bat` (Windows) o `php -S localhost:8000` (Linux/Mac)


## Modalidades Soportadas

| Modalidad | Formador | Tutor | III_6 | III_7 | Uso |
|-----------|----------|-------|-------|-------|-----|
| Presencial | Si | No | Si | No | Formacion presencial con formador |
| Mixta | Si | Si | Si | Si | Formacion mixta (presencial + online) |
| Teleformacion | No | Si | No | Si | Formacion 100% online |

## Uso

### 1. Acceso
- Abre `http://localhost/xml-fundae-validator/` (o la URL de tu servidor)
- Veras el selector de modalidad

### 2. Seleccionar modalidad
- Elige: Teleformacion, Presencial o Mixta

### 3. Completar formulario
- **Archivo CSV**: Arrastra y suelta o selecciona el archivo
- **Empresa**: Nombre de la empresa
- **Expediente**: Codigo del expediente (9 caracteres)
- **Accion**: Numero de accion
- **Grupo**: Numero de grupo
- **Fecha**: Fecha de cumplimentacion

### 4. Procesar
- Haz clic en "Procesar"
- Se genera el XML en `procesados/{empresa}/{expediente}/{accion}-{grupo}.xml`
- Se valida automaticamente contra el XSD

### 5. Resultado
- Exito: Mensaje verde con ruta del archivo
- Error: Mensaje rojo con detalles del problema

## Formato CSV

### Formato Original (sin cabecera)
```csv
1;62;2. Varon;2. Titulo...;1. Desempleada;JAEN;6. Otros...
```

### Formato Nuevo (con cabecera)
```csv
Respuesta numero;(8) 1. Edad;(9) 2. Sexo;...
1;62;2. Varon;2. Titulo...
```

### Delimitadores soportados
- `;` (punto y coma) - por defecto
- `,` (coma)
- Tabulador
- `|` (pipe)

El sistema **detecta automaticamente** el delimitador.

## Mapeo CSV → XML

### Columnas principales

| Indice | Campo | Elemento XML |
|--------|-------|--------------|
| 1 | Edad | `<II_1>` |
| 2 | Sexo | `<II_2>` |
| 3 | Titulacion | `<II_3>` |
| 5 | Situacion laboral | `<II_4>` |
| 6 | Provincia | `<II_5>` |
| 7 | Medio conocimiento | `<II_6>` |
| 8 | Otro medio | `<II_6>/<otra_existencia>` |
| 9 | Categoria | `<II_7>` |
| 10 | Otra categoria | `<II_7>/<otra_categoria>` |
| 11 | Jornada | `<II_8>` |
| 12 | % Jornada | `<II_8_1>` |
| 13 | Tamano empresa | `<II_9>` |
| 14-39 | Preguntas Bloque III | `<III_*>` |
| 45 o 49 | Fecha | `<fecha_cumplimentacion>` |

### Estructura XML generada

```xml
<?xml version="1.0" encoding="UTF-8"?>
<cuestionarios>
    <cuestionario>
        <BloqueI>
            <Expediente>F241216AA</Expediente>
            <Num_accion>1</Num_accion>
            <Num_grupo>3</Num_grupo>
        </BloqueI>
        <BloqueII>
            <II_1>62</II_1>
            <II_2>2</II_2>
            <II_3>2</II_3>
            <II_31>
                <II_31_1>99</II_31_1>
                <otratitulacion>99</otratitulacion>
            </II_31>
            <II_4>1</II_4>
            <II_5>
                <desempleado>JAEN</desempleado>
            </II_5>
            <II_6>
                <existencia>6</existencia>
                <otra_existencia>Recomendado</otra_existencia>
            </II_6>
        </BloqueII>
        <BloqueIII>
            <III_1_1>4</III_1_1>
            <III_1_2>4</III_1_2>
            <!-- ... mas preguntas ... -->
            <III_4_1>
                <III_4_1_Formador>4</III_4_1_Formador>
            </III_4_1>
            <III_4_2>
                <III_4_2_Formador>4</III_4_2_Formador>
            </III_4_2>
            <!-- ... -->
            <fecha_cumplimentacion>08/05/2026</fecha_cumplimentacion>
        </BloqueIII>
    </cuestionario>
</cuestionarios>
```

## Validacion

El sistema valida automaticamente el XML generado contra el esquema XSD:

- **DOMDocument::schemaValidate()** - Validacion integrada
- **Mensajes de error detallados** - Muestra exactamente que falla
- **Resultado visual** - Verde (valido) / Rojo (invalido)

### Validacion manual (opcional)

```bash
# Con xmllint (Linux/Mac/Windows con herramientas XML)
xmllint --schema CuestionarioEvaluacion_2025.xsd archivo.xml --noout
```

## Pruebas

### Probar con archivos de ejemplo

1. Coloca un CSV de prueba en `cuestionarios/`
2. Accede a `http://localhost/xml-fundae-validator/`
3. Selecciona una modalidad y completa el formulario
4. Verifica el resultado en `procesados/{empresa}/{expediente}/`

### Verificar estructura XML

El sistema muestra:
- Archivo XML generado correctamente + ruta
- Validacion XSD exitosa (mensaje verde)
- Errores de validacion (mensaje rojo con lista)

## Solucion de Problemas

### Error: "Faltan parametros requeridos"
- Verifica que todos los campos del formulario esten completos

### Error: "El archivo no existe"
- Asegurate de que el CSV este en `cuestionarios/`
- Verifica que el nombre del archivo sea correcto (sin extension)

### Error de validacion XSD
- Revisa que el CSV tenga el formato correcto
- Verifica que los valores cumplan con los tipos definidos en el XSD

### El XML no se genera
- Comprueba los permisos de escritura en `procesados/`
- Revisa los logs del servidor web

## Desarrollo

### Tecnologias utilizadas
- PHP 7.4+
- DOMDocument (validacion XSD)
- HTML5 + CSS (interfaz)
- JavaScript (arrastrar y soltar)

### Convenciones de codigo
- **Funciones:** `snake_case`, verbos en infinitivo
- **Comentarios:** En español
- **Manejo de errores:** Excepciones capturadas en `index.php`
- **Escape HTML:** `htmlspecialchars()`

### Arquitectura
- **Front-controller:** `index.php` (unico punto de entrada)
- **Motor:** `funciones_encuestas.php` (logica de negocio)
- **Plantillas:** `templates/` (vistas)

## Despliegue

### Opcion 1: XAMPP
```bash
# Copiar a htdocs
cp -r xml-fundae-validator/ /opt/lampp/htdocs/  # Linux
copy xml-fundae-validator\ C:\xampp\htdocs\      # Windows
```

### Opcion 2: PHP nativo
```bash
# Iniciar servidor (Windows)
iniciar_servidor.bat

# Iniciar servidor (Linux/Mac)
php -S localhost:8000 -t .
```

### Opcion 3: Servidor web (Apache/Nginx)
- Configura el document root a la carpeta del proyecto
- Asegura permisos de escritura en `procesados/`
- Asegura permisos de lectura en `cuestionarios/`
