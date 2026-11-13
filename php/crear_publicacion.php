
<?php
session_start();

require_once 'Conexion.php';

// Verificar sesión
if (!isset($_SESSION['id_usuario'])) {
    header('Location: ../login.html');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$conexion = new Conexion();
$db = $conexion->getConexion();

if (!$db) {
    exit('No se pudo conectar con la base de datos.');
}

$audioGuardado = null;

try {

    // Recibir datos del formulario
    $idUsuario = (int) $_SESSION['id_usuario'];
    $titulo = trim($_POST['titulo'] ?? '');
    $contenido = trim($_POST['contenido'] ?? '');

    if ($titulo === '' || $contenido === '') {
        throw new Exception('El título y la descripción son obligatorios.');
    }

    // Validar archivo de audio
    if (
        !isset($_FILES['audio']) ||
        $_FILES['audio']['error'] !== UPLOAD_ERR_OK
    ) {
        throw new Exception('Debes seleccionar un archivo de audio válido.');
    }

    $archivoAudio = $_FILES['audio'];

    $extensionAudio = strtolower(
        pathinfo($archivoAudio['name'], PATHINFO_EXTENSION)
    );

    if ($extensionAudio !== 'mp3') {
        throw new Exception('El audio debe estar en formato MP3.');
    }

    if ($archivoAudio['size'] > 20 * 1024 * 1024) {
        throw new Exception('El audio no puede superar los 20 MB.');
    }

    // Validar imagen de portada (opcional)
    $portadaBinaria = null;

    if (
        isset($_FILES['portada']) &&
        $_FILES['portada']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        if ($_FILES['portada']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Error al subir la imagen.');
        }

        $archivoImagen = $_FILES['portada'];

        if ($archivoImagen['size'] > 5 * 1024 * 1024) {
            throw new Exception('La imagen no puede superar los 5 MB.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $tipoImagen = $finfo->file($archivoImagen['tmp_name']);

        $tiposPermitidos = [
            'image/jpeg',
            'image/png',
            'image/webp'
        ];

        if (!in_array($tipoImagen, $tiposPermitidos, true)) {
            throw new Exception('La portada debe ser JPG, PNG o WEBP.');
        }

        $portadaBinaria = file_get_contents(
            $archivoImagen['tmp_name']
        );

        if ($portadaBinaria === false) {
            throw new Exception('No se pudo leer la imagen.');
        }
    }

    // Crear carpeta de audios si no existe
    $carpetaAudio = dirname(__DIR__) . '/uploads/audio/';

    if (!is_dir($carpetaAudio)) {
        if (!mkdir($carpetaAudio, 0777, true)) {
            throw new Exception('No se pudo crear la carpeta de audios.');
        }
    }

    // Crear nombre único para el MP3
    $nombreAudio = uniqid('audio_', true) . '.mp3';

    $rutaAudioCompleta = $carpetaAudio . $nombreAudio;

    $rutaAudioBD = 'uploads/audio/' . $nombreAudio;

    // Mover audio a la carpeta uploads/audio
    if (!move_uploaded_file(
        $archivoAudio['tmp_name'],
        $rutaAudioCompleta
    )) {
        throw new Exception('No se pudo guardar el archivo de audio.');
    }

    $audioGuardado = $rutaAudioCompleta;

    // Llamar al procedimiento almacenado
    $db->beginTransaction();

    $sql = "CALL sp_gestion_publicaciones(
        :accion,
        :id_publicacion,
        :id_usuario,
        :titulo,
        :contenido,
        :archivo,
        :ruta_audio
    )";

    $stmt = $db->prepare($sql);

    $stmt->bindValue(':accion', 'crear', PDO::PARAM_STR);
    $stmt->bindValue(':id_publicacion', null, PDO::PARAM_NULL);
    $stmt->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);
    $stmt->bindValue(':titulo', $titulo, PDO::PARAM_STR);
    $stmt->bindValue(':contenido', $contenido, PDO::PARAM_STR);

    if ($portadaBinaria !== null) {
        $stmt->bindValue(
            ':archivo',
            $portadaBinaria,
            PDO::PARAM_LOB
        );
    } else {
        $stmt->bindValue(':archivo', null, PDO::PARAM_NULL);
    }

    $stmt->bindValue(':ruta_audio', $rutaAudioBD, PDO::PARAM_STR);

    $stmt->execute();

    // Recuperar respuesta del procedimiento
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt->closeCursor();

    $db->commit();

    // Verificar resultado
    if (!$resultado || !isset($resultado['id_publicacion'])) {
        throw new Exception('No se recibió confirmación de la publicación.');
    }

    // Redirigir al feed
    header('Location: ../index.php');
    exit;

} catch (Throwable $e) {

    if ($db->inTransaction()) {
        $db->rollBack();
    }

    // Eliminar audio si la publicación falló
    if ($audioGuardado !== null && file_exists($audioGuardado)) {
        unlink($audioGuardado);
    }

    error_log('Error al crear publicación: ' . $e->getMessage());

    echo 'No se pudo crear la publicación. Revisa el registro de errores de PHP.';
}
?>
