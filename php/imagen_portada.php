
<?php
require_once 'Conexion.php';

// Se eliminan los espacios o saltos de línea que se hayan generado antes
// de enviar la imagen, ya que pueden causar problemas al mostrarla.
while (ob_get_level() > 0) {
    ob_end_clean();
}

// Se verifica que se haya recibido el id de la publicación
// y que sea un número válido.
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    // Si el id no es válido, se envía el código de error 400.
    http_response_code(400);
    exit;
}

// Se guarda el id recibido y se convierte a entero.
$idPublicacion = (int) $_GET['id'];

try {
    // Se crea la conexión con la base de datos.
    $conexion = new Conexion();
    $db = $conexion->getConexion();

    // Se verifica que la conexión se haya realizado correctamente.
    if (!$db) {
        http_response_code(500);
        exit;
    }

    // Se prepara el procedimiento almacenado que obtiene la imagen
    // correspondiente al id de la publicación.
    $stmt = $db->prepare("CALL sp_obtener_imagen_publicacion(?)");

    // Se envía el id de la publicación como parámetro entero.
    $stmt->bindValue(1, $idPublicacion, PDO::PARAM_INT);

    // Se ejecuta el procedimiento almacenado.
    $stmt->execute();

    // Se obtiene el resultado de la consulta en forma de arreglo.
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    // Se cierra el cursor para liberar los resultados de la consulta.
    $stmt->closeCursor();

    // Se comprueba que exista un resultado y que el archivo de imagen
    // tenga información válida.
    if (!$resultado || !is_string($resultado['archivo']) || $resultado['archivo'] === '') {
        // Si no se encuentra la imagen, se envía el error 404.
        http_response_code(404);
        exit;
    }

    // Se guarda el contenido de la imagen en una variable.
    $imagen = $resultado['archivo'];

    // Se revisa el contenido de la imagen para conocer su formato
    // y comprobar que realmente sea una imagen válida.
    $info = getimagesizefromstring($imagen);

    if ($info === false) {
        // Si el archivo no corresponde a una imagen válida,
        // se envía el error 415.
        http_response_code(415);
        exit;
    }

    // Se indica al navegador el tipo de imagen que se va a mostrar.
    header('Content-Type: ' . $info['mime']);

    // Se indica el tamaño del archivo para que el navegador
    // sepa cuántos datos va a recibir.
    header('Content-Length: ' . strlen($imagen));

    // Se evita que el navegador guarde una copia de la imagen en caché.
    header('Cache-Control: no-store');

    // Se envía el contenido de la imagen al navegador para mostrarla.
    echo $imagen;
    exit;

} catch (Throwable $e) {
    // Si ocurre algún error durante el proceso, se guarda el mensaje
    // en el registro de errores del servidor.
    error_log('Error portada: ' . $e->getMessage());

    // Se informa que ocurrió un error interno del servidor.
    http_response_code(500);
    exit;
}
