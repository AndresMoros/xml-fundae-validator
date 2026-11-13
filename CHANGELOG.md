# Changelog

Todos los cambios relevantes de este proyecto se documentan en este fichero.

El formato sigue [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) y el
versionado sigue [SemVer](https://semver.org/lang/es/).

## [No publicado]

### Corregido

- **Compatibilidad de la lectura de CSV con PHP 8.4**
  ([scripts/funciones_encuestas.php](scripts/funciones_encuestas.php)): la llamada a
  `fgetcsv()` solo indicaba longitud maxima y delimitador, sin el parametro `$escape`.
  Desde PHP 8.4 esto genera el aviso
  `Deprecated: fgetcsv(): the $escape parameter must be provided as its default value will change`.
  En equipos con PHP 8.4 y `display_errors` activado el aviso se imprimia dentro de la
  respuesta HTTP y la conversion CSV -> XML fallaba, aunque el CSV fuese correcto.
  Ahora se pasan de forma explicita `$enclosure` (`"`) y `$escape` (`''`), de modo que el
  comportamiento es identico en PHP 7.4, 8.0-8.3 y 8.4+, y no aparece el aviso.

#### Detalles tecnicos

- Antes: `fgetcsv($fp, 10000, $delimitador)`
- Ahora: `fgetcsv($fp, 10000, $delimitador, '"', '')`
- `$escape = ''` (sin caracter de escape) es el valor recomendado y coherente con RFC 4180.
  Los CSV del proyecto no usan comillas ni barras invertidas, por lo que las filas leidas
  son exactamente las mismas que antes.
- Verificado con el fichero `25_2775.csv` (46 columnas): con PHP 8.2 no habia avisos y con
  PHP 8.4.27 se reproduce el aviso antes del cambio y desaparece despues, obteniendo el
  mismo resultado (12 filas, 46 columnas, codificacion UTF-8 correcta).
- No requiere cambios en los CSV ni en la configuracion de PHP de los equipos.

## [1.0] - 2026-09-02

### Anadido

- Version estable inicial del sistema: conversion de CSV de encuestas de calidad a XML
  validable contra `CuestionarioEvaluacion_2025.xsd`.
- Soporte de las modalidades Presencial, Mixta y Teleformacion.
- Deteccion automatica del delimitador del CSV (`;`, `,`, tabulador o `|`).
- Organizacion automatica de la salida por empresa y expediente.
- `iniciar_servicio.bat` para arrancar el proyecto con PHP nativo en Windows.
- `.gitignore` del proyecto.
