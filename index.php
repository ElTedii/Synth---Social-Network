
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

// Comprobar que la conexión se haya realizado correctamente
if (!$db) {
    exit('No se pudo conectar con la base de datos.');
}

// Inicializar el arreglo de publicaciones
$publicaciones = [];

try {

    // Consultar las publicaciones mediante el procedimiento almacenado
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

    // Indicar que se desea listar las publicaciones
    $stmt->bindValue(':accion', 'listar', PDO::PARAM_STR);

    // No se necesita un ID de publicación específico
    $stmt->bindValue(':id_publicacion', null, PDO::PARAM_NULL);

    // Enviar el ID del usuario que inició sesión
    $stmt->bindValue(':id_usuario', (int) $_SESSION['id_usuario'], PDO::PARAM_INT);

    // Parámetros que no se necesitan para listar
    $stmt->bindValue(':titulo', null, PDO::PARAM_NULL);
    $stmt->bindValue(':contenido', null, PDO::PARAM_NULL);
    $stmt->bindValue(':archivo', null, PDO::PARAM_NULL);
    $stmt->bindValue(':ruta_audio', null, PDO::PARAM_NULL);

    $stmt->execute();

    // Obtener todas las publicaciones
    $publicaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt->closeCursor();

} catch (PDOException $e) {

    // Registrar el error
    error_log('Error al cargar publicaciones: ' . $e->getMessage());

    $errorFeed = 'No se pudieron cargar las publicaciones.';
}


// Función para mostrar datos de forma segura
function escapar($valor) {
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}


