<?php
require_once 'Conexion.php';

// Si alguien entra directo por URL sin enviar el formulario, redirigir al registro
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../registro.html');
    exit;
}

// Función auxiliar para mostrar avisos claros al usuario
function mostrarMensaje(string $titulo, string $detalle, string $enlace, string $textoEnlace): void {
    echo "<div style='font-family:sans-serif;text-align:center;margin-top:50px;'>";
    echo "<h1>" . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . "</h1>";
    if ($detalle !== '') {
        echo "<p>" . htmlspecialchars($detalle, ENT_QUOTES, 'UTF-8') . "</p>";
    }
    echo "<a href='" . htmlspecialchars($enlace, ENT_QUOTES, 'UTF-8') . "' "
       . "style='display:inline-block;margin-top:15px;padding:10px 20px;background:#000;color:#fff;text-decoration:none;border-radius:6px;'>"
       . htmlspecialchars($textoEnlace, ENT_QUOTES, 'UTF-8') . "</a>";
    echo "</div>";
    exit;
}

// 1. Obtener y limpiar datos del formulario
$nombre = trim($_POST['nombre'] ?? '');
$correo = trim($_POST['correo'] ?? '');
$contrasena = $_POST['contrasena'] ?? '';

// 2. Validar campos obligatorios y formatos
if ($nombre === '' || mb_strlen($nombre, 'UTF-8') > 100) {
    mostrarMensaje(
        'Nombre no válido',
        'El nombre es obligatorio y no puede superar los 100 caracteres.',
        '../registro.html',
        'Volver al registro'
    );
}

if ($correo === '' || mb_strlen($correo, 'UTF-8') > 150 || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    mostrarMensaje(
        'Correo no válido',
        'Ingresa un correo electrónico con formato válido.',
        '../registro.html',
        'Volver al registro'
    );
}

// Validar requisitos de contraseña: mín. 8 caracteres, mayúscula, minúscula, número y carácter especial
if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/', $contrasena)) {
    mostrarMensaje(
        'Contraseña no válida',
        'Debe tener al menos 8 caracteres, una mayúscula, una minúscula, un número y un carácter especial.',
        '../registro.html',
        'Volver al registro'
    );
}

// 3. Hashear la contraseña
$hash_contrasena = password_hash($contrasena, PASSWORD_DEFAULT);

// 4. Validar foto de perfil (opcional, almacenada en BLOB)
$foto_perfil = null;

if (isset($_FILES['imagen_perfil']) && $_FILES['imagen_perfil']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['imagen_perfil']['error'] !== UPLOAD_ERR_OK) {
        mostrarMensaje(
            'Error al subir la imagen',
            'No se pudo cargar la fotografía de perfil. Intenta de nuevo.',
            '../registro.html',
            'Volver al registro'
        );
    }

    // Límite de 5 MB para la foto de perfil
    if ($_FILES['imagen_perfil']['size'] > 5 * 1024 * 1024) {
        mostrarMensaje(
            'Imagen demasiado grande',
            'La imagen de perfil no puede superar los 5 MB.',
            '../registro.html',
            'Volver al registro'
        );
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $tipoImagen = $finfo->file($_FILES['imagen_perfil']['tmp_name']);
    $tiposPermitidos = ['image/jpeg', 'image/png'];

    if (!in_array($tipoImagen, $tiposPermitidos, true)) {
        mostrarMensaje(
            'Formato no permitido',
            'Solo se permiten imágenes en formato JPG o PNG.',
            '../registro.html',
            'Volver al registro'
        );
    }

    $foto_perfil = file_get_contents($_FILES['imagen_perfil']['tmp_name']);
    if ($foto_perfil === false) {
        mostrarMensaje(
            'Error al leer la imagen',
            'No se pudo procesar el archivo seleccionado.',
            '../registro.html',
            'Volver al registro'
        );
    }
}

// 5. Registrar usuario mediante Stored Procedure
try {
    $conexion_db = new Conexion();
    $conn = $conexion_db->getConexion();

    if (!$conn) {
        throw new Exception('No se pudo conectar con la base de datos.');
    }

    $sql = "CALL sp_gestion_usuarios(
        'registrar',
        NULL,
        :nombre,
        :correo,
        :contrasena,
        :foto_perfil
    )";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
    $stmt->bindValue(':correo', $correo, PDO::PARAM_STR);
    $stmt->bindValue(':contrasena', $hash_contrasena, PDO::PARAM_STR);

    if ($foto_perfil !== null) {
        $stmt->bindValue(':foto_perfil', $foto_perfil, PDO::PARAM_LOB);
    } else {
        $stmt->bindValue(':foto_perfil', null, PDO::PARAM_NULL);
    }

    $stmt->execute();
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    $mensaje = $resultado['mensaje'] ?? 'Usuario registrado correctamente';

    mostrarMensaje(
        $mensaje,
        'Ya puedes acceder a tu cuenta en Synth.',
        '../login.html',
        'Ir a iniciar sesión'
    );

} catch (PDOException $e) {
    error_log('Error al registrar usuario: ' . $e->getMessage());

    // Detectar mensaje personalizado lanzado por el SIGNAL del SP (SQLSTATE 45000)
    if ($e->getCode() === '45000') {
        mostrarMensaje(
            'No se pudo completar el registro',
            'Ese correo electrónico ya está registrado. Intenta con otro o inicia sesión.',
            '../registro.html',
            'Volver a intentar'
        );
    }

    mostrarMensaje(
        'Error al registrar el usuario',
        'Ocurrió un problema inesperado. Inténtalo nuevamente.',
        '../registro.html',
        'Volver a intentar'
    );
} catch (Throwable $e) {
    error_log('Error general en registro: ' . $e->getMessage());
    mostrarMensaje(
        'Error del sistema',
        'No se pudo completar el registro en este momento.',
        '../registro.html',
        'Volver a intentar'
    );
}
?>