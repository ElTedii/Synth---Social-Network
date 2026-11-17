
<?php

session_start();

require_once 'php/Conexion.php';

// Verificar que el usuario haya iniciado sesión
if (!isset($_SESSION['id_usuario'])) {
    header('Location: login.html');
    exit;
}

// Conectar con la base de datos
$conexion = new Conexion();
$db = $conexion->getConexion();

if (!$db) {
    exit('No se pudo conectar con la base de datos.');
}

// Proteger los datos que se muestran en HTML
function escapar($valor) {
    return htmlspecialchars(
        (string)($valor ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

$id_usuario = (int)$_SESSION['id_usuario'];

$perfil = null;
$publicaciones = [];
$fotoPerfil = null;


// ==========================================
// 1. OBTENER DATOS DEL PERFIL
// ==========================================

try {

    $sql = "CALL sp_obtener_perfil(:id_usuario)";

    $stmt = $db->prepare($sql);

    $stmt->bindValue(
        ':id_usuario',
        $id_usuario,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $perfil = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt->closeCursor();

    if (!$perfil) {
        exit('No se encontró el perfil del usuario.');
    }

    // Convertir la foto BLOB a una imagen que pueda mostrar HTML
    if (!empty($perfil['foto_perfil'])) {

        $finfo = new finfo(FILEINFO_MIME_TYPE);

        $tipoImagen = $finfo->buffer($perfil['foto_perfil']);

        $tiposPermitidos = [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif'
        ];

        if (in_array($tipoImagen, $tiposPermitidos, true)) {

            $fotoPerfil = 'data:' . $tipoImagen . ';base64,' .
                base64_encode($perfil['foto_perfil']);

        }
    }

} catch (PDOException $e) {

    error_log('Error al obtener perfil: ' . $e->getMessage());

    exit('No se pudo cargar el perfil.');
}


// ==========================================
// 2. OBTENER PUBLICACIONES DEL USUARIO
// ==========================================

try {

    $sql = "CALL sp_listar_publicaciones_usuario(:id_usuario)";

    $stmt = $db->prepare($sql);

    $stmt->bindValue(
        ':id_usuario',
        $id_usuario,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $publicaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt->closeCursor();

} catch (PDOException $e) {

    error_log('Error al obtener publicaciones: ' . $e->getMessage());

    exit('No se pudieron cargar las publicaciones.');
}

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Mi Perfil - Synth</title>

    <link rel="stylesheet" href="Styles/style.css">

</head>

<body>

    <!-- FONDO -->

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


    <!-- BARRA LATERAL -->

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
                <a href="miperfil.php">
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


        <ul
            class="menu"
            style="margin-top: auto; position: absolute; bottom: 20px;">

            <li>
                <a href="login.html">
                    <span class="icon">⎋</span>
                    <span>Cerrar Sesión</span>
                </a>
            </li>

        </ul>

    </aside>


    <!-- CONTENIDO PRINCIPAL -->

    <main class="main">

        <div class="feed">


            <!-- CABECERA DEL PERFIL -->

            <section class="glass-panel profile-header">

                <div
                    class="profile-photo"
                    <?php if ($fotoPerfil): ?>
                        style="background-image: url('<?= $fotoPerfil ?>'); background-size: cover; background-position: center;"
                    <?php endif; ?>>
                </div>


                <div class="profile-info">

                    <span class="label-mono">
                        Perfil de Synth
                    </span>

                    <h1>
                        <?= escapar($perfil['nombre']) ?>
                    </h1>

                    <p>
                        <?= escapar($perfil['correo']) ?>
                    </p>


                    <!-- ESTADÍSTICAS -->

                    <div class="profile-stats">

                        <div>
                            <strong>
                                <?= (int)$perfil['total_pistas'] ?>
                            </strong>
                            Pistas
                        </div>

                        <div>
                            <strong>
                                <?= (int)$perfil['total_seguidores'] ?>
                            </strong>
                            Seguidores
                        </div>

                        <div>
                            <strong>
                                <?= (int)$perfil['total_siguiendo'] ?>
                            </strong>
                            Siguiendo
                        </div>

                    </div>


                    <!-- BOTONES -->

                    <div style="display: flex; gap: 16px;">

                        <a
                            href="Editar.html"
                            class="btn-primary"
                            style="width: auto;">

                            Editar Perfil

                        </a>

                        <button
                            type="button"
                            class="btn-primary"
                            onclick="navigator.clipboard.writeText(window.location.href)"
                            style="width: auto; background: transparent; border: 1px solid var(--ink-mono); color: var(--ink-main);">

                            Compartir

                        </button>

                    </div>

                </div>

            </section>


            <!-- PUBLICACIONES -->

            <h2
                class="feed-title"
                style="font-size: 18px;">

                Mis publicaciones

            </h2>


            <?php if (empty($publicaciones)): ?>

                <div class="glass-panel" style="padding: 25px;">

                    <p>
                        Todavía no has publicado ninguna pista.
                    </p>

                    <a href="index.php">
                        Ir al feed y subir una pista
                    </a>

                </div>

            <?php else: ?>


                <?php foreach ($publicaciones as $p): ?>

                    <article
                        class="post-card"
                        style="animation-delay: 0.1s;">


                        <!-- AUTOR -->

                        <div class="post-header">

                            <div
                                class="avatar"
                                <?php if ($fotoPerfil): ?>
                                    style="background-image: url('<?= $fotoPerfil ?>'); background-size: cover; background-position: center;"
                                <?php endif; ?>>
                            </div>

                            <div class="user-meta">

                                <span class="username">
                                    <?= escapar($perfil['nombre']) ?>
                                </span>

                                <span class="date">

                                    <?= escapar(
                                        date(
                                            'd/m/Y H:i',
                                            strtotime($p['fecha_publicacion'])
                                        )
                                    ) ?>

                                    · PISTA ORIGINAL

                                </span>

                            </div>

                        </div>


                        <!-- PORTADA Y TÍTULO -->

                        <div class="audio-body">

                            <?php if (!empty($p['id_portada'])): ?>

                                <img
                                    src="php/imagen_portada.php?id=<?= (int)$p['id_publicacion'] ?>"
                                    class="cover-art"
                                    alt="Portada de publicación">

                            <?php else: ?>

                                <div
                                    class="cover-art"
                                    style="display:flex; align-items:center; justify-content:center;">

                                    <span>Sin portada</span>

                                </div>

                            <?php endif; ?>


                            <div class="track-info">

                                <h2 class="track-title">

                                    <?= escapar($p['titulo'] ?? 'Publicación') ?>

                                </h2>

                            </div>

                        </div>


                        <!-- AUDIO -->

                        <?php if (!empty($p['ruta_audio'])): ?>

                            <audio
                                controls
                                preload="metadata"
                                style="width: 100%; margin-top: 15px;">

                                <source
                                    src="<?= escapar($p['ruta_audio']) ?>"
                                    type="audio/mpeg">

                                Tu navegador no soporta audio.

                            </audio>

                        <?php endif; ?>


                        <!-- DESCRIPCIÓN -->

                        <?php if (!empty($p['contenido'])): ?>

                            <p class="post-text">

                                <?= nl2br(escapar($p['contenido'])) ?>

                            </p>

                        <?php endif; ?>


                        <!-- INTERACCIONES -->

                        <div class="post-actions">

                            <span class="action-btn">

                                ♡ <?= (int)$p['total_likes'] ?> LIKES

                            </span>


                            <a
                                href="post_detalles.php?id=<?= (int)$p['id_publicacion'] ?>"
                                class="action-btn"
                                style="text-decoration: none;">

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

</body>

</html>
