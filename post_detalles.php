<?php

session_start();

require_once 'php/Conexion.php';

// Verificar sesión
if (!isset($_SESSION['id_usuario'])) {
    header('Location: login.html');
    exit;
}

// Obtener ID de publicación
$id_publicacion = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id_publicacion || $id_publicacion <= 0) {
    exit('ID de publicación no válido.');
}

// Conectar con MySQL
$conexion = new Conexion();
$db = $conexion->getConexion();

if (!$db) {
    exit('No se pudo conectar con la base de datos.');
}

// Función para proteger la salida HTML
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
    $tipo = $finfo->buffer($foto);

    $permitidos = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif'
    ];

    if (!in_array($tipo, $permitidos, true)) {
        return null;
    }

    return 'data:' . $tipo . ';base64,' . base64_encode($foto);
}

$publicacion = null;
$comentarios = [];
$error = '';


// ==========================================
// PROCESAR NUEVO COMENTARIO
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $contenido = trim($_POST['contenido'] ?? '');

    if ($contenido === '') {

        $error = 'Escribe un comentario antes de enviarlo.';

    } elseif (mb_strlen($contenido, 'UTF-8') > 300) {

        $error = 'El comentario no puede superar los 300 caracteres.';

    } else {

        try {

            $sql = "CALL sp_gestion_comentarios(
                :accion,
                :id_publicacion,
                :id_usuario,
                :contenido
            )";

            $stmt = $db->prepare($sql);

            $stmt->bindValue(':accion', 'agregar', PDO::PARAM_STR);

            $stmt->bindValue(
                ':id_publicacion',
                $id_publicacion,
                PDO::PARAM_INT
            );

            // El ID del usuario se obtiene de la sesión.
            $stmt->bindValue(
                ':id_usuario',
                (int)$_SESSION['id_usuario'],
                PDO::PARAM_INT
            );

            $stmt->bindValue(
                ':contenido',
                $contenido,
                PDO::PARAM_STR
            );

            $stmt->execute();

            $stmt->closeCursor();

            // Evitar que se vuelva a enviar el formulario
            header('Location: post_detalles.php?id=' . $id_publicacion . '&comentario=ok');
            exit;

        } catch (PDOException $e) {

            error_log('Error al agregar comentario: ' . $e->getMessage());

            $error = 'No se pudo agregar el comentario.';
        }
    }
}


// ==========================================
// OBTENER PUBLICACIÓN
// ==========================================

try {

$sql = "CALL sp_gestion_publicaciones(
        :accion,
        :id_publicacion,
        :id_usuario,
        :titulo,
        :contenido,
        :archivo,
        :ruta_audio,
        :ruta_video
    )";

    $stmt = $db->prepare($sql);

    $stmt->bindValue(':accion', 'obtener', PDO::PARAM_STR);

    $stmt->bindValue(
        ':id_publicacion',
        $id_publicacion,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        ':id_usuario',
        (int)$_SESSION['id_usuario'],
        PDO::PARAM_INT
    );

    $stmt->bindValue(':titulo', null, PDO::PARAM_NULL);
    $stmt->bindValue(':contenido', null, PDO::PARAM_NULL);
    $stmt->bindValue(':archivo', null, PDO::PARAM_NULL);
    $stmt->bindValue(':ruta_audio', null, PDO::PARAM_NULL);
    $stmt->bindValue(':ruta_video', null, PDO::PARAM_NULL);

    $stmt->execute();

    $publicacion = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt->closeCursor();

    if (!$publicacion) {
        exit('La publicación no existe o ya no está disponible.');
    }

} catch (PDOException $e) {

    error_log('Error al obtener publicación: ' . $e->getMessage());

    exit('No se pudo cargar la publicación.');
}


// ==========================================
// OBTENER COMENTARIOS
// ==========================================

