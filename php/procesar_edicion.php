<?php

session_start();

require_once 'Conexion.php';

// ==========================================
// 1. VERIFICAR SESIÓN Y MÉTODO
// ==========================================

if (!isset($_SESSION['id_usuario'])) {
    header('Location: ../login.html');
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../miperfil.php");
    exit;
}

// ==========================================
// 2. OBTENER DATOS DEL FORMULARIO
// ==========================================

$nombre = trim($_POST['nombre'] ?? '');
$correo = trim($_POST['correo'] ?? '');
$contrasena_nueva = $_POST['contrasena'] ?? '';

// Obtener el ID del usuario de la sesión
$id_usuario = (int)$_SESSION['id_usuario'];

// Validar campos obligatorios
if ($nombre === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    exit("Error: proporciona un nombre y un correo válidos.");
}

// ==========================================
// 3. PROCESAR CONTRASEÑA
// ==========================================

// Si está vacía, se conserva la contraseña actual
$hash_contrasena = null;

if ($contrasena_nueva !== '') {
    $hash_contrasena = password_hash(
        $contrasena_nueva,
        PASSWORD_DEFAULT
    );
}

// ==========================================
// 4. PROCESAR IMAGEN DE PERFIL
// ==========================================

// Inicialmente no se enviará una imagen nueva
$foto_perfil = null;

if (
    isset($_FILES['imagen_perfil']) &&
    $_FILES['imagen_perfil']['error'] !== UPLOAD_ERR_NO_FILE
) {

    // Comprobar que la imagen se haya subido correctamente
    if ($_FILES['imagen_perfil']['error'] !== UPLOAD_ERR_OK) {
        exit("Error al subir la imagen de perfil.");
    }

    // Comprobar el formato de la imagen
    $finfo = new finfo(FILEINFO_MIME_TYPE);

    $tipoImagen = $finfo->file(
        $_FILES['imagen_perfil']['tmp_name']
    );

    $tiposPermitidos = [
        'image/jpeg',
        'image/png'
    ];

    if (!in_array($tipoImagen, $tiposPermitidos, true)) {
        exit("Solo se permiten imágenes JPG y PNG.");
    }

    // Leer el contenido binario de la imagen
    $foto_perfil = file_get_contents(
        $_FILES['imagen_perfil']['tmp_name']
    );

    if ($foto_perfil === false) {
        exit("No se pudo leer la imagen.");
    }
}

// ==========================================
// 5. CONECTAR CON LA BASE DE DATOS
// ==========================================

try {

    $conexion_db = new Conexion();
    $conn = $conexion_db->getConexion();

    if (!$conn) {
        throw new Exception(
            "No se pudo conectar con la base de datos."
        );
    }

    // ==========================================
    // 6. LLAMAR AL PROCEDIMIENTO ALMACENADO
    // ==========================================

    $sql = "CALL sp_gestion_usuarios(
        'modificar',
        :id,
        :nombre,
        :correo,
        :contrasena,
        :foto_perfil
    )";

    $stmt = $conn->prepare($sql);

    // Enviar ID, nombre y correo
    $stmt->bindValue(
        ':id',
        $id_usuario,
        PDO::PARAM_INT
    );

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

    // Enviar contraseña nueva o NULL
    if ($hash_contrasena === null) {

        $stmt->bindValue(
            ':contrasena',
            null,
            PDO::PARAM_NULL
        );

    } else {

        $stmt->bindValue(
            ':contrasena',
            $hash_contrasena,
            PDO::PARAM_STR
        );
    }

    // Enviar fotografía nueva o NULL
    if ($foto_perfil !== null) {

        $stmt->bindValue(
            ':foto_perfil',
            $foto_perfil,
            PDO::PARAM_LOB
        );

    } else {

        $stmt->bindValue(
            ':foto_perfil',
            null,
            PDO::PARAM_NULL
        );
    }

    // Ejecutar procedimiento
    $stmt->execute();

    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt->closeCursor();

    // ==========================================
    // 7. MOSTRAR RESULTADO
    // ==========================================

    $mensaje = $resultado['mensaje']
        ?? 'Usuario modificado correctamente';

    echo "<div style='font-family:sans-serif;text-align:center;margin-top:50px;'>";

    echo "<h1>" .
        htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') .
        "</h1>";

    echo "<a href='../miperfil.php'
        style='padding:10px 20px;background:black;color:white;
        text-decoration:none;border-radius:5px;'>
        Volver a mi perfil</a>";

    echo "</div>";

} catch (Throwable $e) {

    error_log(
        "Error al modificar usuario: " . $e->getMessage()
    );

    echo "<h2>No se pudo modificar el usuario.</h2>";
    echo "<p>Verifica que el correo no esté registrado por otra cuenta y que el procedimiento almacenado esté actualizado.</p>";
}

?>
