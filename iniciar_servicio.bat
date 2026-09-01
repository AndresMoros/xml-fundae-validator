@echo off
setlocal

rem Ruta del ejecutable de PHP dentro de XAMPP.
set "PHP_EXE=C:\xampp\php\php.exe"

rem Carpeta del proyecto: la misma donde esta este BAT (funciona desde cualquier carpeta).
set "PROJECT_DIR=%~dp0"

rem Puerto del servidor local. Cambialo si el 8000 esta ocupado.
set "PORT=8000"

if not exist "%PHP_EXE%" (
    echo No se encontro PHP en: %PHP_EXE%
    echo Revisa que XAMPP este instalado en C:\xampp o ajusta la variable PHP_EXE.
    pause
    exit /b 1
)

echo ==========================================================
echo  Servidor de encuestas (PHP built-in server)
echo  Proyecto : %PROJECT_DIR%
echo  URL      : http://localhost:%PORT%
echo ==========================================================
echo.
echo Abriendo el navegador...
echo.

start http://localhost:%PORT%

echo Iniciando el servidor PHP...
echo Pulsa Ctrl+C para detener el servidor.
echo.

"%PHP_EXE%" -S localhost:%PORT% -t "%PROJECT_DIR%"

echo.
echo Servidor detenido.
pause