try {

    $sql = "CALL sp_gestion_comentarios(
        :accion,
        :id_publicacion,
        :id_usuario,
        :contenido
    )";

    $stmt = $db->prepare($sql);

    $stmt->bindValue(':accion', 'listar', PDO::PARAM_STR);

    $stmt->bindValue(
        ':id_publicacion',
        $id_publicacion,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        ':id_usuario',
        (int)$_SESSION['id_usuario'],
        PDO::PARAM_INT
    );

    $stmt->bindValue(':contenido', null, PDO::PARAM_NULL);

    $stmt->execute();

    $comentarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt->closeCursor();

} catch (PDOException $e) {

    error_log('Error al obtener comentarios: ' . $e->getMessage());

    $error = 'No se pudieron cargar los comentarios.';
}

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Publicación - Synth</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="Styles/style.css">

    <style>

        .comments-section {
            margin-top: 20px;
            border-top: 1px solid #292929;
            padding-top: 20px;
        }

        .comment {
            display: flex;
            margin-bottom: 15px;
        }

        .comment-content {
            background-color: #151515;
            padding: 12px 15px;
            border-radius: 8px;
            flex: 1;
            margin-left: 12px;
        }

        .comment-header {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 5px;
        }

        .comment-header strong {
            font-size: 14px;
        }

        .comment-header span {
            font-size: 12px;
            color: #777;
        }

        .comment-body {
            font-size: 14px;
            color: #ddd;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }

        .add-comment {
            display: flex;
            margin-top: 20px;
            align-items: center;
        }

        .add-comment {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            width: 100%;
        }

        .add-comment input {
            flex: 1 1 0;
            width: 0;
            min-width: 0;
            box-sizing: border-box;
            padding: 12px;
            color: #ffffff !important;
            -webkit-text-fill-color: #ffffff;
            background-color: #151515 !important;
            border: 1px solid #444;
            border-radius: 8px;
            font-size: 14px;
        }

        .add-comment input::placeholder {
            color: #888888;
            -webkit-text-fill-color: #888888;
            opacity: 1;
        }

        .add-comment input:focus {
            outline: 1px solid #555;
        }

        .add-comment .btn-primary {
            flex: 0 0 auto;
            width: auto;
            white-space: nowrap;
        }
        .comments-title {
            font-size: 16px;
            margin-bottom: 20px;
        }

        .comments-empty {
            color: #888;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .comment-error {
            color: #ff7777;
            margin-top: 12px;
            font-size: 14px;
        }

        .comment-success {
            color: #8bd49b;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .add-comment input::placeholder {
            color: #888888;
            opacity: 1;
        }

        .add-comment input:focus {
            outline: 1px solid #555555;
        }

    </style>

</head>

<body>

    <main class="main">

        <div class="feed">

            <a
                href="index.php"
                style="color: #aaa; text-decoration: none; margin-bottom: 15px; display: inline-block;">

                ← Volver al feed

            </a>


            <!-- PUBLICACIÓN -->

            <article
                class="post-card"
                style="margin-bottom: 0; border-bottom-left-radius: 0; border-bottom-right-radius: 0;">

                <!-- Autor -->

                <div class="post-header">

                    <?php
                        $fotoAutor = obtenerFotoPerfil(
                            $publicacion['foto_perfil'] ?? null
                        );
                        ?>

                        <div
                            class="avatar"
                            <?php if ($fotoAutor): ?>
                                style="
                                    background-image: url('<?= escapar($fotoAutor) ?>');
                                    background-size: cover;
                                    background-position: center;
                                    background-repeat: no-repeat;
                                "
                            <?php endif; ?>>
                        </div>

                    <div class="user-meta">

                        <span class="username">
                            <?= escapar($publicacion['autor']) ?>
                        </span>

                        <span class="date">

                            <?= escapar(
                                date(
                                    'd/m/Y H:i',
                                    strtotime($publicacion['fecha_publicacion'])
                                )
                            ) ?>

                            · PISTA ORIGINAL

                        </span>

                    </div>

                </div>


                <!-- Portada y título -->

                <div class="audio-body">

                    <?php if (!empty($publicacion['id_portada'])): ?>

                        <img
                            src="php/imagen_portada.php?id=<?= (int)$publicacion['id_publicacion'] ?>"
                            class="cover-art"
                            alt="Portada de <?= escapar($publicacion['titulo']) ?>">

                    <?php else: ?>

                        <div
                            class="cover-art"
                            style="display:flex; align-items:center; justify-content:center;">

                            <span>Sin portada</span>

                        </div>

                    <?php endif; ?>


                    <div class="track-info">

                        <h2 class="track-title">

                            <?= escapar($publicacion['titulo']) ?>

                        </h2>

                    </div>

                </div>


                <!-- Reproductor -->
                <?php if (!empty($publicacion['ruta_audio'])): ?>
                    <audio controls preload="metadata" style="width:100%; margin-top:15px;">
                        <source src="<?= escapar($publicacion['ruta_audio']) ?>" type="audio/mpeg">
                        Tu navegador no soporta la reproducción de audio.
                    </audio>
                <?php elseif (!empty($publicacion['ruta_video'])): ?>
                    <?php
                        $extVideo = strtolower(pathinfo($publicacion['ruta_video'], PATHINFO_EXTENSION));
                        $mimeVideo = ($extVideo === 'webm') ? 'video/webm' : 'video/mp4';
                    ?>
                    <video controls preload="metadata" style="width:100%; margin-top:15px; border-radius:10px; max-height:420px; background:#000;">
                        <source src="<?= escapar($publicacion['ruta_video']) ?>" type="<?= $mimeVideo ?>">
                        Tu navegador no soporta la reproducción de video.
                    </video>
                <?php endif; ?>


                <!-- Descripción -->

                <p class="post-text">

                    <?= nl2br(escapar($publicacion['contenido'])) ?>

                </p>

            </article>


            <!-- SECCIÓN DE COMENTARIOS -->

            <div
                class="post"
                style="border-top-left-radius: 0; border-top-right-radius: 0; padding: 20px;">

                <h3 class="comments-title">

                    Comentarios (<?= count($comentarios) ?>)

                </h3>


                <?php if (isset($_GET['comentario']) && $_GET['comentario'] === 'ok'): ?>

                    <p class="comment-success">
                        Tu comentario se agregó correctamente.
                    </p>

                <?php endif; ?>


                <?php if (!empty($error)): ?>

                    <p class="comment-error">
                        <?= escapar($error) ?>
                    </p>

                <?php endif; ?>


                <!-- Mostrar comentarios reales -->

                <?php if (empty($comentarios)): ?>

                    <p class="comments-empty">
                        Todavía no hay comentarios. ¡Sé el primero en comentar!
                    </p>

                <?php else: ?>

                    <?php foreach ($comentarios as $comentario): ?>

                        <div class="comment">

                            <div class="avatar-small"></div>

                            <div class="comment-content">

                                <div class="comment-header">

                                    <strong>
                                        <?= escapar($comentario['nombre_usuario']) ?>
                                    </strong>

                                    <span>

                                        <?= escapar(
                                            date(
                                                'd/m/Y H:i',
                                                strtotime($comentario['fecha_comentario'])
                                            )
                                        ) ?>

                                    </span>

                                </div>

                                <div class="comment-body">

                                    <?= nl2br(escapar($comentario['contenido'])) ?>

                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>


                <!-- FORMULARIO PARA AGREGAR COMENTARIO -->

                <form
                    action="post_detalles.php?id=<?= (int)$id_publicacion ?>"
                    method="POST"
                    class="add-comment">

                    <div class="avatar-small"></div>

                    <input
                        type="text"
                        name="contenido"
                        class="input-global"
                        placeholder="Escribe un comentario..."
                        maxlength="300"
                        required>

                    <button
                        type="submit"
                        class="btn-primary">

                        Comentar

                    </button>

                </form>

            </div>

        </div>

    </main>

</body>

</html>
