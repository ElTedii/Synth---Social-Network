-- =================================================================================
-- BASE DE DATOS: red_social_bdm (Proyecto Synth - BDM)
-- Versión v2: 100% compatible con phpMyAdmin Importar (sin CALL en datos iniciales)
-- =================================================================================

DROP DATABASE IF EXISTS red_social_bdm;
CREATE DATABASE red_social_bdm
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE red_social_bdm;

-- =================================================================================
-- 1. TABLAS PRINCIPALES Y CATÁLOGOS (3FN)
-- =================================================================================

CREATE TABLE usuarios (
    id_usuario       INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del usuario',
    nombre           VARCHAR(100) NOT NULL COMMENT 'Nombre del usuario',
    correo           VARCHAR(150) NOT NULL COMMENT 'Correo electrónico único del usuario',
    contrasena       VARCHAR(255) NOT NULL COMMENT 'Hash seguro de la contraseña del usuario',
    foto_perfil      MEDIUMBLOB NULL COMMENT 'Imagen de perfil almacenada en formato binario',
    estado           ENUM('activo','inactivo') NOT NULL DEFAULT 'activo' COMMENT 'Estado actual de la cuenta del usuario',
    CONSTRAINT uq_usuarios_correo UNIQUE (correo)
) ENGINE=InnoDB;

CREATE TABLE cat_instrumentos (
    id_instrumento   INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del instrumento',
    nombre           VARCHAR(50) NOT NULL COMMENT 'Nombre del instrumento musical',
    CONSTRAINT uq_cat_instrumentos_nombre UNIQUE (nombre)
) ENGINE=InnoDB;

CREATE TABLE cat_generos (
    id_genero        INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del género musical',
    nombre           VARCHAR(50) NOT NULL COMMENT 'Nombre del género musical',
    CONSTRAINT uq_cat_generos_nombre UNIQUE (nombre)
) ENGINE=InnoDB;

