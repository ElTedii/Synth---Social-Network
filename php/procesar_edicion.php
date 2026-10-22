<?php
require_once 'Conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Para este avance, simularemos que estamos editando al usuario 1 (Isma). 
    // En el proyecto final, este ID lo tomaremos de la sesión iniciada ( $_SESSION['id_usuario'] ).
    $id_usuario = 1; 
    
    $nombre = $_POST['nombre'];
    $correo = $_POST['correo'];
    $contrasena_nueva = $_POST['contrasena'];
    $hash_contrasena = password_hash($contrasena_nueva, PASSWORD_DEFAULT);

    try {
        $conexion_db = new Conexion();
        $conn = $conexion_db->getConexion();

        // Llamamos al Stored Procedure en modo "modificar"
        $sql = "CALL sp_gestion_usuarios('modificar', :id, :nombre, :correo, :contrasena, NULL)";
        $stmt = $conn->prepare($sql);
        
        $stmt->bindParam(':id', $id_usuario);
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':correo', $correo);
        $stmt->bindParam(':contrasena', $hash_contrasena);
        
        $stmt->execute();
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        echo "<div style='font-family: sans-serif; text-align: center; margin-top: 50px;'>";
        echo "<h1>" . $resultado['mensaje'] . "</h1>";
        echo "<a href='../miperfil.html' style='padding: 10px 20px; background: black; color: white; text-decoration: none; border-radius: 5px;'>Volver a mi perfil</a>";
        echo "</div>";

    } catch(PDOException $e) {
        echo "<h2>Error al modificar: " . $e->getMessage() . "</h2>";
    }
}
?>