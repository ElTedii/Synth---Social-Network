
<?php

require_once 'Conexion.php';

// Verificar que el formulario de registro se haya enviado mediante POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Obtener los datos que el usuario escribió en el formulario
    $nombre = $_POST['nombre'] ?? '';
    $correo = $_POST['correo'] ?? '';
    $contrasena = $_POST['contrasena'] ?? '';

    // Generar un hash de la contraseña antes de guardarla
    // Esto evita almacenar la contraseña original directamente en la base de datos
    $hash_contrasena = password_hash($contrasena, PASSWORD_DEFAULT);

    // Inicialmente no se tiene ninguna fotografía de perfil
    // Si el usuario no selecciona una imagen, este valor seguirá siendo NULL
    $foto_perfil = null;

    // Comprobar si el usuario seleccionó una imagen en el formulario
    if (
        isset($_FILES['imagen_perfil']) &&
        $_FILES['imagen_perfil']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        // Verificar que el archivo se haya subido correctamente
        if ($_FILES['imagen_perfil']['error'] !== UPLOAD_ERR_OK) {
            exit('Error al subir la imagen.');
        }

        // Obtener el tipo real del archivo para comprobar su formato
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $tipoImagen = $finfo->file($_FILES['imagen_perfil']['tmp_name']);

        // Definir los formatos de imagen que se permiten
        $tiposPermitidos = [
            'image/jpeg',
            'image/png'
        ];

        // Comprobar que la imagen sea JPG o PNG
        if (!in_array($tipoImagen, $tiposPermitidos, true)) {
            exit('Solo se permiten imágenes JPG y PNG.');
        }

        // Leer el contenido de la imagen y obtener sus datos binarios
        // Estos datos se enviarán después a la base de datos
        $foto_perfil = file_get_contents(
            $_FILES['imagen_perfil']['tmp_name']
        );

        // Verificar que se haya podido leer correctamente la imagen
        if ($foto_perfil === false) {
            exit('No se pudo leer la imagen.');
        }
    }

    try {

        // Crear la conexión con la base de datos
        $conexion_db = new Conexion();
        $conn = $conexion_db->getConexion();

        // Preparar la llamada al procedimiento almacenado para registrar al usuario
        // Se utiliza la acción registrar para indicar la operación que se realizará
        $sql = "CALL sp_gestion_usuarios(
            'registrar',
            NULL,
            :nombre,
            :correo,
            :contrasena,
            :foto_perfil
        )";

        $stmt = $conn->prepare($sql);

        // Enviar el nombre y el correo del usuario como cadenas de texto
        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindValue(':correo', $correo, PDO::PARAM_STR);

        // Enviar el hash de la contraseña en lugar de la contraseña original
        $stmt->bindValue(':contrasena', $hash_contrasena, PDO::PARAM_STR);

        // Comprobar si el usuario seleccionó una fotografía
        if ($foto_perfil !== null) {

            // Enviar los datos binarios de la imagen a MySQL
            // PDO::PARAM_LOB indica que se trata de un archivo binario grande
            $stmt->bindValue(':foto_perfil', $foto_perfil, PDO::PARAM_LOB);

        } else {

            // Si no se seleccionó una fotografía, enviar NULL
            // De esta manera el usuario puede registrarse sin foto de perfil
            $stmt->bindValue(':foto_perfil', null, PDO::PARAM_NULL);
        }

        // Ejecutar el procedimiento almacenado para registrar al usuario
        $stmt->execute();

        // Obtener el resultado que devuelve el procedimiento almacenado
        // En este caso contiene el mensaje sobre el resultado del registro
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        // Cerrar el cursor para liberar los resultados del procedimiento
        $stmt->closeCursor();

        // Mostrar el mensaje que devuelve el procedimiento almacenado
        // htmlspecialchars evita que el texto se interprete como código HTML
        echo "<h1>" . htmlspecialchars($resultado['mensaje']) . "</h1>";

        // Agregar un enlace para ir a la pantalla de inicio de sesión
        echo "<a href='../login.html'>Ir a iniciar sesión</a>";

    } catch (PDOException $e) {

        // Guardar los detalles del error en el registro de PHP
        // Esto permite revisar qué ocurrió sin mostrar información interna al usuario
        error_log('Error al registrar usuario: ' . $e->getMessage());

        // Mostrar un mensaje si ocurrió algún problema durante el registro
        echo "<h2>Error al registrar el usuario.</h2>";
        echo "<a href='../registro.html'>Volver a intentar</a>";
    }
}

?>
