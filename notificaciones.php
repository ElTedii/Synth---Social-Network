<?php
session_start();
require_once 'php/Conexion.php';

date_default_timezone_set('America/Monterrey');

// Crear y obtener la conexión
$database = new Conexion();
$conn = $database->getConexion();

// Verificar que el usuario haya iniciado sesión
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.html");
    exit;
}

$id_usuario = $_SESSION['id_usuario'];

// Obtener las actividades mediante el procedimiento almacenado
try {
    $stmt = $conn->prepare("CALL sp_obtener_actividad_reciente(:id_usuario)");
    $stmt->bindValue(':id_usuario', $id_usuario, PDO::PARAM_INT);
    $stmt->execute();

    $actividades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

} catch (PDOException $e) {
    error_log("Error al obtener actividad: " . $e->getMessage());
    $actividades = [];
}

// Calcular cuánto tiempo ha pasado desde la actividad
function tiempoTranscurrido($fecha)
{
    $ahora = new DateTime();
    $fechaActividad = new DateTime($fecha);

    $diferencia = $ahora->diff($fechaActividad);

    if ($diferencia->y > 0) {
        return "Hace " . $diferencia->y . " año(s)";
    }

    if ($diferencia->m > 0) {
        return "Hace " . $diferencia->m . " mes(es)";
    }

    if ($diferencia->d > 0) {
        return "Hace " . $diferencia->d . " día(s)";
    }

    if ($diferencia->h > 0) {
        return "Hace " . $diferencia->h . " hora(s)";
    }

    if ($diferencia->i > 0) {
        return "Hace " . $diferencia->i . " minuto(s)";
    }

    return "Hace unos segundos";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificaciones - Synth</title>

    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="Styles/style.css">

    <style>
        .notification-item {
            background-color: #0d0d0d;
            border: 1px solid #292929;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            transition: background-color 0.2s;
        }

        .notification-item:hover {
            background-color: #151515;
        }

        .noti-text {
            flex: 1;
            margin-left: 15px;
            font-size: 15px;
        }

        .noti-text strong {
            color: #fff;
        }

        .noti-text span {
            color: #aaa;
        }

        .noti-time {
            font-size: 12px;
            color: #777;
            margin-left: 10px;
            white-space: nowrap;
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #292929;
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            font-size: 18px;
            flex-shrink: 0;
        }

        .empty-notifications {
            color: #999;
            text-align: center;
            padding: 35px 15px;
        }

        @media (max-width: 600px) {
            .notification-item {
                padding: 12px;
            }

            .noti-time {
                font-size: 11px;
            }
        }
    </style>
</head>

<body>

    <!-- Barra lateral -->
    <aside class="sidebar">

        <div class="logo">Synth</div>

        <ul class="menu">
            <li>
                <a href="index.php">
                    <span class="icon">⌂</span>
                    <span>Descubrir</span>
                </a>
            </li>

            <li>
                <a href="index.php#modal-crear">
                    <span class="icon">＋</span>
                    <span>Subir Pista</span>
                </a>
            </li>

            <li>
                <a href="miperfil.php">
                    <span class="icon">↗</span>
                    <span>Mi perfil</span>
                </a>
            </li>

            <li>
                <a href="notificaciones.php">
                    <span class="icon">○</span>
                    <span>Actividad</span>
                </a>
            </li>

            <li>
                <a href="reportes.php">
                    <span class="icon">📊</span>
                    <span>Reportes</span>
                </a>
            </li>

            <li>
                <a href="configuracion.php">
                    <span class="icon">⚙</span>
                    <span>Configuración</span>
                </a>
            </li>
        </ul>

        <div class="section-title">En Tendencia</div>

        <ul class="menu">
            <li>
                <a href="#">
                    <span class="icon">#</span>
                    <span>Ambient Textures</span>
                </a>
            </li>

            <li>
                <a href="#">
                    <span class="icon">#</span>
                    <span>Late Night Mix</span>
                </a>
            </li>
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

    <!-- Contenido principal -->
    <main class="main">

        <div class="feed">

            <h1 class="feed-title">Notificaciones</h1>

            <?php if (count($actividades) > 0): ?>

                <?php foreach ($actividades as $actividad): ?>

                    <?php
                    $actor = htmlspecialchars($actividad['actor'], ENT_QUOTES, 'UTF-8');

                    // Definir el mensaje y el icono según la actividad
                    switch ($actividad['tipo_actividad']) {

                        case 'Like':
                            $mensaje = ' le dio "Me gusta" a tu publicación.';
                            $icono = '♥';
                            break;

                        case 'Comentario':
                            $mensaje = ' comentó una de tus publicaciones.';
                            $icono = '☰';
                            break;

                        case 'Nuevo Seguidor':
                            $mensaje = ' comenzó a seguirte.';
                            $icono = '＋';
                            break;

                        default:
                            $mensaje = ' realizó una actividad.';
                            $icono = '○';
                    }
                    ?>

                    <div class="notification-item">

                        <div class="notification-icon">
                            <?php echo $icono; ?>
                        </div>

                        <div class="noti-text">
                            <strong><?php echo $actor; ?></strong>
                            <?php echo $mensaje; ?>
                        </div>

                        <span class="noti-time">
                            <?php echo tiempoTranscurrido($actividad['fecha']); ?>
                        </span>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="empty-notifications">
                    <p>No tienes actividad reciente.</p>
                    <p>Cuando alguien interactúe con tus publicaciones o comience a seguirte, aparecerá aquí.</p>
                </div>

            <?php endif; ?>

        </div>

    </main>

</body>
</html>
