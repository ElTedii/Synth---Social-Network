<?php
session_start();
require_once 'Conexion.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../miperfil.html");
    exit;
}

$nombre = trim($_POST['nombre'] ?? '');
$correo = trim($_POST['correo'] ?? '');
$contrasena_nueva = $_POST['contrasena'] ?? '';

// Validar los campos obligatorios
if ($nombre === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    exit("Error: proporciona un nombre y un correo válidos.");
}


$id_usuario = $_SESSION['id_usuario'] ?? 1;

// Si la contraseña está vacía, se conserva la actual.
$hash_contrasena = null;

if ($contrasena_nueva !== '') {
    $hash_contrasena = password_hash(
        $contrasena_nueva,
        PASSWORD_DEFAULT
    );
}

try {
    $conexion_db = new Conexion();
    $conn = $conexion_db->getConexion();

    if (!$conn) {
        throw new Exception("No se pudo conectar con la base de datos.");
    }

    $sql = "CALL sp_gestion_usuarios(
        'modificar',
        :id,
        :nombre,
        :correo,
        :contrasena,
        NULL
    )";

    $stmt = $conn->prepare($sql);

    $stmt->bindValue(':id', (int)$id_usuario, PDO::PARAM_INT);
    $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
    $stmt->bindValue(':correo', $correo, PDO::PARAM_STR);

    if ($hash_contrasena === null) {
        $stmt->bindValue(':contrasena', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(
            ':contrasena',
            $hash_contrasena,
            PDO::PARAM_STR
        );
    }

    $stmt->execute();

    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    $mensaje = $resultado['mensaje']
        ?? 'Usuario modificado correctamente';

    echo "<div style='font-family:sans-serif;text-align:center;margin-top:50px;'>";

    echo "<h1>" .
        htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') .
        "</h1>";

    echo "<a href='../miperfil.html'
        style='padding:10px 20px;background:black;color:white;
        text-decoration:none;border-radius:5px;'>
        Volver a mi perfil</a>";

    echo "</div>";

} catch (Throwable $e) {
    error_log("Error al modificar usuario: " . $e->getMessage());

    echo "<h2>No se pudo modificar el usuario.</h2>";
    echo "<p>Verifica que el correo no esté registrado por otra cuenta y que el procedimiento almacenado esté actualizado.</p>";
}
?>
