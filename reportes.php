<?php
session_start();

require_once 'php/Conexion.php';

// 1. Verificar que el usuario haya iniciado sesión
if (!isset($_SESSION['id_usuario'])) {
    header('Location: login.html');
    exit;
}

// 2. Conectar con la base de datos
$conexion = new Conexion();
$db = $conexion->getConexion();

if (!$db) {
    exit('No se pudo conectar con la base de datos.');
}

function escapar($valor) {
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

$id_usuario = (int) $_SESSION['id_usuario'];

// Variables por defecto
$totalPistas = 0;
$totalSeguidores = 0;
$totalLikes = 0;
$publicaciones = [];

try {
    // Obtener resumen del perfil (pistas y seguidores) desde la vista vw_perfil_usuario
    $stmtPerfil = $db->prepare("CALL sp_obtener_perfil(:id_usuario)");
    $stmtPerfil->bindValue(':id_usuario', $id_usuario, PDO::PARAM_INT);
    $stmtPerfil->execute();
    $perfil = $stmtPerfil->fetch(PDO::FETCH_ASSOC);
    $stmtPerfil->closeCursor();

    if ($perfil) {
        $totalPistas = (int) $perfil['total_pistas'];
        $totalSeguidores = (int) $perfil['total_seguidores'];
    }

    // Obtener las publicaciones del usuario para calcular likes totales y llenar la tabla
    $stmtPubs = $db->prepare("CALL sp_listar_publicaciones_usuario(:id_usuario)");
    $stmtPubs->bindValue(':id_usuario', $id_usuario, PDO::PARAM_INT);
    $stmtPubs->execute();
    $publicaciones = $stmtPubs->fetchAll(PDO::FETCH_ASSOC);
    $stmtPubs->closeCursor();

    foreach ($publicaciones as $pub) {
        $totalLikes += (int) $pub['total_likes'];
    }

} catch (PDOException $e) {
    error_log('Error al cargar reportes: ' . $e->getMessage());
    $errorReporte = 'No se pudieron cargar las estadísticas en este momento.';
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - Synth</title>
    <link rel="stylesheet" href="Styles/style.css">
</head>

<body>
    <img class="scene-plate"
        src="https://d2ol7oe51mr4n9.cloudfront.net/user_38xzZboKViGWJOttwIXH07lWA1P/7a7598ba-a6f8-42b4-ac39-98279dac4192.jpg"
        alt="">
    <video class="scene-video"
        src="https://d2ol7oe51mr4n9.cloudfront.net/user_38xzZboKViGWJOttwIXH07lWA1P/d41b8c1d-f175-43b3-af22-7081133aa8de.mp4"
        autoplay loop muted playsinline></video>

    <!-- Barra Lateral (Navegación principal) -->
    <aside class="sidebar">
        <div class="logo">Synth</div>
        <ul class="menu">
            <li><a href="index.php"><span class="icon">⌂</span><span>Descubrir</span></a></li>
            <li><a href="index.php#modal-crear"><span class="icon">＋</span><span>Subir Pista</span></a></li>
            <li><a href="miperfil.php"><span class="icon">↗</span><span>Mi perfil</span></a></li>
            <li><a href="notificaciones.php"><span class="icon">○</span><span>Actividad</span></a></li>

            <!-- Enlaces de administración -->
            <li><a href="reportes.php" class="active"><span class="icon">📊</span><span>Reportes</span></a></li>
            <li><a href="configuracion.php"><span class="icon">⚙</span><span>Configuración</span></a></li>
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
        <div style="max-width: 900px; margin: 0 auto;">

            <h1 class="feed-title">Análisis de Audiencia</h1>

            <?php if (isset($errorReporte)): ?>
                <p class="post-text"><?= escapar($errorReporte) ?></p>
            <?php endif; ?>

            <!-- Tarjetas de Estadísticas Reales -->
            <div class="stats-grid">
                <div class="glass-panel stat-card">
                    <div class="stat-value"><?= $totalPistas ?></div>
                    <div class="stat-label">Pistas Subidas</div>
                </div>
                <div class="glass-panel stat-card">
                    <div class="stat-value"><?= $totalLikes ?></div>
                    <div class="stat-label">Likes Recibidos</div>
                </div>
                <div class="glass-panel stat-card">
                    <div class="stat-value"><?= $totalSeguidores ?></div>
                    <div class="stat-label">Seguidores</div>
                </div>
            </div>

            <!-- Tabla de Rendimiento por Pista -->
            <div class="glass-panel" style="overflow: hidden;">
                <table class="data-table">
                    <thead style="background: rgba(0,0,0,0.4);">
                        <tr>
                            <th>Pista / Título</th>
                            <th>Fecha</th>
                            <th>Likes</th>
                            <th>Comentarios</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($publicaciones)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 30px; color: var(--ink-mono);">
                                    Aún no tienes pistas publicadas para mostrar estadísticas.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($publicaciones as $p): ?>
                                <tr>
                                    <td>
                                        <a href="post_detalles.php?id=<?= (int)$p['id_publicacion'] ?>"
                                           style="color: #fff; text-decoration: none; font-weight: 500;">
                                            <?= escapar($p['titulo'] ?? 'Sin título') ?>
                                        </a>
                                    </td>
                                    <td><?= escapar(date('d/m/Y', strtotime($p['fecha_publicacion']))) ?></td>
                                    <td><?= (int)$p['total_likes'] ?></td>
                                    <td><?= (int)$p['total_comentarios'] ?></td>
                                    <td><span style="color: var(--accent);">Activa</span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </main>
</body>

</html>