// Función para obtener la foto de perfil
function obtenerFotoPerfil($foto) {

    // Comprobar si existe una fotografía
    if (empty($foto)) {
        return null;
    }

    // Identificar el formato de la imagen
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $tipo = $finfo->buffer($foto);

    // Formatos permitidos
    $permitidos = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif'
    ];

    // Verificar que el formato esté permitido
    if (!in_array($tipo, $permitidos, true)) {
        return null;
    }

    // Convertir los datos binarios a Base64
    return 'data:' . $tipo . ';base64,' . base64_encode($foto);
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

    <style>

        /* Botón para dar o quitar like */
        .btn-like {
            background: transparent;
            border: none;
            cursor: pointer;
            font: inherit;
            color: inherit;
        }

        /* Estado cuando el usuario ya dio like */
        .btn-like[data-liked="1"] {
            color: #e65b78;
        }

        .btn-like:hover,
        .btn-ver-likes:hover {
            opacity: 0.7;
        }

        /* Botón para consultar quién dio like */
        .btn-ver-likes {
            background: transparent;
            border: none;
            cursor: pointer;
            font: inherit;
            color: inherit;
        }

        /* Ventana para mostrar los usuarios */
        #modal-likes {
            width: min(420px, 90%);
            border: 1px solid var(--ink-mono);
            border-radius: 12px;
            padding: 0;
            background: #171717;
            color: white;
        }

        #modal-likes::backdrop {
            background: rgba(0, 0, 0, 0.75);
        }

        .modal-likes-content {
            padding: 25px;
        }

        .modal-likes-content h2 {
            margin-top: 0;
            margin-bottom: 20px;
        }

        .usuario-like {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255,255,255,0.15);
        }

        .usuario-like span:last-child {
            font-size: 12px;
            opacity: 0.7;
        }

        #cerrar-modal-likes {
            margin-top: 20px;
        }

    </style>

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

                <!-- Mostrar el mensaje si ocurrió un error -->
                <p class="post-text">
                    <?= escapar($errorFeed) ?>
                </p>

            <?php elseif (empty($publicaciones)): ?>

                <!-- Mostrar este mensaje si no existen publicaciones -->
                <article class="post-card">

                    <h2 class="track-title">Todavía no hay publicaciones</h2>

                    <p class="post-text">
                        Sé el primero en compartir una pista con la comunidad.
                    </p>

                </article>

            <?php else: ?>

                <!-- Recorrer las publicaciones obtenidas -->
                <?php foreach ($publicaciones as $p): ?>

                    <article class="post-card">

                        <!-- Información del autor -->

                        <div class="post-header">

                            <?php
                                // Obtener la fotografía del autor
                                $fotoAutor = obtenerFotoPerfil($p['foto_perfil'] ?? null);

                                // Crear el enlace hacia su perfil
                                $urlPerfil = 'perfil.php?id=' . (int)$p['id_usuario'];

                                // Comprobar si el usuario ya dio like
                                $usuarioDioLike = !empty($p['usuario_dio_like']);
                            ?>

                            <!-- Foto del autor -->

                            <a
                                href="<?= escapar($urlPerfil) ?>"
                                style="text-decoration: none;">

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

                            </a>


                            <div class="user-meta">

                                <!-- Nombre del autor -->

                                <a
                                    href="<?= escapar($urlPerfil) ?>"
                                    style="text-decoration: none; color: inherit;">

                                    <span class="username">
                                        <?= escapar($p['autor']) ?>
                                    </span>

                                </a>

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

                            <!-- Botón para dar o quitar like -->

                            <button
                                type="button"
                                class="action-btn btn-like"
                                data-id="<?= (int)$p['id_publicacion'] ?>"
                                data-liked="<?= $usuarioDioLike ? '1' : '0' ?>">

                                <span class="like-icon">
                                    <?= $usuarioDioLike ? '♥' : '♡' ?>
                                </span>

                                <span class="like-text">
                                    <?= $usuarioDioLike ? 'TE GUSTA' : 'LIKE' ?>
                                </span>

                                <span class="like-count">
                                    <?= (int)$p['total_likes'] ?>
                                </span>

                            </button>


                            <!-- Consultar quién dio like -->

                            <button
                                type="button"
                                class="action-btn btn-ver-likes"
                                data-id="<?= (int)$p['id_publicacion'] ?>">

                                Ver quién dio like

                            </button>


                            <!-- Comentarios -->

                            <a
                                href="post_detalles.php?id=<?= (int)$p['id_publicacion'] ?>"
                                class="action-btn"
                                style="text-decoration:none; padding-top:2px;">

                                ○ <?= (int)$p['total_comentarios'] ?> COMENTARIOS

                            </a>


                            <!-- Compartidos -->

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


    <!-- Modal para consultar quién dio like -->

    <dialog id="modal-likes">

        <div class="modal-likes-content">

            <h2>Personas que dieron like</h2>

            <div id="lista-usuarios-likes">
                Cargando usuarios...
            </div>

            <button
                type="button"
                id="cerrar-modal-likes"
                class="btn-primary">

                Cerrar

            </button>

        </div>

    </dialog>


    <!-- JavaScript para gestionar los likes -->

    <script>

    // Obtener los elementos de la ventana de likes
    const modalLikes = document.getElementById('modal-likes');

    const listaUsuariosLikes = document.getElementById('lista-usuarios-likes');

    const cerrarModalLikes = document.getElementById('cerrar-modal-likes');


    // ==========================================
    // DAR O QUITAR LIKE
    // ==========================================

    document.querySelectorAll('.btn-like').forEach(boton => {

        boton.addEventListener('click', async function () {

            // Obtener el ID de la publicación
            const idPublicacion = this.dataset.id;

            // Evitar múltiples clics mientras se procesa la petición
            this.disabled = true;

            const datos = new FormData();

            datos.append('accion', 'toggle');
            datos.append('id_publicacion', idPublicacion);

            try {

                // Enviar la petición al archivo PHP
                const respuesta = await fetch('php/gestionar_likes.php', {
                    method: 'POST',
                    body: datos
                });

                const resultado = await respuesta.json();

                // Comprobar si ocurrió un error
                if (!respuesta.ok || !resultado.success) {
                    throw new Error(resultado.error || 'No se pudo registrar el like.');
                }

                // Actualizar el estado del botón
                this.dataset.liked = resultado.usuario_dio_like ? '1' : '0';

                // Actualizar el símbolo
                this.querySelector('.like-icon').textContent =
                    resultado.usuario_dio_like ? '♥' : '♡';

                // Actualizar el texto
                this.querySelector('.like-text').textContent =
                    resultado.usuario_dio_like ? 'TE GUSTA' : 'LIKE';

                // Actualizar el contador
                this.querySelector('.like-count').textContent =
                    resultado.total_likes;

            } catch (error) {

                alert(error.message);

            } finally {

                // Habilitar nuevamente el botón
                this.disabled = false;

            }

        });

    });


    // ==========================================
    // CONSULTAR QUIÉN DIO LIKE
    // ==========================================

    document.querySelectorAll('.btn-ver-likes').forEach(boton => {

        boton.addEventListener('click', async function () {

            const idPublicacion = this.dataset.id;

            // Abrir la ventana
            modalLikes.showModal();

            // Mostrar mensaje de carga
            listaUsuariosLikes.textContent = 'Cargando usuarios...';

            const datos = new FormData();

            datos.append('accion', 'listar');
            datos.append('id_publicacion', idPublicacion);

            try {

                // Consultar los usuarios mediante el archivo PHP
                const respuesta = await fetch('php/gestionar_likes.php', {
                    method: 'POST',
                    body: datos
                });

                const resultado = await respuesta.json();

                if (!respuesta.ok || !resultado.success) {
                    throw new Error(resultado.error || 'No se pudieron consultar los likes.');
                }

                // Limpiar la lista
                listaUsuariosLikes.replaceChildren();

                // Comprobar si hay usuarios
                if (resultado.usuarios.length === 0) {

                    listaUsuariosLikes.textContent =
                        'Todavía nadie ha dado like a esta publicación.';

                    return;
                }

                // Recorrer los usuarios que dieron like
                resultado.usuarios.forEach(usuario => {

                    const fila = document.createElement('div');

                    fila.className = 'usuario-like';

                    // Crear el elemento del nombre
                    const nombre = document.createElement('span');

                    nombre.textContent = usuario.nombre_usuario;

                    // Crear el elemento de la fecha
                    const fecha = document.createElement('span');

                    const fechaLike = new Date(
                        usuario.fecha_like.replace(' ', 'T')
                    );

                    fecha.textContent = fechaLike.toLocaleString('es-MX');

                    // Agregar los elementos a la fila
                    fila.appendChild(nombre);
                    fila.appendChild(fecha);

                    // Agregar la fila a la ventana
                    listaUsuariosLikes.appendChild(fila);

                });

            } catch (error) {

                listaUsuariosLikes.textContent = error.message;

            }

        });

    });


    // ==========================================
    // CERRAR LA VENTANA DE LIKES
    // ==========================================

    cerrarModalLikes.addEventListener('click', function () {

        modalLikes.close();

    });

    </script>

</body>

</html>
