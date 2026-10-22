<?php
require_once 'Conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $correo = $_POST['correo'];
    $contrasena_ingresada = $_POST['contrasena'];

    try {
        $conexion_db = new Conexion();
        $conn = $conexion_db->getConexion();

        // Llamamos al Stored Procedure para buscar al usuario por su correo
        $sql = "CALL sp_gestion_usuarios('iniciar_sesion', NULL, NULL, :correo, NULL, NULL)";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':correo', $correo);
        $stmt->execute();
        
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        // Si el Stored Procedure encontró un usuario con ese correo
        if ($usuario) {
            // Verificamos que la contraseña escrita coincida con el hash seguro de la base de datos
            if (password_verify($contrasena_ingresada, $usuario['hash_contrasena'])) {
                echo "<div style='font-family: sans-serif; text-align: center; margin-top: 50px;'>";
                echo "<h1>¡Bienvenido al estudio, " . $usuario['nombre'] . "!</h1>";
                echo "<a href='../index.html' style='padding: 10px 20px; background: white; color: black; text-decoration: none; border-radius: 5px;'>Ir al Descubrir (Feed)</a>";
                echo "</div>";
            } else {
                echo "<h2>Contraseña incorrecta.</h2>";
                echo "<a href='../login.html'>Volver a intentar</a>";
            }
        } else {
            echo "<h2>No se encontró ninguna cuenta activa con ese correo.</h2>";
            echo "<a href='../registro.html'>Regístrate aquí</a>";
        }

    } catch(PDOException $e) {
        echo "<h2>Error del sistema: " . $e->getMessage() . "</h2>";
    }
}
?>