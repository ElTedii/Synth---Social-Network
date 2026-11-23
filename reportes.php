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

// Valores por defecto de los filtros (últimos 30 días hasta hoy)
$fechaInicio = trim($_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-30 days')));
$fechaFin = trim($_GET['fecha_fin'] ?? date('Y-m-d'));
$errorFiltro = '';
$errorReporte = '';

// Validar formato YYYY-MM-DD y coherencia del rango
$dtInicio = DateTime::createFromFormat('Y-m-d', $fechaInicio);
$dtFin = DateTime::createFromFormat('Y-m-d', $fechaFin);

if (!$dtInicio || $dtInicio->format('Y-m-d') !== $fechaInicio || !$dtFin || $dtFin->format('Y-m-d') !== $fechaFin) {
    $errorFiltro = 'Selecciona fechas válidas para generar el reporte.';
    $fechaInicio = date('Y-m-d', strtotime('-30 days'));
    $fechaFin = date('Y-m-d');
} elseif ($fechaInicio > $fechaFin) {
    $errorFiltro = 'La fecha de inicio no puede ser mayor a la fecha de fin.';
}

// Variables del reporte global filtrado por fechas
$reportePublicaciones = 0;
$reporteLikes = 0;
$reporteSeguidores = 0;

// Variables de resumen del usuario y tabla de pistas
$publicaciones = [];

try {
    // 3. Ejecutar SP de estadísticas con filtros de fecha (solo si no hubo error de validación)
    if ($errorFiltro === '') {
        $stmtRep = $db->prepare("CALL sp_reporte_estadisticas(:fecha_inicio, :fecha_fin)");
        $stmtRep->bindValue(':fecha_inicio', $fechaInicio, PDO::PARAM_STR);
        $stmtRep->bindValue(':fecha_fin', $fechaFin, PDO::PARAM_STR);
        $stmtRep->execute();
        $estadisticas = $stmtRep->fetch(PDO::FETCH_ASSOC);
        $stmtRep->closeCursor();

        if ($estadisticas) {
            $reportePublicaciones = (int) $estadisticas['total_publicaciones'];
            $reporteLikes = (int) $estadisticas['total_likes'];
            $reporteSeguidores = (int) $estadisticas['nuevos_seguidores'];
        }
    }

    // 4. Obtener publicaciones del usuario mediante SP
    $stmtPubs = $db->prepare("CALL sp_listar_publicaciones_usuario(:id_usuario)");
    $stmtPubs->bindValue(':id_usuario', $id_usuario, PDO::PARAM_INT);
    $stmtPubs->execute();
    $publicaciones = $stmtPubs->fetchAll(PDO::FETCH_ASSOC);
    $stmtPubs->closeCursor();

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
    <style>
        .filters-panel {
            padding: 24px;
            margin-bottom: 30px;
        }

        .filters-form {
            display: flex;
            gap: 16px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .filter-field {
            flex: 1;
            min-width: 180px;
        }

        .filter-field .input-global {
            margin-bottom: 0;
        }

        .alert-err {
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid #dc3545;
            color: #ff8591;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
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
            <li><a href="reportes.php" class="active"><span class="icon">📊</span><span>Reportes</span></a></li>
            <li><a href="configuracion.php"><span class="icon">⚙</span><span>Configuración</span></a></li>
        </ul>

        <div class="section-title">En Tendencia</div>
        <ul class="menu">
            <li><a href="#"><span class="icon">#</span><span>Ambient Textures</span></a></li>
            <li><a href="#"><span class="icon">#</span><span>Late Night Mix</span></a></li>
        </ul>

        <ul class="menu" style="margin-top: auto; position: absolute; bottom: 20px;">
            <li>
                <a href="php/cerrar_sesion.php">
                    <span class="icon">⎋</span>
                    <span>Cerrar Sesión</span>
                </a>
            </li>
        </ul>
    </aside>

    <main class="main">
        <div style="max-width: 900px; margin: 0 auto;">

            <h1 class="feed-title">Análisis de Audiencia</h1>

            <?php if ($errorFiltro !== ''): ?>
                <div class="alert-err"><?= escapar($errorFiltro) ?></div>
            <?php endif; ?>

            <?php if ($errorReporte !== ''): ?>
                <div class="alert-err"><?= escapar($errorReporte) ?></div>
            <?php endif; ?>

            <!-- Filtros por rango de fechas (parámetros para sp_reporte_estadisticas) -->
            <section class="glass-panel filters-panel">
                <form action="reportes.php" method="GET" class="filters-form">
                    <div class="filter-field">
                        <label class="label-mono" for="fecha_inicio">Fecha de inicio</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" class="input-global"
                            value="<?= escapar($fechaInicio) ?>" required>
                    </div>

                    <div class="filter-field">
                        <label class="label-mono" for="fecha_fin">Fecha de fin</label>
                        <input type="date" id="fecha_fin" name="fecha_fin" class="input-global"
                            value="<?= escapar($fechaFin) ?>" required>
                    </div>

                    <div>
                        <button type="submit" class="btn-primary" style="width: auto;">
                            Filtrar Reporte
                        </button>
                    </div>
                </form>
            </section>

            <!-- Tarjetas de Estadísticas del Periodo -->
            <div class="stats-grid">
                <div class="glass-panel stat-card">
                    <div class="stat-value"><?= $reportePublicaciones ?></div>
                    <div class="stat-label">Publicaciones en el periodo</div>
                </div>
                <div class="glass-panel stat-card">
                    <div class="stat-value"><?= $reporteLikes ?></div>
                    <div class="stat-label">Likes en el periodo</div>
                </div>
                <div class="glass-panel stat-card">
                    <div class="stat-value"><?= $reporteSeguidores ?></div>
                    <div class="stat-label">Nuevos Seguidores</div>
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