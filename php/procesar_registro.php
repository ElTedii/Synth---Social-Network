<?php
// 1. Llamar al archivo de conexión
require_once 'Conexion.php';

// 2. Verificar que los datos llegaron desde el formulario HTML
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 3. Capturar lo que el usuario escribió en los inputs
    $nombre = $_POST['nombre'];
    $correo = $_POST['correo'];
    $contrasena = $_POST['contrasena'];
    
    // Por seguridad, encriptamos la contraseña antes de guardarla
    $hash_contrasena = password_hash($contrasena, PASSWORD_DEFAULT);

    try {
        // 4. Conectarnos a la BD
        $conexion_db = new Conexion();
        $conn = $conexion_db->getConexion();

        // 5. Preparar la llamada al Stored Procedure
        // Los parámetros son: accion, id_usuario (NULL al crear), nombre, correo, contrasena, foto_perfil (NULL por ahora)
        $sql = "CALL sp_gestion_usuarios('registrar', NULL, :nombre, :correo, :contrasena, NULL)";
        $stmt = $conn->prepare($sql);
        
        // 6. Inyectar los datos de forma segura
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':correo', $correo);
        $stmt->bindParam(':contrasena', $hash_contrasena);
        
        // 7. Ejecutar y mostrar el resultado
        $stmt->execute();
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Si todo sale bien, muestra el mensaje y da un link para volver
        echo "<h1>" . $resultado['mensaje'] . "</h1>";
        echo "<a href='../login.html'>Ir a iniciar sesión</a>";

    } catch(PDOException $e) {
        // Si el correo ya existe, el Procedimiento Almacenado arrojará un error 45000 que atrapamos aquí
        echo "<h2>Error al registrar: " . $e->getMessage() . "</h2>";
        echo "<a href='../registro.html'>Volver a intentar</a>";
    }
}
?>
