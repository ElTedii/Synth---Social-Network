
<?php
session_start();

require_once 'php/Conexion.php';

// Verificar que el usuario haya iniciado sesión.
if (!isset($_SESSION['id_usuario'])) {
    header('Location: login.html');
    exit;
}

// Conectar con la base de datos.
$conexion = new Conexion();
$db = $conexion->getConexion();

if (!$db) {
    exit('No se pudo conectar con la base de datos.');
}

$publicaciones = [];

try {
    // Todas las operaciones de consulta se realizan mediante el SP.
    $sql = "CALL sp_gestion_publicaciones(
        :accion,
        :id_publicacion,
        :id_usuario,
        :titulo,
        :contenido,
        :archivo,
        :ruta_audio
    )";

    $stmt = $db->prepare($sql);

    $stmt->bindValue(':accion', 'listar', PDO::PARAM_STR);
    $stmt->bindValue(':id_publicacion', null, PDO::PARAM_NULL);
    $stmt->bindValue(':id_usuario', (int) $_SESSION['id_usuario'], PDO::PARAM_INT);
    $stmt->bindValue(':titulo', null, PDO::PARAM_NULL);
    $stmt->bindValue(':contenido', null, PDO::PARAM_NULL);
    $stmt->bindValue(':archivo', null, PDO::PARAM_NULL);
    $stmt->bindValue(':ruta_audio', null, PDO::PARAM_NULL);

    $stmt->execute();

    $publicaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt->closeCursor();

} catch (PDOException $e) {
    error_log('Error al cargar publicaciones: ' . $e->getMessage());
    $errorFeed = 'No se pudieron cargar las publicaciones.';
}

