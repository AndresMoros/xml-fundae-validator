<form action="index.php?tipo_encuesta=presencial" method="post" enctype="multipart/form-data">
    <h2>Encuesta Presencial</h2>
    <label>Archivo </label><input type="text" name="archivo"><br />
    <input type="file" name="archivo_subido" class="archivo-subido" accept=".csv">
    <label>Empresa </label> <input type="text" name="empresa"><br />
    <label>Expediente </label> <input type="text" name="expediente"><br />
    <label>Acción </label> <input type="text" name="accion"><br />
    <label>Grupo </label> <input type="text" name="grupo"><br />
    <label>Fecha </label> <input type="text" name="fecha"><br />
    <input type="submit" name="enviar" value="Procesar">
</form>