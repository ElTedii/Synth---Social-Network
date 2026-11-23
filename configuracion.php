<?php
session_start();

require_once 'php/Conexion.php';

// 1. Verificar sesión activa
if (!isset($_SESSION['id_usuario'])) {
    header('Location: login.html');
    exit;
}

$conexion = new Conexion();
$db = $conexion->getConexion();

if (!$db) {
    exit('No se pudo conectar con la base de datos.');
}

function escapar($valor) {
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

$id_usuario = (int) $_SESSION['id_usuario'];
$nombre_actual = $_SESSION['nombre'] ?? '';
$correo_actual = $_SESSION['correo'] ?? '';

$mensajeExito = '';
$mensajeError = '';

// 2. Procesar formularios enviados por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'cambiar_password') {
        $nueva = $_POST['nueva_contrasena'] ?? '';
        $confirmar = $_POST['confirmar_contrasena'] ?? '';

        if (strlen($nueva) < 8) {
            $mensajeError = 'La nueva contraseña debe tener al menos 8 caracteres.';
        } elseif ($nueva !== $confirmar) {
            $mensajeError = 'Las contraseñas no coinciden.';
        } else {
            try {
                $hash = password_hash($nueva, PASSWORD_DEFAULT);

                // Usamos el SP existente manteniendo nombre, correo y foto actuales
                $sql = "CALL sp_gestion_usuarios('modificar', :id, :nombre, :correo, :contrasena, NULL)";
                $stmt = $db->prepare($sql);
                $stmt->bindValue(':id', $id_usuario, PDO::PARAM_INT);
                $stmt->bindValue(':nombre', $nombre_actual, PDO::PARAM_STR);
                $stmt->bindValue(':correo', $correo_actual, PDO::PARAM_STR);
                $stmt->bindValue(':contrasena', $hash, PDO::PARAM_STR);
                $stmt->execute();
                $stmt->closeCursor();

                $mensajeExito = 'Contraseña actualizada correctamente.';
            } catch (PDOException $e) {
                error_log('Error al cambiar contraseña: ' . $e->getMessage());
                $mensajeError = 'No se pudo actualizar la contraseña.';
            }
        }
    } elseif ($accion === 'desactivar_cuenta') {
        try {
            // Cambia el estado a 'inactivo' (esto activa el trigger trg_auditoria_usuarios)
            $stmt = $db->prepare("CALL sp_gestion_usuarios('desactivar', :id, NULL, NULL, NULL, NULL)");
            $stmt->bindValue(':id', $id_usuario, PDO::PARAM_INT);
            $stmt->execute();
            $stmt->closeCursor();

            // Destruir sesión y redirigir al login
            $_SESSION = [];
            session_destroy();
            header('Location: login.html');
            exit;
        } catch (PDOException $e) {
            error_log('Error al desactivar cuenta: ' . $e->getMessage());
            $mensajeError = 'No se pudo desactivar la cuenta.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración - Synth</title>
    <link rel="stylesheet" href="Styles/style.css">
    <style>
        .settings-panel {
            padding: 30px;
            margin-bottom: 25px;
        }

        .settings-panel h2 {
            font-size: 18px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .setting-group {
            margin-bottom: 20px;
        }

        .danger-zone {
            border: 1px solid #dc3545;
            background-color: rgba(220, 53, 69, 0.08);
        }

        .danger-zone h2 {
            border-bottom-color: #dc3545;
            color: #ff6b7a;
        }

        .btn-danger {
            background-color: #dc3545;
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            font-family: var(--font-ui);
        }

        .btn-danger:hover {
            background-color: #bb2d3b;
        }

        .alert-msg {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-ok {
            background: rgba(40, 167, 69, 0.2);
            border: 1px solid #28a745;
            color: #8bd49b;
        }
        .alert-err {
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid #dc3545;
            color: #ff8591;
        }
    </style>
</head>

<body>
    <img class="scene-plate"
        src="https://d2ol7oe51mr4n9.cloudfront.net/user_38xzZboKViGWJOttwIXH07lWA1P/7a7598ba-a6f8-42b4-ac39-98279dac4192.jpg"
        alt="">
    <video class="scene-video"
        src="https://d2ol7oe51mr4n9.cloudfront.net/user_38xzZboKViGWJOttwIXH07lWA1P/d41b8c1d-f175-43b3-af22-7081133aa8de.mp4"
        autoplay loop muted playsinline></video>

    <!-- Barra Lateral -->
    <aside class="sidebar">
        <div class="logo">Synth</div>
        <ul class="menu">
            <li><a href="index.php"><span class="icon">⌂</span><span>Descubrir</span></a></li>
            <li><a href="index.php#modal-crear"><span class="icon">＋</span><span>Subir Pista</span></a></li>
            <li><a href="miperfil.php"><span class="icon">↗</span><span>Mi perfil</span></a></li>
            <li><a href="notificaciones.php"><span class="icon">○</span><span>Actividad</span></a></li>
            <li><a href="reportes.php"><span class="icon">📊</span><span>Reportes</span></a></li>
            <li><a href="configuracion.php" class="active"><span class="icon">⚙</span><span>Configuración</span></a></li>
        </ul>

        <div class="section-title">En Tendencia</div>
        <ul class="menu">
            <li><a href="#"><span class="icon">#</span><span>Ambient Textures</span></a></li>
            <li><a href="#"><span class="icon">#</span><span>Late Night Mix</span></a></li>
        </ul>

        <!-- Botón dinámico de Sesión -->
        <ul class="menu" style="margin-top: auto; position: absolute; bottom: 20px;">
            <?php if (isset($_SESSION['id_usuario'])): ?>
                <li>
                    <a href="php/cerrar_sesion.php">
                        <span class="icon">⎋</span>
                        <span>Cerrar Sesión</span>
                    </a>
                </li>
            <?php else: ?>
                <li>
                    <a href="login.html">
                        <span class="icon">→</span>
                        <span>Iniciar Sesión</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </aside>

    <main class="main">
        <div class="feed">
            <h1 class="feed-title">Configuración de la Cuenta</h1>

            <?php if ($mensajeExito): ?>
                <div class="alert-msg alert-ok"><?= escapar($mensajeExito) ?></div>
            <?php endif; ?>

            <?php if ($mensajeError): ?>
                <div class="alert-msg alert-err"><?= escapar($mensajeError) ?></div>
            <?php endif; ?>

            <div class="glass-panel settings-panel">
                <h2>Seguridad y Contraseña</h2>
                <form action="configuracion.php" method="POST">
                    <input type="hidden" name="accion" value="cambiar_password">
                    <div class="setting-group">
                        <label class="label-mono" for="nueva_contrasena">Nueva Contraseña</label>
                        <input type="password" id="nueva_contrasena" name="nueva_contrasena" class="input-global"
                            placeholder="Mínimo 8 caracteres" minlength="8" required>

                        <label class="label-mono" for="confirmar_contrasena">Confirmar Nueva Contraseña</label>
                        <input type="password" id="confirmar_contrasena" name="confirmar_contrasena" class="input-global"
                            placeholder="Repite la nueva contraseña" minlength="8" required>
                    </div>
                    <button type="submit" class="btn-primary">Actualizar Contraseña</button>
                </form>
            </div>

            <div class="glass-panel settings-panel danger-zone">
                <h2>Zona de Peligro</h2>
                <p style="color: var(--ink-sub); font-size: 14px; margin-bottom: 18px;">
                    Al desactivar tu cuenta, tu perfil y pistas dejarán de ser visibles y se cerrará tu sesión.
                </p>
                <form action="configuracion.php" method="POST"
                    onsubmit="return confirm('¿Seguro que deseas desactivar tu cuenta?');">
                    <input type="hidden" name="accion" value="desactivar_cuenta">
                    <button type="submit" class="btn-danger">Desactivar mi cuenta</button>
                </form>
            </div>
        </div>
    </main>
</body>

</html>