CREATE TABLE usuarios_instrumentos (
    id_usuario       INT NOT NULL COMMENT 'Usuario asociado al instrumento',
    id_instrumento   INT NOT NULL COMMENT 'Instrumento que domina el usuario',
    nivel_dominio    ENUM('principiante','intermedio','avanzado','experto') NOT NULL DEFAULT 'intermedio' COMMENT 'Nivel de dominio del instrumento',
    PRIMARY KEY (id_usuario, id_instrumento),
    CONSTRAINT fk_ui_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    CONSTRAINT fk_ui_instrumento FOREIGN KEY (id_instrumento) REFERENCES cat_instrumentos(id_instrumento) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE usuarios_generos (
    id_usuario       INT NOT NULL COMMENT 'Usuario asociado al género musical',
    id_genero        INT NOT NULL COMMENT 'Género musical de interés',
    relacion         ENUM('escucha','toca','ambos') NOT NULL DEFAULT 'escucha' COMMENT 'Relación del usuario con el género musical',
    PRIMARY KEY (id_usuario, id_genero),
    CONSTRAINT fk_ug_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    CONSTRAINT fk_ug_genero FOREIGN KEY (id_genero) REFERENCES cat_generos(id_genero) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE auditoria_usuarios (
    id_auditoria     INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del registro de auditoría',
    id_usuario       INT NOT NULL COMMENT 'Usuario al que corresponde la acción registrada',
    accion_realizada VARCHAR(255) NOT NULL COMMENT 'Descripción de la acción realizada',
    fecha_accion     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora en que se realizó la acción',
    CONSTRAINT fk_auditoria_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE publicaciones (
    id_publicacion     INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único de la publicación',
    id_usuario         INT NOT NULL COMMENT 'Usuario que creó la publicación',
    titulo             VARCHAR(150) NULL COMMENT 'Título de la pista o publicación',
    contenido          VARCHAR(500) NULL COMMENT 'Descripción o texto de la publicación',
    fecha_publicacion  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación de la publicación',
    estado             ENUM('activa','eliminada') NOT NULL DEFAULT 'activa' COMMENT 'Estado actual de la publicación',
    CONSTRAINT fk_publicaciones_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE multimedia (
    id_multimedia    INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del archivo multimedia',
    id_publicacion   INT NOT NULL COMMENT 'Publicación a la que pertenece el archivo',
    tipo             ENUM('imagen','video','audio','partitura') NOT NULL COMMENT 'Tipo de archivo multimedia',
    archivo          MEDIUMBLOB NULL COMMENT 'Contenido binario del archivo multimedia (BLOB)',
    ruta_archivo     VARCHAR(255) NULL COMMENT 'Ruta o ubicación del archivo en el servidor',
    fecha_carga      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de carga del archivo',
    CONSTRAINT fk_multimedia_publicacion FOREIGN KEY (id_publicacion) REFERENCES publicaciones(id_publicacion) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE likes (
    id_like          INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del like',
    id_publicacion   INT NOT NULL COMMENT 'Publicación que recibió el like',
    id_usuario       INT NOT NULL COMMENT 'Usuario que dio el like',
    fecha_like       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora en que se dio el like',
    CONSTRAINT fk_likes_publicacion FOREIGN KEY (id_publicacion) REFERENCES publicaciones(id_publicacion) ON DELETE CASCADE,
    CONSTRAINT fk_likes_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    CONSTRAINT uq_likes_publicacion_usuario UNIQUE (id_publicacion, id_usuario)
) ENGINE=InnoDB;

CREATE TABLE comentarios (
    id_comentario      INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del comentario',
    id_publicacion     INT NOT NULL COMMENT 'Publicación donde se escribió el comentario',
    id_usuario         INT NOT NULL COMMENT 'Usuario que escribió el comentario',
    contenido          VARCHAR(300) NOT NULL COMMENT 'Texto del comentario',
    fecha_comentario   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de publicación del comentario',
    CONSTRAINT fk_comentarios_publicacion FOREIGN KEY (id_publicacion) REFERENCES publicaciones(id_publicacion) ON DELETE CASCADE,
    CONSTRAINT fk_comentarios_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE compartidos (
    id_compartido        INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del registro compartido',
    id_publicacion       INT NOT NULL COMMENT 'Publicación original que se comparte',
    id_usuario           INT NOT NULL COMMENT 'Usuario que compartió la publicación',
    comentario           VARCHAR(300) NULL COMMENT 'Comentario opcional al compartir la publicación',
    fecha_compartido     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora en que se compartió la publicación',
    CONSTRAINT fk_compartidos_publicacion FOREIGN KEY (id_publicacion) REFERENCES publicaciones(id_publicacion) ON DELETE CASCADE,
    CONSTRAINT fk_compartidos_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE seguidores (
    id_seguimiento      INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único de la relación de seguimiento',
    id_seguidor         INT NOT NULL COMMENT 'Usuario que sigue a otra cuenta',
    id_seguido          INT NOT NULL COMMENT 'Usuario al que se sigue',
    fecha_seguimiento   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora en que comenzó el seguimiento',
    CONSTRAINT fk_seguidores_seguidor FOREIGN KEY (id_seguidor) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    CONSTRAINT fk_seguidores_seguido FOREIGN KEY (id_seguido) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    CONSTRAINT uq_seguidores_par UNIQUE (id_seguidor, id_seguido),
    CONSTRAINT chk_seguidores_no_auto CHECK (id_seguidor <> id_seguido)
) ENGINE=InnoDB;

CREATE TABLE anotaciones_colaborativas (
    id_anotacion       INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único de la anotación colaborativa',
    id_publicacion     INT NOT NULL COMMENT 'Publicación musical anotada',
    id_usuario         INT NOT NULL COMMENT 'Usuario que aporta la explicación',
    referencia_tiempo  VARCHAR(20) NULL COMMENT 'Marca de tiempo referenciada (ej. 01:24 - 01:40)',
    referencia_letra   VARCHAR(150) NULL COMMENT 'Fragmento de letra o partitura referenciado',
    explicacion        TEXT NOT NULL COMMENT 'Explicación o análisis colaborativo',
    fecha_anotacion    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de la anotación',
    CONSTRAINT fk_anotaciones_publicacion FOREIGN KEY (id_publicacion) REFERENCES publicaciones(id_publicacion) ON DELETE CASCADE,
    CONSTRAINT fk_anotaciones_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =================================================================================
-- 2. TRIGGERS (3)
-- =================================================================================
DELIMITER //

CREATE TRIGGER trg_auditoria_usuarios
AFTER UPDATE ON usuarios
FOR EACH ROW
BEGIN
    IF OLD.estado <> NEW.estado THEN
        INSERT INTO auditoria_usuarios (id_usuario, accion_realizada)
        VALUES (OLD.id_usuario, CONCAT('El estado cambió de ', OLD.estado, ' a ', NEW.estado));
    END IF;

    IF OLD.correo <> NEW.correo THEN
        INSERT INTO auditoria_usuarios (id_usuario, accion_realizada)
        VALUES (OLD.id_usuario, CONCAT('Correo actualizado de ', OLD.correo, ' a ', NEW.correo));
    END IF;
END //

CREATE TRIGGER trg_validar_like
BEFORE INSERT ON likes
FOR EACH ROW
BEGIN
    DECLARE v_estado_pub ENUM('activa','eliminada');
    SELECT estado INTO v_estado_pub
    FROM publicaciones
    WHERE id_publicacion = NEW.id_publicacion;

    IF v_estado_pub = 'eliminada' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No puedes dar like a una publicación eliminada.';
    END IF;
END //

CREATE TRIGGER trg_evitar_auto_seguimiento
BEFORE INSERT ON seguidores
FOR EACH ROW
BEGIN
    IF NEW.id_seguidor = NEW.id_seguido THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Un usuario no puede seguirse a sí mismo.';
    END IF;
END //

-- =================================================================================
-- 3. FUNCIONES ESCALARES (2)
-- =================================================================================

CREATE FUNCTION fn_tiempo_transcurrido(p_fecha DATETIME)
RETURNS VARCHAR(50)
NOT DETERMINISTIC
NO SQL
BEGIN
    DECLARE v_minutos INT;
    DECLARE v_horas INT;
    DECLARE v_dias INT;

    SET v_minutos = TIMESTAMPDIFF(MINUTE, p_fecha, NOW());
    SET v_horas   = TIMESTAMPDIFF(HOUR, p_fecha, NOW());
    SET v_dias    = TIMESTAMPDIFF(DAY, p_fecha, NOW());

    IF v_minutos < 60 THEN
        RETURN CONCAT('Hace ', v_minutos, ' minutos');
    ELSEIF v_horas < 24 THEN
        RETURN CONCAT('Hace ', v_horas, ' horas');
    ELSEIF v_dias < 30 THEN
        RETURN CONCAT('Hace ', v_dias, ' días');
    ELSE
        RETURN DATE_FORMAT(p_fecha, '%d/%m/%Y');
    END IF;
END //

CREATE FUNCTION fn_total_interacciones(p_id_publicacion INT)
RETURNS INT
READS SQL DATA
BEGIN
    DECLARE v_total_likes INT DEFAULT 0;
    DECLARE v_total_comentarios INT DEFAULT 0;
    DECLARE v_total_compartidos INT DEFAULT 0;

    SELECT COUNT(id_like) INTO v_total_likes
    FROM likes
    WHERE id_publicacion = p_id_publicacion;

    SELECT COUNT(id_comentario) INTO v_total_comentarios
    FROM comentarios
    WHERE id_publicacion = p_id_publicacion;

    SELECT COUNT(id_compartido) INTO v_total_compartidos
    FROM compartidos
    WHERE id_publicacion = p_id_publicacion;

    RETURN v_total_likes + v_total_comentarios + v_total_compartidos;
END //

DELIMITER ;

-- =================================================================================
-- 4. VISTAS (10)
-- =================================================================================

CREATE OR REPLACE VIEW vw_feed_publicaciones AS
SELECT
    p.id_publicacion,
    p.id_usuario,
    u.nombre AS autor,
    u.foto_perfil,
    p.titulo,
    p.fecha_publicacion,
    fn_tiempo_transcurrido(p.fecha_publicacion) AS tiempo_publicacion,
    p.contenido,
    (
        SELECT m.ruta_archivo
        FROM multimedia m
        WHERE m.id_publicacion = p.id_publicacion
          AND m.tipo = 'audio'
        LIMIT 1
    ) AS ruta_audio,
    (
        SELECT m.id_multimedia
        FROM multimedia m
        WHERE m.id_publicacion = p.id_publicacion
          AND m.tipo = 'imagen'
        LIMIT 1
    ) AS id_portada,
    (
        SELECT COUNT(l.id_like)
        FROM likes l
        WHERE l.id_publicacion = p.id_publicacion
    ) AS total_likes,
    (
        SELECT COUNT(c.id_comentario)
        FROM comentarios c
        WHERE c.id_publicacion = p.id_publicacion
    ) AS total_comentarios,
    (
        SELECT COUNT(s.id_compartido)
        FROM compartidos s
        WHERE s.id_publicacion = p.id_publicacion
    ) AS total_compartidos,
    fn_total_interacciones(p.id_publicacion) AS interacciones_totales
FROM publicaciones p
INNER JOIN usuarios u ON p.id_usuario = u.id_usuario
WHERE p.estado = 'activa';

CREATE OR REPLACE VIEW vw_perfil_musical AS
SELECT
    u.id_usuario,
    u.nombre AS musico,
    i.nombre AS instrumento,
    ui.nivel_dominio,
    g.nombre AS genero,
    ug.relacion AS relacion_genero
FROM usuarios u
LEFT JOIN usuarios_instrumentos ui ON u.id_usuario = ui.id_usuario
LEFT JOIN cat_instrumentos i ON ui.id_instrumento = i.id_instrumento
LEFT JOIN usuarios_generos ug ON u.id_usuario = ug.id_usuario
LEFT JOIN cat_generos g ON ug.id_genero = g.id_genero;

CREATE OR REPLACE VIEW vw_biblioteca_partituras AS
SELECT
    p.id_publicacion,
    u.nombre AS compositor,
    p.titulo,
    p.contenido AS descripcion_partitura,
    m.ruta_archivo AS enlace_descarga,
    p.fecha_publicacion
FROM publicaciones p
INNER JOIN usuarios u ON p.id_usuario = u.id_usuario
INNER JOIN multimedia m ON p.id_publicacion = m.id_publicacion
WHERE p.estado = 'activa' AND m.tipo = 'partitura';

CREATE OR REPLACE VIEW vw_anotaciones_genius AS
SELECT
    a.id_anotacion,
    p.id_publicacion,
    u_autor.nombre AS autor_pista,
    u_anotador.nombre AS contribuidor,
    a.referencia_tiempo,
    a.referencia_letra,
    a.explicacion,
    a.fecha_anotacion
FROM anotaciones_colaborativas a
INNER JOIN publicaciones p ON a.id_publicacion = p.id_publicacion
INNER JOIN usuarios u_autor ON p.id_usuario = u_autor.id_usuario
INNER JOIN usuarios u_anotador ON a.id_usuario = u_anotador.id_usuario;

CREATE OR REPLACE VIEW vw_ranking_usuarios AS
SELECT
    u.id_usuario,
    u.nombre,
    (SELECT COUNT(s.id_seguimiento) FROM seguidores s WHERE s.id_seguido = u.id_usuario) AS total_seguidores,
    (SELECT COUNT(p.id_publicacion) FROM publicaciones p WHERE p.id_usuario = u.id_usuario AND p.estado = 'activa') AS pistas_activas
FROM usuarios u
WHERE u.estado = 'activo';

CREATE OR REPLACE VIEW vw_historial_auditoria AS
SELECT
    a.id_auditoria,
    u.nombre AS usuario_afectado,
    a.accion_realizada,
    a.fecha_accion
FROM auditoria_usuarios a
INNER JOIN usuarios u ON a.id_usuario = u.id_usuario;

CREATE OR REPLACE VIEW vw_red_seguidores AS
SELECT
    s.id_seguimiento,
    seguidor.nombre AS seguidor,
    seguido.nombre AS seguido,
    s.fecha_seguimiento
FROM seguidores s
INNER JOIN usuarios seguidor ON s.id_seguidor = seguidor.id_usuario
INNER JOIN usuarios seguido ON s.id_seguido = seguido.id_usuario;

CREATE OR REPLACE VIEW vw_actividad_reciente AS
SELECT
    'Like' AS tipo_actividad,
    u.nombre AS actor,
    l.id_publicacion AS id_referencia,
    l.fecha_like AS fecha
FROM likes l
INNER JOIN usuarios u ON l.id_usuario = u.id_usuario
UNION ALL
SELECT
    'Comentario' AS tipo_actividad,
    u.nombre AS actor,
    c.id_publicacion AS id_referencia,
    c.fecha_comentario AS fecha
FROM comentarios c
INNER JOIN usuarios u ON c.id_usuario = u.id_usuario
UNION ALL
SELECT
    'Nuevo Seguidor' AS tipo_actividad,
    u.nombre AS actor,
    s.id_seguido AS id_referencia,
    s.fecha_seguimiento AS fecha
FROM seguidores s
INNER JOIN usuarios u ON s.id_seguidor = u.id_usuario;

CREATE OR REPLACE VIEW vw_comentarios_publicacion AS
SELECT
    c.id_comentario,
    c.id_publicacion,
    c.id_usuario,
    u.nombre AS nombre_usuario,
    c.contenido,
    c.fecha_comentario
FROM comentarios c
INNER JOIN usuarios u ON c.id_usuario = u.id_usuario;

CREATE OR REPLACE VIEW vw_perfil_usuario AS
SELECT
    u.id_usuario,
    u.nombre,
    u.correo,
    u.foto_perfil,
    (
        SELECT COUNT(p.id_publicacion)
        FROM publicaciones p
        WHERE p.id_usuario = u.id_usuario
          AND p.estado = 'activa'
    ) AS total_pistas,
    (
        SELECT COUNT(s.id_seguimiento)
        FROM seguidores s
        WHERE s.id_seguido = u.id_usuario
    ) AS total_seguidores,
    (
        SELECT COUNT(s.id_seguimiento)
        FROM seguidores s
        WHERE s.id_seguidor = u.id_usuario
    ) AS total_siguiendo
FROM usuarios u
WHERE u.estado = 'activo';

-- =================================================================================
-- 5. STORED PROCEDURES (11 - columnas explícitas)
-- =================================================================================
DELIMITER //

CREATE PROCEDURE sp_gestion_usuarios(
    IN p_accion VARCHAR(20),
    IN p_id_usuario INT,
    IN p_nombre VARCHAR(100),
    IN p_correo VARCHAR(150),
    IN p_contrasena VARCHAR(255),
    IN p_foto_perfil MEDIUMBLOB
)
BEGIN
    IF p_accion = 'registrar' THEN
        IF EXISTS (SELECT 1 FROM usuarios WHERE correo = p_correo) THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El correo ya esta registrado';
        ELSE
            INSERT INTO usuarios (nombre, correo, contrasena, foto_perfil)
            VALUES (p_nombre, p_correo, p_contrasena, p_foto_perfil);
            SELECT 'Usuario registrado correctamente' AS mensaje, LAST_INSERT_ID() AS id_usuario;
        END IF;

    ELSEIF p_accion = 'iniciar_sesion' THEN
        SELECT id_usuario, nombre, correo, contrasena AS hash_contrasena, foto_perfil, estado
        FROM usuarios
        WHERE correo = p_correo AND estado = 'activo';

    ELSEIF p_accion = 'modificar' THEN
        IF NOT EXISTS (SELECT 1 FROM usuarios WHERE id_usuario = p_id_usuario) THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El usuario no existe';
        ELSEIF EXISTS (SELECT 1 FROM usuarios WHERE correo = p_correo AND id_usuario <> p_id_usuario) THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El correo ya pertenece a otro usuario';
        ELSE
            UPDATE usuarios
            SET nombre = p_nombre,
                correo = p_correo,
                contrasena = COALESCE(p_contrasena, contrasena),
                foto_perfil = COALESCE(p_foto_perfil, foto_perfil)
            WHERE id_usuario = p_id_usuario;
            SELECT 'Usuario modificado correctamente' AS mensaje;
        END IF;

    ELSEIF p_accion = 'desactivar' THEN
        UPDATE usuarios
        SET estado = 'inactivo'
        WHERE id_usuario = p_id_usuario;
        SELECT 'Cuenta desactivada correctamente' AS mensaje;

    ELSE
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Accion no valida';
    END IF;
END //

CREATE PROCEDURE sp_gestion_publicaciones(
    IN p_accion VARCHAR(20),
    IN p_id_publicacion INT,
    IN p_id_usuario INT,
    IN p_titulo VARCHAR(150),
    IN p_contenido VARCHAR(500),
    IN p_archivo MEDIUMBLOB,
    IN p_ruta_audio VARCHAR(255)
)
BEGIN
    DECLARE v_id_nueva_pub INT;

    IF p_accion = 'crear' THEN
        INSERT INTO publicaciones (id_usuario, titulo, contenido)
        VALUES (p_id_usuario, p_titulo, p_contenido);

        SET v_id_nueva_pub = LAST_INSERT_ID();

        IF p_archivo IS NOT NULL THEN
            INSERT INTO multimedia (id_publicacion, tipo, archivo, ruta_archivo)
            VALUES (v_id_nueva_pub, 'imagen', p_archivo, NULL);
        END IF;

        IF p_ruta_audio IS NOT NULL THEN
            INSERT INTO multimedia (id_publicacion, tipo, archivo, ruta_archivo)
            VALUES (v_id_nueva_pub, 'audio', NULL, p_ruta_audio);
        END IF;

        SELECT 'Publicación creada exitosamente.' AS mensaje, v_id_nueva_pub AS id_publicacion;

    ELSEIF p_accion = 'eliminar' THEN
        UPDATE publicaciones
        SET estado = 'eliminada'
        WHERE id_publicacion = p_id_publicacion
          AND id_usuario = p_id_usuario;

        SELECT 'Publicación marcada como eliminada.' AS mensaje;

    ELSEIF p_accion = 'listar' THEN
        SELECT
            f.id_publicacion,
            f.id_usuario,
            f.autor,
            f.foto_perfil,
            f.titulo,
            f.fecha_publicacion,
            f.tiempo_publicacion,
            f.contenido,
            f.ruta_audio,
            f.id_portada,
            f.total_likes,
            f.total_comentarios,
            f.total_compartidos,
            f.interacciones_totales,
            EXISTS (
                SELECT 1
                FROM likes l
                WHERE l.id_publicacion = f.id_publicacion
                  AND l.id_usuario = p_id_usuario
            ) AS usuario_dio_like
        FROM vw_feed_publicaciones f
        ORDER BY f.fecha_publicacion DESC;

    ELSEIF p_accion = 'obtener' THEN
        SELECT
            id_publicacion,
            id_usuario,
            autor,
            foto_perfil,
            titulo,
            fecha_publicacion,
            tiempo_publicacion,
            contenido,
            ruta_audio,
            id_portada,
            total_likes,
            total_comentarios,
            total_compartidos,
            interacciones_totales
        FROM vw_feed_publicaciones
        WHERE id_publicacion = p_id_publicacion
        LIMIT 1;

    ELSE
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Acción no válida en publicaciones.';
    END IF;
END //

CREATE PROCEDURE sp_obtener_imagen_publicacion(
    IN p_id_publicacion INT
)
BEGIN
    SELECT archivo
    FROM multimedia
    WHERE id_publicacion = p_id_publicacion
      AND tipo = 'imagen'
    LIMIT 1;
END //

CREATE PROCEDURE sp_gestion_comentarios(
    IN p_accion VARCHAR(20),
    IN p_id_publicacion INT,
    IN p_id_usuario INT,
    IN p_contenido VARCHAR(300)
)
BEGIN
    IF p_accion = 'listar' THEN
        SELECT
            id_comentario,
            id_publicacion,
            id_usuario,
            nombre_usuario,
            contenido,
            fecha_comentario
        FROM vw_comentarios_publicacion
        WHERE id_publicacion = p_id_publicacion
        ORDER BY fecha_comentario ASC;

    ELSEIF p_accion = 'agregar' THEN
        IF p_contenido IS NULL OR TRIM(p_contenido) = '' THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El comentario no puede estar vacío.';
        ELSEIF CHAR_LENGTH(p_contenido) > 300 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El comentario supera los 300 caracteres.';
        ELSEIF NOT EXISTS (SELECT 1 FROM publicaciones WHERE id_publicacion = p_id_publicacion AND estado = 'activa') THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La publicación no existe o no está disponible.';
        ELSEIF NOT EXISTS (SELECT 1 FROM usuarios WHERE id_usuario = p_id_usuario AND estado = 'activo') THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El usuario no existe o está inactivo.';
        ELSE
            INSERT INTO comentarios (id_publicacion, id_usuario, contenido)
            VALUES (p_id_publicacion, p_id_usuario, TRIM(p_contenido));

            SELECT 'Comentario agregado correctamente.' AS mensaje, LAST_INSERT_ID() AS id_comentario;
        END IF;

    ELSE
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Acción no válida en comentarios.';
    END IF;
END //

CREATE PROCEDURE sp_obtener_perfil(
    IN p_id_usuario INT
)
BEGIN
    SELECT
        id_usuario,
        nombre,
        correo,
        foto_perfil,
        total_pistas,
        total_seguidores,
        total_siguiendo
    FROM vw_perfil_usuario
    WHERE id_usuario = p_id_usuario;
END //

CREATE PROCEDURE sp_listar_publicaciones_usuario(
    IN p_id_usuario INT
)
BEGIN
    SELECT
        id_publicacion,
        id_usuario,
        autor,
        foto_perfil,
        titulo,
        fecha_publicacion,
        tiempo_publicacion,
        contenido,
        ruta_audio,
        id_portada,
        total_likes,
        total_comentarios,
        total_compartidos,
        interacciones_totales
    FROM vw_feed_publicaciones
    WHERE id_usuario = p_id_usuario
    ORDER BY fecha_publicacion DESC;
END //

CREATE PROCEDURE sp_gestion_seguidores(
    IN p_accion VARCHAR(30),
    IN p_id_seguidor INT,
    IN p_id_seguido INT
)
BEGIN
    IF p_accion = 'seguir' THEN
        INSERT IGNORE INTO seguidores (id_seguidor, id_seguido)
        VALUES (p_id_seguidor, p_id_seguido);
        SELECT 'Seguimiento registrado.' AS mensaje;

    ELSEIF p_accion = 'dejar_seguir' THEN
        DELETE FROM seguidores
        WHERE id_seguidor = p_id_seguidor
          AND id_seguido = p_id_seguido;
        SELECT 'Seguimiento eliminado.' AS mensaje;

    ELSEIF p_accion = 'estado' THEN
        SELECT EXISTS (
            SELECT 1
            FROM seguidores
            WHERE id_seguidor = p_id_seguidor
              AND id_seguido = p_id_seguido
        ) AS siguiendo;

    ELSEIF p_accion = 'conteos' THEN
        SELECT
            (SELECT COUNT(id_seguimiento) FROM seguidores WHERE id_seguido = p_id_seguido) AS total_seguidores,
            (SELECT COUNT(id_seguimiento) FROM seguidores WHERE id_seguidor = p_id_seguido) AS total_siguiendo;

    ELSE
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Acción de seguimiento no válida.';
    END IF;
END //

CREATE PROCEDURE sp_obtener_actividad_reciente(
    IN p_id_usuario INT
)
BEGIN
    SELECT
        a.tipo_actividad,
        a.actor,
        a.id_referencia,
        a.fecha
    FROM vw_actividad_reciente a
    LEFT JOIN publicaciones p
        ON a.id_referencia = p.id_publicacion
        AND a.tipo_actividad IN ('Like', 'Comentario')
    WHERE
        (
            a.tipo_actividad IN ('Like', 'Comentario')
            AND p.id_usuario = p_id_usuario
        )
        OR
        (
            a.tipo_actividad = 'Nuevo Seguidor'
            AND a.id_referencia = p_id_usuario
        )
    ORDER BY a.fecha DESC;
END //

CREATE PROCEDURE sp_configurar_perfil_musical(
    IN p_id_usuario INT,
    IN p_id_instrumento INT,
    IN p_nivel ENUM('principiante','intermedio','avanzado','experto'),
    IN p_id_genero INT,
    IN p_relacion ENUM('escucha','toca','ambos')
)
BEGIN
    IF p_id_instrumento IS NOT NULL THEN
        INSERT INTO usuarios_instrumentos (id_usuario, id_instrumento, nivel_dominio)
        VALUES (p_id_usuario, p_id_instrumento, COALESCE(p_nivel, 'intermedio'))
        ON DUPLICATE KEY UPDATE nivel_dominio = VALUES(nivel_dominio);
    END IF;

    IF p_id_genero IS NOT NULL THEN
        INSERT INTO usuarios_generos (id_usuario, id_genero, relacion)
        VALUES (p_id_usuario, p_id_genero, COALESCE(p_relacion, 'escucha'))
        ON DUPLICATE KEY UPDATE relacion = VALUES(relacion);
    END IF;

    SELECT 'Perfil musical actualizado.' AS mensaje;
END //

CREATE PROCEDURE sp_agregar_anotacion(
    IN p_id_publicacion INT,
    IN p_id_usuario INT,
    IN p_ref_tiempo VARCHAR(20),
    IN p_ref_letra VARCHAR(150),
    IN p_explicacion TEXT
)
BEGIN
    INSERT INTO anotaciones_colaborativas (id_publicacion, id_usuario, referencia_tiempo, referencia_letra, explicacion)
    VALUES (p_id_publicacion, p_id_usuario, p_ref_tiempo, p_ref_letra, p_explicacion);

    SELECT 'Anotación agregada exitosamente.' AS mensaje, LAST_INSERT_ID() AS id_anotacion;
END //

CREATE PROCEDURE sp_reporte_estadisticas(
    IN p_fecha_inicio DATE,
    IN p_fecha_fin DATE
)
BEGIN
    DECLARE v_fecha_fin_ajustada DATETIME;
    SET v_fecha_fin_ajustada = CONCAT(p_fecha_fin, ' 23:59:59');

    SELECT
        (SELECT COUNT(id_publicacion) FROM publicaciones WHERE fecha_publicacion BETWEEN p_fecha_inicio AND v_fecha_fin_ajustada AND estado = 'activa') AS total_publicaciones,
        (SELECT COUNT(id_like) FROM likes WHERE fecha_like BETWEEN p_fecha_inicio AND v_fecha_fin_ajustada) AS total_likes,
        (SELECT COUNT(id_seguimiento) FROM seguidores WHERE fecha_seguimiento BETWEEN p_fecha_inicio AND v_fecha_fin_ajustada) AS nuevos_seguidores;
END //

DELIMITER ;

-- =================================================================================
-- 6. POBLACIÓN INICIAL DE DATOS (INSERT directos para evitar #2014 en phpMyAdmin)
-- =================================================================================

INSERT INTO usuarios (nombre, correo, contrasena, foto_perfil) VALUES
    ('Seckoe', 'seckoe@synth.com', 'Hash123!', NULL),
    ('Tedss', 'tedss@synth.com', 'Hash123!', NULL),
    ('Admin', 'admin@synth.com', 'Hash123!', NULL);

INSERT INTO cat_instrumentos (nombre) VALUES
    ('Sintetizador'), ('Piano'), ('Guitarra Eléctrica'), ('Bajo'), ('Batería');

INSERT INTO cat_generos (nombre) VALUES
    ('Synthwave'), ('Ambient'), ('Rock'), ('Electrónica'), ('Jazz');

INSERT INTO usuarios_instrumentos (id_usuario, id_instrumento, nivel_dominio) VALUES
    (1, 1, 'avanzado'),
    (2, 3, 'intermedio');

INSERT INTO usuarios_generos (id_usuario, id_genero, relacion) VALUES
    (1, 2, 'ambos'),
    (2, 3, 'escucha');

INSERT INTO publicaciones (id_usuario, titulo, contenido) VALUES
    (1, 'Rooftop Forests & Midnight Synths', 'Explorando texturas de la ciudad en la madrugada.'),
    (2, 'Boss Theme - Partitura', 'Partitura original para el tema de jefe final.');

INSERT INTO multimedia (id_publicacion, tipo, archivo, ruta_archivo) VALUES
    (1, 'audio', NULL, 'uploads/audio/midnight_synths.mp3'),
    (2, 'partitura', NULL, 'uploads/partituras/boss_theme.pdf');

INSERT INTO likes (id_publicacion, id_usuario) VALUES (1, 2);
INSERT INTO seguidores (id_seguidor, id_seguido) VALUES (2, 1);

INSERT INTO anotaciones_colaborativas (id_publicacion, id_usuario, referencia_tiempo, referencia_letra, explicacion) VALUES
    (1, 2, '01:20 - 01:45', NULL, 'Gran transición, el uso del arpegiador aquí levanta toda la pista.');