// Función para evitar insertar HTML no deseado.
function escapar($valor) {
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Synth - Música y Audio</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=Inter:wght@400;500&family=Newsreader:opsz,wght@6..72,500&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="Styles/style.css">
</head>

<body>

    <!-- Fondos Cinemáticos -->

    <img
        class="scene-plate"
        src="https://d2ol7oe51mr4n9.cloudfront.net/user_38xzZboKViGWJOttwIXH07lWA1P/7a7598ba-a6f8-42b4-ac39-98279dac4192.jpg"
        alt="">

    <video
        class="scene-video"
        src="https://d2ol7oe51mr4n9.cloudfront.net/user_38xzZboKViGWJOttwIXH07lWA1P/d41b8c1d-f175-43b3-af22-7081133aa8de.mp4"
        autoplay
        loop
        muted
        playsinline>
    </video>


    <!-- Barra Lateral -->

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
                <a href="#modal-crear">
                    <span class="icon">＋</span>
                    <span>Subir Pista</span>
                </a>
            </li>

            <li>
                <a href="miperfil.html">
                    <span class="icon">↗</span>
                    <span>Mi perfil</span>
                </a>
            </li>

            <li>
                <a href="notificaciones.html">
                    <span class="icon">○</span>
                    <span>Actividad</span>
                </a>
            </li>

            <li>
                <a href="reportes.html">
                    <span class="icon">📊</span>
                    <span>Reportes</span>
                </a>
            </li>

            <li>
                <a href="configuracion.html">
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


        <!-- Cerrar sesión -->

        <ul class="menu" style="margin-top: auto; position: absolute; bottom: 20px;">

            <li>
                <a href="php/cerrar_sesion.php">
                    <span class="icon">⎋</span>
                    <span>Cerrar Sesión</span>
                </a>
            </li>

        </ul>

    </aside>


    <!-- Feed Principal -->

    <main class="main">

        <div class="feed">

            <h1>Descubrir música</h1>

            <?php if (isset($errorFeed)): ?>

                <p class="post-text">
                    <?= escapar($errorFeed) ?>
                </p>

            <?php elseif (empty($publicaciones)): ?>

                <article class="post-card">

                    <h2 class="track-title">Todavía no hay publicaciones</h2>

                    <p class="post-text">
                        Sé el primero en compartir una pista con la comunidad.
                    </p>

                </article>

            <?php else: ?>

                <?php foreach ($publicaciones as $p): ?>

                    <article class="post-card">

                        <!-- Información del autor -->

                        <div class="post-header">

                            <div class="avatar"></div>

                            <div class="user-meta">

                                <span class="username">
                                    <?= escapar($p['autor']) ?>
                                </span>

                                <span class="date">
                                    <?= escapar(date('d/m/Y H:i', strtotime($p['fecha_publicacion']))) ?>
                                    · PISTA ORIGINAL
                                </span>

                            </div>

                        </div>


                        <!-- Portada y reproductor -->

                        <div class="audio-body">

                            <?php if (!empty($p['id_portada'])): ?>

                                <img
                                    src="php/imagen_portada.php?id=<?= (int)$p['id_publicacion'] ?>"
                                    class="cover-art"
                                    alt="Portada de <?= escapar($p['titulo']) ?>">

                            <?php else: ?>

                                <div
                                    class="cover-art"
                                    style="display:flex; align-items:center; justify-content:center;">
                                    <span>Sin portada</span>
                                </div>

                            <?php endif; ?>


                            <div class="track-info">

                                <h2 class="track-title">
                                    <?= escapar($p['titulo']) ?>
                                </h2>

                                <div class="waveform">

                                    <div class="wave-bar" style="height:10px;"></div>
                                    <div class="wave-bar" style="height:18px;"></div>
                                    <div class="wave-bar" style="height:13px;"></div>
                                    <div class="wave-bar active" style="height:24px;"></div>
                                    <div class="wave-bar active" style="height:32px;"></div>
                                    <div class="wave-bar active" style="height:18px;"></div>
                                    <div class="wave-bar" style="height:12px;"></div>
                                    <div class="wave-bar" style="height:8px;"></div>
                                    <div class="wave-bar" style="height:14px;"></div>
                                    <div class="wave-bar" style="height:20px;"></div>
                                    <div class="wave-bar" style="height:10px;"></div>

                                </div>

                            </div>

                        </div>


                        <!-- Reproductor de audio real -->

                        <?php if (!empty($p['ruta_audio'])): ?>

                            <audio
                                controls
                                preload="metadata"
                                style="width:100%; margin-top:15px;">

                                <source
                                    src="<?= escapar($p['ruta_audio']) ?>"
                                    type="audio/mpeg">

                                Tu navegador no soporta la reproducción de audio.

                            </audio>

                        <?php else: ?>

                            <p class="post-text">
                                Esta publicación no tiene un archivo de audio.
                            </p>

                        <?php endif; ?>


                        <!-- Descripción -->

                        <p class="post-text">
                            <?= nl2br(escapar($p['contenido'])) ?>
                        </p>


                        <!-- Acciones y contadores -->

                        <div class="post-actions">

                            <span class="action-btn">
                                ♡ <?= (int)$p['total_likes'] ?> LIKES
                            </span>

                            <a
                                href="post_detalles.html?id=<?= (int)$p['id_publicacion'] ?>"
                                class="action-btn"
                                style="text-decoration:none; padding-top:2px;">

                                ○ <?= (int)$p['total_comentarios'] ?> COMENTARIOS

                            </a>

                            <span class="action-btn">
                                ↗ <?= (int)$p['total_compartidos'] ?> COMPARTIDOS
                            </span>

                        </div>

                    </article>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </main>


    <!-- Modal Subir Pista -->

    <div id="modal-crear" class="modal-overlay">

        <div class="modal-box">

            <a href="#" class="modal-close">&times;</a>

            <h2 style="font-family: var(--font-logo); font-size: 28px; margin-bottom: 24px;">
                Subir Pista
            </h2>


            <form
                action="php/crear_publicacion.php"
                method="POST"
                enctype="multipart/form-data">


                <label class="label-mono" for="track-title">
                    Título de la pista
                </label>

                <input
                    type="text"
                    id="track-title"
                    name="titulo"
                    class="input-global"
                    placeholder="Ej. Midnight Synths..."
                    maxlength="150"
                    required>


                <label class="label-mono" for="track-desc">
                    Descripción
                </label>

                <textarea
                    id="track-desc"
                    name="contenido"
                    class="input-global"
                    rows="3"
                    maxlength="500"
                    placeholder="¿Qué sintetizadores usaste?"
                    required></textarea>


                <div style="display:flex; gap:16px; margin-bottom:24px;">

                    <div style="flex:1;">

                        <label class="label-mono" for="track-audio">
                            Archivo de Audio (.mp3)
                        </label>

                        <input
                            type="file"
                            id="track-audio"
                            name="audio"
                            class="input-global"
                            accept=".mp3,audio/mpeg"
                            required
                            style="padding:9px;">

                    </div>


                    <div style="flex:1;">

                        <label class="label-mono" for="track-cover">
                            Portada (Opcional)
                        </label>

                        <input
                            type="file"
                            id="track-cover"
                            name="portada"
                            class="input-global"
                            accept="image/jpeg,image/png,image/webp"
                            style="padding:9px;">

                    </div>

                </div>


                <div style="display:flex; gap:12px; justify-content:flex-end;">

                    <a
                        href="#"
                        class="btn-primary"
                        style="background:transparent; border:1px solid var(--ink-mono); width:auto;">
                        Cancelar
                    </a>

                    <button
                        type="submit"
                        class="btn-primary"
                        style="width:auto;">
                        Publicar Pista
                    </button>

                </div>

            </form>

        </div>

    </div>

</body>

</html>
