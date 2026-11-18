
<?php

session_start();

require_once 'Conexion.php';

// Verificar si el formulario de inicio de sesión fue enviado mediante POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Obtener el correo y la contraseña que escribió el usuario
    // trim() elimina espacios al principio y al final del correo
    $correo = trim($_POST['correo'] ?? '');
    $contrasena_ingresada = $_POST['contrasena'] ?? '';

    try {

        // Crear la conexión con la base de datos
        $conexion_db = new Conexion();
        $conn = $conexion_db->getConexion();

        // Llamar al procedimiento almacenado para buscar una cuenta
        // Se utiliza la acción iniciar_sesion y se envía el correo
        // Los demás parámetros se mandan como NULL porque no se necesitan
        $sql = "CALL sp_gestion_usuarios(
            'iniciar_sesion', NULL, NULL, :correo, NULL, NULL
        )";

        // Preparar la llamada al procedimiento almacenado
        $stmt = $conn->prepare($sql);

        // Asociar el correo ingresado con el parámetro del procedimiento
        $stmt->bindParam(':correo', $correo);

        // Ejecutar el procedimiento para buscar al usuario en la base de datos
        $stmt->execute();

        // Obtener los datos de la cuenta encontrada en forma de arreglo asociativo
        // Así podemos acceder a sus campos utilizando el nombre de cada columna
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        // Cerrar el cursor para liberar los resultados del procedimiento almacenado
        // Esto permite que la conexión pueda utilizarse nuevamente
        $stmt->closeCursor();

        // Comprobar si se encontró un usuario con ese correo
        if ($usuario) {

            // Verificar si la contraseña escrita coincide con el hash
            // que está almacenado en la base de datos
            // password_verify() compara la contraseña sin necesidad de desencriptarla
            if (password_verify(
                $contrasena_ingresada,
                $usuario['hash_contrasena']
            )) {

                // Regenerar el ID de sesión para evitar que se reutilice
                // un identificador de sesión anterior al iniciar sesión
                session_regenerate_id(true);

                // Guardar los datos del usuario en la sesión
                // Estos datos permiten identificar al usuario en otras páginas
                $_SESSION['id_usuario'] = $usuario['id_usuario'];
                $_SESSION['nombre'] = $usuario['nombre'];
                $_SESSION['correo'] = $usuario['correo'];

                // Si los datos son correctos, redirigir al usuario al feed
                header("Location: ../index.php");
                exit();

            } else {

                // Mostrar este mensaje cuando el correo existe,
                // pero la contraseña ingresada no coincide
                echo "Contraseña incorrecta.";
            }

        } else {

            // Mostrar este mensaje cuando el procedimiento no encuentra
            // una cuenta activa asociada al correo ingresado
            echo "No se encontró ninguna cuenta activa con ese correo.";
        }

    } catch (PDOException $e) {

        // Guardar los detalles del error en el registro de PHP
        // Esto ayuda a identificar problemas con la conexión o la consulta
        error_log("Error en login: " . $e->getMessage());

        // Mostrar un mensaje general sin exponer detalles internos del sistema
        echo "Ocurrió un error al iniciar sesión.";
    }
}

?>
