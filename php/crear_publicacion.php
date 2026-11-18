
<?php
session_start();

require_once 'Conexion.php';

// Verificar que el usuario haya iniciado sesión
if (!isset($_SESSION['id_usuario'])) {
    header('Location: ../login.html');
    exit;
}

// Comprobar que el formulario se haya enviado mediante POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

// Crear la conexión con la base de datos
$conexion = new Conexion();
$db = $conexion->getConexion();

// Verificar que la conexión se haya realizado correctamente
if (!$db) {
    exit('No se pudo conectar con la base de datos.');
}

$audioGuardado = null;

try {

    // Obtener el id del usuario que inició sesión y los datos del formulario
    $idUsuario = (int) $_SESSION['id_usuario'];
    $titulo = trim($_POST['titulo'] ?? '');
    $contenido = trim($_POST['contenido'] ?? '');

    // Verificar que el título y la descripción no estén vacíos
    if ($titulo === '' || $contenido === '') {
        throw new Exception('El título y la descripción son obligatorios.');
    }

    // Comprobar que se haya seleccionado un archivo de audio y que se haya subido correctamente
    if (
        !isset($_FILES['audio']) ||
        $_FILES['audio']['error'] !== UPLOAD_ERR_OK
    ) {
        throw new Exception('Debes seleccionar un archivo de audio válido.');
    }

    // Obtener la información del archivo de audio
    $archivoAudio = $_FILES['audio'];

    // Obtener la extensión del archivo y convertirla a minúsculas
    $extensionAudio = strtolower(
        pathinfo($archivoAudio['name'], PATHINFO_EXTENSION)
    );

    // Permitir únicamente archivos MP3
    if ($extensionAudio !== 'mp3') {
        throw new Exception('El audio debe estar en formato MP3.');
    }

    // Comprobar que el audio no supere los 20 MB
    if ($archivoAudio['size'] > 20 * 1024 * 1024) {
        throw new Exception('El audio no puede superar los 20 MB.');
    }

    // La portada es opcional, por eso inicialmente no contiene ningún dato
    $portadaBinaria = null;

    // Comprobar si el usuario seleccionó una imagen de portada
    if (
        isset($_FILES['portada']) &&
        $_FILES['portada']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        // Verificar que la imagen se haya subido correctamente
        if ($_FILES['portada']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Error al subir la imagen.');
        }

        // Obtener la información del archivo de imagen
        $archivoImagen = $_FILES['portada'];

        // Comprobar que la imagen no supere los 5 MB
        if ($archivoImagen['size'] > 5 * 1024 * 1024) {
            throw new Exception('La imagen no puede superar los 5 MB.');
        }

        // Obtener el tipo real de la imagen para comprobar su formato
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $tipoImagen = $finfo->file($archivoImagen['tmp_name']);

        // Definir los formatos de imagen que se permiten
        $tiposPermitidos = [
            'image/jpeg',
            'image/png',
            'image/webp'
        ];

        // Verificar que la imagen tenga uno de los formatos permitidos
        if (!in_array($tipoImagen, $tiposPermitidos, true)) {
            throw new Exception('La portada debe ser JPG, PNG o WEBP.');
        }

        // Leer el contenido de la imagen en formato binario
        // Estos datos son los que después se guardan en la base de datos
        $portadaBinaria = file_get_contents(
            $archivoImagen['tmp_name']
        );

        // Comprobar que se haya podido leer correctamente la imagen
        if ($portadaBinaria === false) {
            throw new Exception('No se pudo leer la imagen.');
        }
    }

    // Definir la carpeta donde se van a guardar los archivos de audio
    $carpetaAudio = dirname(__DIR__) . '/uploads/audio/';

    // Si la carpeta no existe, crearla
    if (!is_dir($carpetaAudio)) {
        if (!mkdir($carpetaAudio, 0777, true)) {
            throw new Exception('No se pudo crear la carpeta de audios.');
        }
    }

    // Generar un nombre único para evitar que se repitan los nombres de los audios
    $nombreAudio = uniqid('audio_', true) . '.mp3';

    // Crear la ruta completa donde se guardará físicamente el audio
    $rutaAudioCompleta = $carpetaAudio . $nombreAudio;

    // Crear la ruta relativa que se almacenará en la base de datos
    $rutaAudioBD = 'uploads/audio/' . $nombreAudio;

    // Mover el audio desde su ubicación temporal hasta la carpeta uploads/audio
    if (!move_uploaded_file(
        $archivoAudio['tmp_name'],
        $rutaAudioCompleta
    )) {
        throw new Exception('No se pudo guardar el archivo de audio.');
    }

    // Guardar la ruta del audio para poder eliminarlo si ocurre algún error
    $audioGuardado = $rutaAudioCompleta;

    // Iniciar una transacción para guardar la información de la publicación
    $db->beginTransaction();

    // Preparar la llamada al procedimiento almacenado que crea la publicación
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

    // Indicar que la acción que realizará el procedimiento será crear una publicación
    $stmt->bindValue(':accion', 'crear', PDO::PARAM_STR);

    // Enviar NULL porque todavía no existe un id de publicación
    $stmt->bindValue(':id_publicacion', null, PDO::PARAM_NULL);

    // Enviar el id del usuario que está creando la publicación
    $stmt->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);

    // Enviar el título y la descripción de la publicación
    $stmt->bindValue(':titulo', $titulo, PDO::PARAM_STR);
    $stmt->bindValue(':contenido', $contenido, PDO::PARAM_STR);

    // Si se seleccionó una portada, enviar sus datos binarios a MySQL
    if ($portadaBinaria !== null) {
        $stmt->bindValue(
            ':archivo',
            $portadaBinaria,         //Aqui se envia la multemedia a mysql
            PDO::PARAM_LOB
        );
    } else {
        // Si no se seleccionó una portada, enviar NULL
        $stmt->bindValue(':archivo', null, PDO::PARAM_NULL);
    }

    // Enviar la ruta donde se guardó el audio
    $stmt->bindValue(':ruta_audio', $rutaAudioBD, PDO::PARAM_STR);

    // Ejecutar el procedimiento almacenado
    $stmt->execute();

    // Obtener la respuesta que devuelve el procedimiento almacenado
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    // Liberar el cursor para poder realizar otras consultas
    $stmt->closeCursor();

    // Confirmar los cambios realizados en la base de datos
    $db->commit();

    // Verificar que el procedimiento haya devuelto el id de la publicación creada
    if (!$resultado || !isset($resultado['id_publicacion'])) {
        throw new Exception('No se recibió confirmación de la publicación.');
    }

    // Si todo salió bien, regresar al feed
    header('Location: ../index.php');
    exit;

} catch (Throwable $e) {

    // Si ocurrió un error, cancelar los cambios de la transacción
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    // Si el audio ya se había guardado, eliminarlo para no dejar archivos sin publicación
    if ($audioGuardado !== null && file_exists($audioGuardado)) {
        unlink($audioGuardado);
    }

    // Guardar el detalle del error en el registro de PHP
    error_log('Error al crear publicación: ' . $e->getMessage());

    // Mostrar un mensaje si no se pudo crear la publicación
    echo 'No se pudo crear la publicación. Revisa el registro de errores de PHP.';
}
?>
