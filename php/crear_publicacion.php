<?php
session_start();

require_once 'Conexion.php';

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

$archivoGuardado = null;

try {
    $idUsuario = (int) $_SESSION['id_usuario'];
    $titulo = trim($_POST['titulo'] ?? '');
    $contenido = trim($_POST['contenido'] ?? '');
    $tipoMedio = $_POST['tipo_medio'] ?? 'audio';

    if ($titulo === '' || $contenido === '') {
        throw new Exception('El título y la descripción son obligatorios.');
    }

    if (!in_array($tipoMedio, ['audio', 'video'], true)) {
        throw new Exception('Tipo de contenido no válido.');
    }

    $rutaAudioBD = null;
    $rutaVideoBD = null;

    // 1. PROCESAR AUDIO O VIDEO SEGÚN LA ELECCIÓN
    if ($tipoMedio === 'audio') {
        if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Debes seleccionar un archivo de audio válido.');
        }

        $archivoAudio = $_FILES['audio'];
        $extensionAudio = strtolower(pathinfo($archivoAudio['name'], PATHINFO_EXTENSION));

        if ($extensionAudio !== 'mp3') {
            throw new Exception('El audio debe estar en formato MP3.');
        }

        if ($archivoAudio['size'] > 20 * 1024 * 1024) {
            throw new Exception('El audio no puede superar los 20 MB.');
        }

        $carpetaAudio = dirname(__DIR__) . '/uploads/audio/';
        if (!is_dir($carpetaAudio) && !mkdir($carpetaAudio, 0777, true)) {
            throw new Exception('No se pudo crear la carpeta de audios.');
        }

        $nombreAudio = uniqid('audio_', true) . '.mp3';
        $rutaCompleta = $carpetaAudio . $nombreAudio;

        if (!move_uploaded_file($archivoAudio['tmp_name'], $rutaCompleta)) {
            throw new Exception('No se pudo guardar el archivo de audio.');
        }

        $archivoGuardado = $rutaCompleta;
        $rutaAudioBD = 'uploads/audio/' . $nombreAudio;

    } else {
        if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Debes seleccionar un archivo de video válido.');
        }

        $archivoVideo = $_FILES['video'];

        if ($archivoVideo['size'] > 50 * 1024 * 1024) {
            throw new Exception('El video no puede superar los 50 MB.');
        }

        $finfoVideo = new finfo(FILEINFO_MIME_TYPE);
        $mimeVideo = $finfoVideo->file($archivoVideo['tmp_name']);

        $formatosVideo = [
            'video/mp4'  => 'mp4',
            'video/webm' => 'webm'
        ];

        if (!isset($formatosVideo[$mimeVideo])) {
            throw new Exception('El video debe estar en formato MP4 o WebM.');
        }

        $carpetaVideo = dirname(__DIR__) . '/uploads/video/';
        if (!is_dir($carpetaVideo) && !mkdir($carpetaVideo, 0777, true)) {
            throw new Exception('No se pudo crear la carpeta de videos.');
        }

        $nombreVideo = uniqid('video_', true) . '.' . $formatosVideo[$mimeVideo];
        $rutaCompleta = $carpetaVideo . $nombreVideo;

        if (!move_uploaded_file($archivoVideo['tmp_name'], $rutaCompleta)) {
            throw new Exception('No se pudo guardar el archivo de video.');
        }

        $archivoGuardado = $rutaCompleta;
        $rutaVideoBD = 'uploads/video/' . $nombreVideo;
    }

    // 2. PROCESAR PORTADA OPCIONAL (BLOB)
    $portadaBinaria = null;

    if (isset($_FILES['portada']) && $_FILES['portada']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['portada']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Error al subir la imagen.');
        }

        $archivoImagen = $_FILES['portada'];

        if ($archivoImagen['size'] > 5 * 1024 * 1024) {
            throw new Exception('La imagen no puede superar los 5 MB.');
        }

        $finfoImg = new finfo(FILEINFO_MIME_TYPE);
        $tipoImagen = $finfoImg->file($archivoImagen['tmp_name']);

        $tiposPermitidos = ['image/jpeg', 'image/png', 'image/webp'];

        if (!in_array($tipoImagen, $tiposPermitidos, true)) {
            throw new Exception('La portada debe ser JPG, PNG o WEBP.');
        }

        $portadaBinaria = file_get_contents($archivoImagen['tmp_name']);
        if ($portadaBinaria === false) {
            throw new Exception('No se pudo leer la imagen.');
        }
    }

    // 3. GUARDAR EN BASE DE DATOS
    $db->beginTransaction();

    $sql = "CALL sp_gestion_publicaciones(
        :accion,
        :id_publicacion,
        :id_usuario,
        :titulo,
        :contenido,
        :archivo,
        :ruta_audio,
        :ruta_video
    )";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':accion', 'crear', PDO::PARAM_STR);
    $stmt->bindValue(':id_publicacion', null, PDO::PARAM_NULL);
    $stmt->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);
    $stmt->bindValue(':titulo', $titulo, PDO::PARAM_STR);
    $stmt->bindValue(':contenido', $contenido, PDO::PARAM_STR);

    if ($portadaBinaria !== null) {
        $stmt->bindValue(':archivo', $portadaBinaria, PDO::PARAM_LOB);
    } else {
        $stmt->bindValue(':archivo', null, PDO::PARAM_NULL);
    }

    $stmt->bindValue(':ruta_audio', $rutaAudioBD, $rutaAudioBD !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':ruta_video', $rutaVideoBD, $rutaVideoBD !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);

    $stmt->execute();
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    $db->commit();

    if (!$resultado || !isset($resultado['id_publicacion'])) {
        throw new Exception('No se recibió confirmación de la publicación.');
    }

    header('Location: ../index.php');
    exit;

} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    if ($archivoGuardado !== null && file_exists($archivoGuardado)) {
        unlink($archivoGuardado);
    }

    error_log('Error al crear publicación: ' . $e->getMessage());
    echo 'No se pudo crear la publicación: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}
?>