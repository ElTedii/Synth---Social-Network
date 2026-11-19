<?php

session_start();

require_once 'Conexion.php';

// Verificar que el usuario haya iniciado sesión
if (!isset($_SESSION['id_usuario'])) {
    header('Location: ../login.html');
    exit;
}

// Comprobar que el formulario se haya enviado mediante POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../miperfil.php");
    exit;
}

// Obtener los datos que el usuario escribió en el formulario
$nombre = trim($_POST['nombre'] ?? '');
$correo = trim($_POST['correo'] ?? '');
$contrasena_nueva = $_POST['contrasena'] ?? '';

// Obtener el ID del usuario que inició sesión
$id_usuario = (int)$_SESSION['id_usuario'];

// Verificar que el nombre y el correo sean válidos
if ($nombre === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    exit("Error: proporciona un nombre y un correo válidos.");
}

// Si no se escribe una contraseña nueva, se conserva la actual
$hash_contrasena = null;

// Comprobar si el usuario quiere cambiar su contraseña
if ($contrasena_nueva !== '') {

    // Encriptar la nueva contraseña antes de guardarla en la base de datos
    $hash_contrasena = password_hash(
        $contrasena_nueva,
        PASSWORD_DEFAULT
    );
}

// Inicialmente no se enviará ninguna foto nueva
$foto_perfil = null;

// Comprobar si el usuario seleccionó una imagen de perfil
if (
    isset($_FILES['imagen_perfil']) &&
    $_FILES['imagen_perfil']['error'] !== UPLOAD_ERR_NO_FILE
) {

    // Verificar que la imagen se haya subido correctamente
    if ($_FILES['imagen_perfil']['error'] !== UPLOAD_ERR_OK) {
        exit("Error al subir la imagen de perfil.");
    }

    // Obtener el tipo de archivo para comprobar su formato
    $finfo = new finfo(FILEINFO_MIME_TYPE);

    $tipoImagen = $finfo->file(
        $_FILES['imagen_perfil']['tmp_name']
    );

    // Definir los formatos de imagen que se permiten
    $tiposPermitidos = [
        'image/jpeg',
        'image/png'
    ];

    // Comprobar que la imagen sea JPG o PNG
    if (!in_array($tipoImagen, $tiposPermitidos, true)) {
        exit("Solo se permiten imágenes JPG y PNG.");
    }

    // Leer el contenido de la imagen y obtener sus datos binarios
    // Estos datos son los que se van a enviar a la base de datos
    $foto_perfil = file_get_contents(
        $_FILES['imagen_perfil']['tmp_name']
    );

    // Comprobar que se haya podido leer correctamente la imagen
    if ($foto_perfil === false) {
        exit("No se pudo leer la imagen.");
    }
}

// Intentar realizar la modificación del usuario
try {

    // Crear la conexión con la base de datos
    $conexion_db = new Conexion();
    $conn = $conexion_db->getConexion();

    // Verificar que la conexión se haya realizado correctamente
    if (!$conn) {
        throw new Exception(
            "No se pudo conectar con la base de datos."
        );
    }

    // Preparar la llamada al procedimiento almacenado para modificar el usuario
    $sql = "CALL sp_gestion_usuarios(
        'modificar',
        :id,
        :nombre,
        :correo,
        :contrasena,
        :foto_perfil
    )";

    $stmt = $conn->prepare($sql);

    // Enviar el ID del usuario que se va a modificar
    $stmt->bindValue(
        ':id',
        $id_usuario,
        PDO::PARAM_INT
    );

    // Enviar el nuevo nombre y correo
    $stmt->bindValue(
        ':nombre',
        $nombre,
        PDO::PARAM_STR
    );

    $stmt->bindValue(
        ':correo',
        $correo,
        PDO::PARAM_STR
    );

    // Si se escribió una contraseña nueva, enviar su hash
    if ($hash_contrasena === null) {

        // Si no se cambió la contraseña, enviar NULL
        $stmt->bindValue(
            ':contrasena',
            null,
            PDO::PARAM_NULL
        );

    } else {

        // Enviar la contraseña ya encriptada
        $stmt->bindValue(
            ':contrasena',
            $hash_contrasena,
            PDO::PARAM_STR
        );
    }

    // Si se seleccionó una foto nueva, enviar sus datos binarios
    if ($foto_perfil !== null) {

        // PDO::PARAM_LOB permite enviar archivos binarios a MySQL
        $stmt->bindValue(
            ':foto_perfil',
            $foto_perfil,
            PDO::PARAM_LOB
        );

    } else {

        // Si no se seleccionó otra foto, enviar NULL
        // El procedimiento almacenado conserva la foto que ya tenía el usuario
        $stmt->bindValue(
            ':foto_perfil',
            null,
            PDO::PARAM_NULL
        );
    }

    // Ejecutar el procedimiento almacenado para guardar los cambios
    $stmt->execute();

    // Obtener el mensaje que devuelve el procedimiento almacenado
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    // Liberar el cursor después de obtener la respuesta
    $stmt->closeCursor();

    // Obtener el mensaje de confirmación
    $mensaje = $resultado['mensaje']
        ?? 'Usuario modificado correctamente';

    // Mostrar el resultado de la modificación en pantalla
    echo "<div style='font-family:sans-serif;text-align:center;margin-top:50px;'>";

    // Mostrar el mensaje sin permitir que se interpreten etiquetas HTML
    echo "<h1>" .
        htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') .
        "</h1>";

    // Agregar un enlace para regresar al perfil
    echo "<a href='../miperfil.php'
        style='padding:10px 20px;background:black;color:white;
        text-decoration:none;border-radius:5px;'>
        Volver a mi perfil</a>";

    echo "</div>";

} catch (Throwable $e) {

    // Guardar el detalle del error en el registro de PHP
    error_log(
        "Error al modificar usuario: " . $e->getMessage()
    );

    // Mostrar un mensaje si ocurrió algún problema
    echo "<h2>No se pudo modificar el usuario.</h2>";
    echo "<p>Verifica que el correo no esté registrado por otra cuenta y que el procedimiento almacenado esté actualizado.</p>";
}

?>
