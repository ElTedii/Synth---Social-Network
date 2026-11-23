
<?php
session_start();

require_once 'php/Conexion.php';

// ==========================================
// 1. VERIFICAR SESIÓN
// ==========================================

if (!isset($_SESSION['id_usuario'])) {
    header('Location: login.html');
    exit;
}

// ==========================================
// 2. CONECTAR CON LA BASE DE DATOS
// ==========================================

$conexion = new Conexion();
$db = $conexion->getConexion();

if (!$db) {
    exit('No se pudo conectar con la base de datos.');
}

// ==========================================
// 3. FUNCIONES AUXILIARES
// ==========================================

function escapar($valor) {
    return htmlspecialchars(
        (string)($valor ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function obtenerFotoPerfil($foto) {

    if (empty($foto)) {
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $tipoImagen = $finfo->buffer($foto);

    $tiposPermitidos = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif'
    ];

    if (!in_array($tipoImagen, $tiposPermitidos, true)) {
        return null;
    }

    return 'data:' . $tipoImagen . ';base64,' .
        base64_encode($foto);
}

// ==========================================
// 4. OBTENER ID DEL PERFIL VISITADO
// ==========================================

$id_usuario_actual = (int)$_SESSION['id_usuario'];

$id_perfil = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id_perfil || $id_perfil <= 0) {
    exit('El perfil solicitado no es válido.');
}

// Si el usuario visita su propio perfil,
// enviarlo a miperfil.php.

if ($id_perfil === $id_usuario_actual) {
    header('Location: miperfil.php');
    exit;
}

// ==========================================
// 5. TOKEN DE SEGURIDAD
// ==========================================

if (empty($_SESSION['csrf_perfil'])) {
    $_SESSION['csrf_perfil'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf_perfil'];

$mensaje = '';

// ==========================================
// 6. OBTENER INFORMACIÓN DEL PERFIL
// ==========================================

try {

    $sql = "CALL sp_obtener_perfil(:id_usuario)";

    $stmt = $db->prepare($sql);

    $stmt->bindValue(
        ':id_usuario',
        $id_perfil,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $perfil = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt->closeCursor();

    if (!$perfil) {
        exit('No se encontró el perfil solicitado.');
    }

    $fotoPerfil = obtenerFotoPerfil(
        $perfil['foto_perfil'] ?? null
    );

} catch (PDOException $e) {

    error_log('Error al obtener perfil: ' . $e->getMessage());

    exit('No se pudo cargar el perfil.');
}

// ==========================================
// 7. PROCESAR SEGUIR / DEJAR DE SEGUIR
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $accion = $_POST['accion'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($csrf, $token)) {
        exit('Solicitud no válida.');
    }

    if (!in_array($accion, ['seguir', 'dejar_seguir'], true)) {
        exit('Acción no válida.');
    }

    try {

        $sql = "CALL sp_gestion_seguidores(
            :accion,
            :id_seguidor,
            :id_seguido
        )";

        $stmt = $db->prepare($sql);

        $stmt->bindValue(
            ':accion',
            $accion,
            PDO::PARAM_STR
        );

        $stmt->bindValue(
            ':id_seguidor',
            $id_usuario_actual,
            PDO::PARAM_INT
        );

        $stmt->bindValue(
            ':id_seguido',
            $id_perfil,
            PDO::PARAM_INT
        );

        $stmt->execute();

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt->closeCursor();

        // Redirigir para evitar repetir el formulario
        // si el usuario actualiza la página.

        header('Location: perfil.php?id=' . $id_perfil);
        exit;

    } catch (PDOException $e) {

        error_log('Error en seguimiento: ' . $e->getMessage());

        $mensaje = 'No se pudo actualizar el seguimiento.';
    }
}

// ==========================================
// 8. CONSULTAR ESTADO DEL SEGUIMIENTO
// ==========================================

$yaLoSigue = false;

try {

    $sql = "CALL sp_gestion_seguidores(
        :accion,
        :id_seguidor,
        :id_seguido
    )";

    $stmt = $db->prepare($sql);

    $stmt->bindValue(':accion', 'estado', PDO::PARAM_STR);
    $stmt->bindValue(':id_seguidor', $id_usuario_actual, PDO::PARAM_INT);
    $stmt->bindValue(':id_seguido', $id_perfil, PDO::PARAM_INT);

    $stmt->execute();

    $estado = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt->closeCursor();

    $yaLoSigue = (bool)($estado['siguiendo'] ?? false);

} catch (PDOException $e) {

    error_log('Error al consultar seguimiento: ' . $e->getMessage());

    $mensaje = 'No se pudo consultar el seguimiento.';
}

// ==========================================
// 9. OBTENER PUBLICACIONES DEL USUARIO
// ==========================================

$publicaciones = [];

try {

    $sql = "CALL sp_listar_publicaciones_usuario(:id_usuario)";

    $stmt = $db->prepare($sql);

    $stmt->bindValue(
        ':id_usuario',
        $id_perfil,
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

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Perfil - Synth</title>

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


    <!-- CONTENIDO PRINCIPAL -->

    <main class="main">

        <div class="feed">

            <!-- CABECERA DEL PERFIL -->

            <section class="glass-panel profile-header">

                <div
                    class="profile-photo"
                    <?php if ($fotoPerfil): ?>
                        style="background-image: url('<?= escapar($fotoPerfil) ?>'); background-size: cover; background-position: center;"
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


                    <!-- BOTÓN SEGUIR / DEJAR DE SEGUIR -->

                    <form method="POST" style="display: flex; gap: 16px;">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= escapar($csrf) ?>">

                        <?php if ($yaLoSigue): ?>

                            <button
                                type="submit"
                                name="accion"
                                value="dejar_seguir"
                                class="btn-primary"
                                style="width: auto;">

                                Dejar de seguir

                            </button>

                        <?php else: ?>

                            <button
                                type="submit"
                                name="accion"
                                value="seguir"
                                class="btn-primary"
                                style="width: auto;">

                                Seguir

                            </button>

                        <?php endif; ?>

                        <button
                            type="button"
                            class="btn-primary"
                            onclick="navigator.clipboard.writeText(window.location.href)"
                            style="width: auto; background: transparent; border: 1px solid var(--ink-mono); color: var(--ink-main);">

                            Compartir

                        </button>

                    </form>

                    <?php if ($mensaje): ?>

                        <p><?= escapar($mensaje) ?></p>

                    <?php endif; ?>

                </div>

            </section>


            <!-- PUBLICACIONES -->

            <h2
                class="feed-title"
                style="font-size: 18px;">

                Publicaciones de <?= escapar($perfil['nombre']) ?>

            </h2>


            <?php if (empty($publicaciones)): ?>

                <div class="glass-panel" style="padding: 25px;">

                    <p>
                        Este usuario todavía no ha publicado ninguna pista.
                    </p>

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
                                    style="background-image: url('<?= escapar($fotoPerfil) ?>'); background-size: cover; background-position: center;"
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

                        <!-- AUDIO O VIDEO -->
                        <?php if (!empty($p['ruta_audio'])): ?>
                            <audio controls preload="metadata" style="width: 100%; margin-top: 15px;">
                                <source src="<?= escapar($p['ruta_audio']) ?>" type="audio/mpeg">
                                Tu navegador no soporta audio.
                            </audio>
                        <?php elseif (!empty($p['ruta_video'])): ?>
                            <?php
                                $extVideo = strtolower(pathinfo($p['ruta_video'], PATHINFO_EXTENSION));
                                $mimeVideo = ($extVideo === 'webm') ? 'video/webm' : 'video/mp4';
                            ?>
                            <video controls preload="metadata" style="width: 100%; margin-top: 15px; border-radius: 10px; max-height: 420px; background: #000;">
                                <source src="<?= escapar($p['ruta_video']) ?>" type="<?= $mimeVideo ?>">
                                Tu navegador no soporta video.
                            </video>
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
