
<?php
require_once 'Conexion.php';

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    http_response_code(400);
    exit;
}

$idPublicacion = (int) $_GET['id'];

try {
    $conexion = new Conexion();
    $db = $conexion->getConexion();

    if (!$db) {
        http_response_code(500);
        exit;
    }

    $stmt = $db->prepare(
        "CALL sp_obtener_imagen_publicacion(?)"
    );

    $stmt->bindValue(1, $idPublicacion, PDO::PARAM_INT);
    $stmt->execute();

    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (!$resultado || !is_string($resultado['archivo'])) {
        http_response_code(404);
        exit;
    }

    $imagen = $resultado['archivo'];

    if (strlen($imagen) === 0) {
        http_response_code(404);
        exit;
    }

    $info = getimagesizefromstring($imagen);

    if ($info === false) {
        http_response_code(415);
        exit;
    }

    header('Content-Type: ' . $info['mime']);
    header('Content-Length: ' . strlen($imagen));
    header('Cache-Control: no-store');

    echo $imagen;
    exit;

} catch (Throwable $e) {
    error_log('Error portada: ' . $e->getMessage());
    http_response_code(500);
    exit;
}
?>
