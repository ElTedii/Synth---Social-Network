<?php

require_once 'Conexion.php';

// Verificar que el formulario se haya enviado
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Obtener los datos del formulario
    $nombre = $_POST['nombre'] ?? '';
    $correo = $_POST['correo'] ?? '';
    $contrasena = $_POST['contrasena'] ?? '';

    // Encriptar la contraseña
    $hash_contrasena = password_hash($contrasena, PASSWORD_DEFAULT);

    // Inicialmente no hay fotografía
    $foto_perfil = null;

    // Comprobar si se seleccionó una imagen
    if (
        isset($_FILES['imagen_perfil']) &&
        $_FILES['imagen_perfil']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        if ($_FILES['imagen_perfil']['error'] !== UPLOAD_ERR_OK) {
            exit('Error al subir la imagen.');
        }

        // Comprobar el tipo de imagen
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $tipoImagen = $finfo->file($_FILES['imagen_perfil']['tmp_name']);

        $tiposPermitidos = [
            'image/jpeg',
            'image/png'
        ];

        if (!in_array($tipoImagen, $tiposPermitidos, true)) {
            exit('Solo se permiten imágenes JPG y PNG.');
        }

        // Leer el contenido binario de la imagen
        $foto_perfil = file_get_contents(
            $_FILES['imagen_perfil']['tmp_name']
        );

        if ($foto_perfil === false) {
            exit('No se pudo leer la imagen.');
        }
    }

    try {

        // Conectar con la base de datos
        $conexion_db = new Conexion();
        $conn = $conexion_db->getConexion();

        // Llamar al procedimiento almacenado
        $sql = "CALL sp_gestion_usuarios(
            'registrar',
            NULL,
            :nombre,
            :correo,
            :contrasena,
            :foto_perfil
        )";

        $stmt = $conn->prepare($sql);

        // Enviar los datos
        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindValue(':correo', $correo, PDO::PARAM_STR);
        $stmt->bindValue(':contrasena', $hash_contrasena, PDO::PARAM_STR);

        // Enviar la fotografía como BLOB o NULL
        if ($foto_perfil !== null) {
            $stmt->bindValue(':foto_perfil', $foto_perfil, PDO::PARAM_LOB);
        } else {
            $stmt->bindValue(':foto_perfil', null, PDO::PARAM_NULL);
        }

        // Ejecutar el procedimiento
        $stmt->execute();

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt->closeCursor();

        echo "<h1>" . htmlspecialchars($resultado['mensaje']) . "</h1>";
        echo "<a href='../login.html'>Ir a iniciar sesión</a>";

    } catch (PDOException $e) {

        error_log('Error al registrar usuario: ' . $e->getMessage());

        echo "<h2>Error al registrar el usuario.</h2>";
        echo "<a href='../registro.html'>Volver a intentar</a>";
    }
}

?>
