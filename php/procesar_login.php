<?php
session_start();

require_once 'Conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $correo = trim($_POST['correo'] ?? '');
    $contrasena_ingresada = $_POST['contrasena'] ?? '';

    try {
        $conexion_db = new Conexion();
        $conn = $conexion_db->getConexion();

        $sql = "CALL sp_gestion_usuarios(
            'iniciar_sesion', NULL, NULL, :correo, NULL, NULL
        )";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':correo', $correo);
        $stmt->execute();

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        // Cerramos el cursor del procedimiento almacenado
        $stmt->closeCursor();

        if ($usuario) {

            if (password_verify(
                $contrasena_ingresada,
                $usuario['hash_contrasena']
            )) {

                // Guardamos la información del usuario
                session_regenerate_id(true);

                $_SESSION['id_usuario'] = $usuario['id_usuario'];
                $_SESSION['nombre'] = $usuario['nombre'];
                $_SESSION['correo'] = $usuario['correo'];

                // Redirigimos al feed
                header("Location: ../index.php");
                exit();

            } else {
                echo "Contraseña incorrecta.";
            }

        } else {
            echo "No se encontró ninguna cuenta activa con ese correo.";
        }

    } catch (PDOException $e) {
        error_log("Error en login: " . $e->getMessage());
        echo "Ocurrió un error al iniciar sesión.";
    }
}
?>
