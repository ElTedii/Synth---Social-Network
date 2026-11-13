CREATE DATABASE IF NOT EXISTS red_social_bdm;
USE red_social_bdm;

-- =================================================================================
-- 1. TABLAS PRINCIPALES Y CATÁLOGOS
-- =================================================================================

CREATE TABLE usuarios (
    id_usuario       INT AUTO_INCREMENT PRIMARY KEY, 
    nombre           VARCHAR(100) NOT NULL, 
    correo           VARCHAR(150) NOT NULL, 
    contrasena       VARCHAR(255) NOT NULL,   
    foto_perfil      MEDIUMBLOB   NULL, 
    estado           ENUM('activo','inactivo') NOT NULL DEFAULT 'activo', 
    CONSTRAINT uq_usuarios_correo UNIQUE (correo)
);

-- Catálogo de Instrumentos (NUEVO)
CREATE TABLE cat_instrumentos (
    id_instrumento   INT AUTO_INCREMENT PRIMARY KEY,
    nombre           VARCHAR(50) NOT NULL UNIQUE
);

-- Catálogo de Géneros Musicales (NUEVO)
CREATE TABLE cat_generos (
    id_genero        INT AUTO_INCREMENT PRIMARY KEY,
    nombre           VARCHAR(50) NOT NULL UNIQUE
);

-- Tabla pivote: Usuarios - Instrumentos (NUEVO)
CREATE TABLE usuarios_instrumentos (
    id_usuario       INT NOT NULL,
    id_instrumento   INT NOT NULL,
    nivel_dominio    ENUM('principiante', 'intermedio', 'avanzado', 'experto') DEFAULT 'intermedio',
    PRIMARY KEY (id_usuario, id_instrumento),
    CONSTRAINT fk_ui_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    CONSTRAINT fk_ui_instrumento FOREIGN KEY (id_instrumento) REFERENCES cat_instrumentos(id_instrumento) ON DELETE CASCADE
);

-- Tabla pivote: Usuarios - Géneros (NUEVO)
CREATE TABLE usuarios_generos (
    id_usuario       INT NOT NULL,
    id_genero        INT NOT NULL,
    relacion         ENUM('escucha', 'toca', 'ambos') NOT NULL DEFAULT 'escucha',
    PRIMARY KEY (id_usuario, id_genero),
    CONSTRAINT fk_ug_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    CONSTRAINT fk_ug_genero FOREIGN KEY (id_genero) REFERENCES cat_generos(id_genero) ON DELETE CASCADE
);

CREATE TABLE auditoria_usuarios (
    id_auditoria     INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario       INT NOT NULL,
    accion_realizada VARCHAR(255) NOT NULL,
    fecha_accion     DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_auditoria_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
);

CREATE TABLE publicaciones (
    id_publicacion     INT AUTO_INCREMENT PRIMARY KEY, 
    id_usuario         INT NOT NULL, 
    contenido          VARCHAR(500) NULL, 
    fecha_publicacion  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, 
    estado             ENUM('activa','eliminada') NOT NULL DEFAULT 'activa', 
    CONSTRAINT fk_publicaciones_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
        ON DELETE CASCADE
);

ALTER TABLE publicaciones
ADD COLUMN titulo VARCHAR(150) NULL AFTER id_usuario;

-- Tabla multimedia actualizada con 'audio' y 'partitura'
CREATE TABLE multimedia (
    id_multimedia    INT AUTO_INCREMENT PRIMARY KEY, 
    id_publicacion   INT NOT NULL, 
    tipo             ENUM('imagen','video','audio','partitura') NOT NULL, 
    archivo          MEDIUMBLOB   NULL,   
    ruta_archivo     VARCHAR(255) NULL,   
    fecha_carga      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, 
    CONSTRAINT fk_multimedia_publicacion
        FOREIGN KEY (id_publicacion) REFERENCES publicaciones(id_publicacion)
        ON DELETE CASCADE
);

CREATE TABLE likes (
    id_like          INT AUTO_INCREMENT PRIMARY KEY, 
    id_publicacion   INT NOT NULL, 
    id_usuario       INT NOT NULL, 
    fecha_like       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, 
    CONSTRAINT fk_likes_publicacion
        FOREIGN KEY (id_publicacion) REFERENCES publicaciones(id_publicacion)
        ON DELETE CASCADE,
    CONSTRAINT fk_likes_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
        ON DELETE CASCADE,
    CONSTRAINT uq_likes_publicacion_usuario UNIQUE (id_publicacion, id_usuario) 
);

CREATE TABLE comentarios (
    id_comentario      INT AUTO_INCREMENT PRIMARY KEY, 
    id_publicacion     INT NOT NULL, 
    id_usuario         INT NOT NULL, 
    contenido          VARCHAR(300) NOT NULL, 
    fecha_comentario   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, 
    CONSTRAINT fk_comentarios_publicacion
        FOREIGN KEY (id_publicacion) REFERENCES publicaciones(id_publicacion)
        ON DELETE CASCADE,
    CONSTRAINT fk_comentarios_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
        ON DELETE CASCADE
);

CREATE TABLE compartidos (
    id_compartido        INT AUTO_INCREMENT PRIMARY KEY, 
    id_publicacion       INT NOT NULL,   
    id_usuario           INT NOT NULL,   
    comentario           VARCHAR(300) NULL, 
    fecha_compartido     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, 
    CONSTRAINT fk_compartidos_publicacion
        FOREIGN KEY (id_publicacion) REFERENCES publicaciones(id_publicacion)
        ON DELETE CASCADE,
    CONSTRAINT fk_compartidos_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
        ON DELETE CASCADE
);

CREATE TABLE seguidores (
    id_seguimiento      INT AUTO_INCREMENT PRIMARY KEY, 
    id_seguidor         INT NOT NULL,   
    id_seguido          INT NOT NULL,   
    fecha_seguimiento   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, 
    CONSTRAINT fk_seguidores_seguidor
        FOREIGN KEY (id_seguidor) REFERENCES usuarios(id_usuario)
        ON DELETE CASCADE,
    CONSTRAINT fk_seguidores_seguido
        FOREIGN KEY (id_seguido) REFERENCES usuarios(id_usuario)
        ON DELETE CASCADE,
    CONSTRAINT uq_seguidores_par UNIQUE (id_seguidor, id_seguido), 
    CONSTRAINT chk_seguidores_no_auto CHECK (id_seguidor <> id_seguido) 
);

-- Tabla de Anotaciones Colaborativas estilo Genius (NUEVO)
CREATE TABLE anotaciones_colaborativas (
    id_anotacion       INT AUTO_INCREMENT PRIMARY KEY,
    id_publicacion     INT NOT NULL,
    id_usuario         INT NOT NULL,
    referencia_tiempo  VARCHAR(20) NULL, -- Ej: "01:24 - 01:40"
    referencia_letra   VARCHAR(150) NULL, -- Fragmento de la letra o partitura
    explicacion        TEXT NOT NULL,
    fecha_anotacion    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_anotaciones_publicacion FOREIGN KEY (id_publicacion) REFERENCES publicaciones(id_publicacion) ON DELETE CASCADE,
    CONSTRAINT fk_anotaciones_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
);

ALTER TABLE auditoria_usuarios
    MODIFY COLUMN id_auditoria INT AUTO_INCREMENT
        COMMENT 'Identificador único del registro de auditoría',
    MODIFY COLUMN id_usuario INT NOT NULL
        COMMENT 'Usuario al que corresponde la acción registrada',
    MODIFY COLUMN accion_realizada VARCHAR(255) NOT NULL
        COMMENT 'Descripción de la acción realizada',
    MODIFY COLUMN fecha_accion DATETIME DEFAULT CURRENT_TIMESTAMP
        COMMENT 'Fecha y hora en que se realizó la acción';


ALTER TABLE usuarios
    MODIFY COLUMN id_usuario INT AUTO_INCREMENT
        COMMENT 'Identificador único del usuario',
    MODIFY COLUMN nombre VARCHAR(100) NOT NULL
        COMMENT 'Nombre del usuario',
    MODIFY COLUMN correo VARCHAR(150) NOT NULL
        COMMENT 'Correo electrónico del usuario',
    MODIFY COLUMN contrasena VARCHAR(255) NOT NULL
        COMMENT 'Hash de la contraseña del usuario',
    MODIFY COLUMN foto_perfil MEDIUMBLOB NULL
        COMMENT 'Imagen de perfil almacenada en formato binario',
    MODIFY COLUMN estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo'
        COMMENT 'Estado actual de la cuenta del usuario';


ALTER TABLE publicaciones
    MODIFY COLUMN id_publicacion INT AUTO_INCREMENT
        COMMENT 'Identificador único de la publicación',
    MODIFY COLUMN id_usuario INT NOT NULL
        COMMENT 'Usuario que creó la publicación',
    MODIFY COLUMN contenido VARCHAR(500) NULL
        COMMENT 'Texto de la publicación',
    MODIFY COLUMN fecha_publicacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        COMMENT 'Fecha y hora de creación de la publicación',
    MODIFY COLUMN estado ENUM('activa','eliminada') NOT NULL DEFAULT 'activa'
        COMMENT 'Estado actual de la publicación';


ALTER TABLE multimedia
    MODIFY COLUMN id_multimedia INT AUTO_INCREMENT
        COMMENT 'Identificador único del archivo multimedia',
    MODIFY COLUMN id_publicacion INT NOT NULL
        COMMENT 'Publicación a la que pertenece el archivo',
    MODIFY COLUMN tipo ENUM('imagen','video') NOT NULL
        COMMENT 'Tipo de archivo: imagen o video',
    MODIFY COLUMN archivo MEDIUMBLOB NULL
        COMMENT 'Contenido binario del archivo multimedia',
    MODIFY COLUMN ruta_archivo VARCHAR(255) NULL
        COMMENT 'Ruta o ubicación del archivo multimedia',
    MODIFY COLUMN fecha_carga DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        COMMENT 'Fecha y hora de carga del archivo';


ALTER TABLE likes
    MODIFY COLUMN id_like INT AUTO_INCREMENT
        COMMENT 'Identificador único del like',
    MODIFY COLUMN id_publicacion INT NOT NULL
        COMMENT 'Publicación que recibió el like',
    MODIFY COLUMN id_usuario INT NOT NULL
        COMMENT 'Usuario que dio el like',
    MODIFY COLUMN fecha_like DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        COMMENT 'Fecha y hora en que se dio el like';


ALTER TABLE comentarios
    MODIFY COLUMN id_comentario INT AUTO_INCREMENT
        COMMENT 'Identificador único del comentario',
    MODIFY COLUMN id_publicacion INT NOT NULL
        COMMENT 'Publicación donde se escribió el comentario',
    MODIFY COLUMN id_usuario INT NOT NULL
        COMMENT 'Usuario que escribió el comentario',
    MODIFY COLUMN contenido VARCHAR(300) NOT NULL
        COMMENT 'Texto del comentario',
    MODIFY COLUMN fecha_comentario DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        COMMENT 'Fecha y hora de publicación del comentario';


ALTER TABLE compartidos
    MODIFY COLUMN id_compartido INT AUTO_INCREMENT
        COMMENT 'Identificador único del registro compartido',
    MODIFY COLUMN id_publicacion INT NOT NULL
        COMMENT 'Publicación original que se comparte',
    MODIFY COLUMN id_usuario INT NOT NULL
        COMMENT 'Usuario que compartió la publicación',
    MODIFY COLUMN comentario VARCHAR(300) NULL
        COMMENT 'Comentario opcional al compartir la publicación',
    MODIFY COLUMN fecha_compartido DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        COMMENT 'Fecha y hora en que se compartió la publicación';


ALTER TABLE seguidores
    MODIFY COLUMN id_seguimiento INT AUTO_INCREMENT
        COMMENT 'Identificador único de la relación de seguimiento',
    MODIFY COLUMN id_seguidor INT NOT NULL
        COMMENT 'Usuario que sigue a otra cuenta',
    MODIFY COLUMN id_seguido INT NOT NULL
        COMMENT 'Usuario al que se sigue',
    MODIFY COLUMN fecha_seguimiento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        COMMENT 'Fecha y hora en que comenzó el seguimiento';

-- =================================================================================
-- 2. TRIGGERS
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
    DECLARE estado_pub ENUM('activa','eliminada');
    SELECT estado INTO estado_pub FROM publicaciones WHERE id_publicacion = NEW.id_publicacion;
    IF estado_pub = 'eliminada' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No puedes dar like a una publicación eliminada.';
    END IF;
END //

CREATE TRIGGER trg_evitar_auto_seguimiento
BEFORE INSERT ON seguidores
FOR EACH ROW
BEGIN
    IF NEW.id_seguidor = NEW.id_seguido THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Un usuario no puede seguirse a sí mismo.';
    END IF;
END //

DELIMITER ;

-- =================================================================================
-- 3. FUNCIONES ESCALARES (NUEVO - REQUISITO DE RÚBRICA: Mínimo 2)
-- =================================================================================
DELIMITER //

-- Función 1: Tiempo transcurrido amigable
CREATE FUNCTION fn_tiempo_transcurrido(p_fecha DATETIME) 
RETURNS VARCHAR(50)
DETERMINISTIC
BEGIN
    DECLARE v_minutos INT;
    DECLARE v_horas INT;
    DECLARE v_dias INT;
    
    SET v_minutos = TIMESTAMPDIFF(MINUTE, p_fecha, NOW());
    SET v_horas = TIMESTAMPDIFF(HOUR, p_fecha, NOW());
    SET v_dias = TIMESTAMPDIFF(DAY, p_fecha, NOW());
    
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

-- Función 2: Total de interacciones de una publicación
CREATE FUNCTION fn_total_interacciones(p_id_publicacion INT)
RETURNS INT
READS SQL DATA
BEGIN
    DECLARE v_total_likes INT;
    DECLARE v_total_comentarios INT;
    DECLARE v_total_compartidos INT;
    
    SELECT COUNT(*) INTO v_total_likes FROM likes WHERE id_publicacion = p_id_publicacion;
    SELECT COUNT(*) INTO v_total_comentarios FROM comentarios WHERE id_publicacion = p_id_publicacion;
    SELECT COUNT(*) INTO v_total_compartidos FROM compartidos WHERE id_publicacion = p_id_publicacion;
    
    RETURN v_total_likes + v_total_comentarios + v_total_compartidos;
END //

DELIMITER ;

-- =================================================================================
-- 4. STORED PROCEDURES
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
        FROM usuarios WHERE correo = p_correo AND estado = 'activo';

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

    ELSE
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Accion no valida';
    END IF;
END //


DELIMITER //

-- SP: Gestionar publicaciones
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

    -- CREAR PUBLICACIÓN
    IF p_accion = 'crear' THEN

        INSERT INTO publicaciones (
            id_usuario,
            titulo,
            contenido
        )
        VALUES (
            p_id_usuario,
            p_titulo,
            p_contenido
        );

        SET v_id_nueva_pub = LAST_INSERT_ID();

        -- Guardar imagen
        IF p_archivo IS NOT NULL THEN
            INSERT INTO multimedia (
                id_publicacion,
                tipo,
                archivo,
                ruta_archivo
            )
            VALUES (
                v_id_nueva_pub,
                'imagen',
                p_archivo,
                NULL
            );
        END IF;

        -- Guardar ruta del audio
        IF p_ruta_audio IS NOT NULL THEN
            INSERT INTO multimedia (
                id_publicacion,
                tipo,
                archivo,
                ruta_archivo
            )
            VALUES (
                v_id_nueva_pub,
                'audio',
                NULL,
                p_ruta_audio
            );
        END IF;

        SELECT
            'Publicación creada exitosamente.' AS mensaje,
            v_id_nueva_pub AS id_publicacion;

    -- ELIMINAR PUBLICACIÓN
    ELSEIF p_accion = 'eliminar' THEN

        UPDATE publicaciones
        SET estado = 'eliminada'
        WHERE id_publicacion = p_id_publicacion
          AND id_usuario = p_id_usuario;

        SELECT
            'Publicación marcada como eliminada.' AS mensaje;

    -- LISTAR PUBLICACIONES DEL FEED
    ELSEIF p_accion = 'listar' THEN

        SELECT
            f.*,
            EXISTS (
                SELECT 1
                FROM likes l
                WHERE l.id_publicacion = f.id_publicacion
                  AND l.id_usuario = p_id_usuario
            ) AS usuario_dio_like
        FROM vw_feed_publicaciones f
        ORDER BY f.fecha_publicacion DESC;

    -- ACCIÓN NO VÁLIDA
    ELSE

        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Acción no válida en publicaciones.';

    END IF;

END //


-- SP: Agregar anotación colaborativa (Genius)
CREATE PROCEDURE sp_agregar_anotacion(
    IN p_id_publicacion INT,
    IN p_id_usuario INT,
    IN p_ref_tiempo VARCHAR(20),
    IN p_ref_letra VARCHAR(150),
    IN p_explicacion TEXT
)
BEGIN

    INSERT INTO anotaciones_colaborativas (
        id_publicacion,
        id_usuario,
        referencia_tiempo,
        referencia_letra,
        explicacion
    )
    VALUES (
        p_id_publicacion,
        p_id_usuario,
        p_ref_tiempo,
        p_ref_letra,
        p_explicacion
    );

    SELECT 'Anotación agregada exitosamente.' AS mensaje;

END //


-- SP: Configurar perfil musical
CREATE PROCEDURE sp_configurar_perfil_musical(
    IN p_id_usuario INT,
    IN p_id_instrumento INT,
    IN p_nivel ENUM(
        'principiante',
        'intermedio',
        'avanzado',
        'experto'
    ),
    IN p_id_genero INT,
    IN p_relacion ENUM(
        'escucha',
        'toca',
        'ambos'
    )
)
BEGIN

    IF p_id_instrumento IS NOT NULL THEN

        INSERT INTO usuarios_instrumentos (
            id_usuario,
            id_instrumento,
            nivel_dominio
        )
        VALUES (
            p_id_usuario,
            p_id_instrumento,
            COALESCE(p_nivel, 'intermedio')
        )
        ON DUPLICATE KEY UPDATE
            nivel_dominio = VALUES(nivel_dominio);

    END IF;

    IF p_id_genero IS NOT NULL THEN

        INSERT INTO usuarios_generos (
            id_usuario,
            id_genero,
            relacion
        )
        VALUES (
            p_id_usuario,
            p_id_genero,
            COALESCE(p_relacion, 'escucha')
        )
        ON DUPLICATE KEY UPDATE
            relacion = VALUES(relacion);

    END IF;

    SELECT 'Perfil musical actualizado.' AS mensaje;

END //

DELIMITER ;

DELIMITER //

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

DELIMITER ;


-- =================================================================================
-- 5. VISTAS (VIEWS) - REQUISITO DE RÚBRICA: Mínimo 8
-- =================================================================================

-- Vista 1: Feed principal (Original)
CREATE VIEW vw_feed_publicaciones AS
SELECT p.id_publicacion,p.id_usuario,u.nombre AS autor,p.titulo,p.fecha_publicacion,p.contenido,
 (SELECT m.ruta_archivo FROM multimedia m WHERE m.id_publicacion=p.id_publicacion AND m.tipo='audio' LIMIT 1) AS ruta_audio,
 (SELECT m.id_multimedia FROM multimedia m WHERE m.id_publicacion=p.id_publicacion AND m.tipo='imagen' LIMIT 1) AS id_portada,
 (SELECT COUNT(*) FROM likes l WHERE l.id_publicacion=p.id_publicacion) AS total_likes,
 (SELECT COUNT(*) FROM comentarios c WHERE c.id_publicacion=p.id_publicacion) AS total_comentarios,
 (SELECT COUNT(*) FROM compartidos s WHERE s.id_publicacion=p.id_publicacion) AS total_compartidos
FROM publicaciones p INNER JOIN usuarios u ON p.id_usuario=u.id_usuario WHERE p.estado='activa';
DELIMITER ;
-- Vista 2: Perfil Musical (NUEVO)
CREATE VIEW vw_perfil_musical AS
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

-- Vista 3: Biblioteca de Partituras (NUEVO)
CREATE VIEW vw_biblioteca_partituras AS
SELECT 
    p.id_publicacion,
    u.nombre AS compositor,
    p.contenido AS descripcion_partitura,
    m.ruta_archivo AS enlace_descarga,
    p.fecha_publicacion
FROM publicaciones p
INNER JOIN usuarios u ON p.id_usuario = u.id_usuario
INNER JOIN multimedia m ON p.id_publicacion = m.id_publicacion
WHERE p.estado = 'activa' AND m.tipo = 'partitura';

-- Vista 4: Anotaciones estilo Genius (NUEVO)
CREATE VIEW vw_anotaciones_genius AS
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

-- Vista 5: Ranking de Usuarios (NUEVO)
CREATE VIEW vw_ranking_usuarios AS
SELECT 
    u.id_usuario,
    u.nombre,
    (SELECT COUNT(*) FROM seguidores WHERE id_seguido = u.id_usuario) AS total_seguidores,
    (SELECT COUNT(*) FROM publicaciones WHERE id_usuario = u.id_usuario AND estado = 'activa') AS pistas_activas
FROM usuarios u
WHERE u.estado = 'activo';

-- Vista 6: Historial de Auditoría (NUEVO)
CREATE VIEW vw_historial_auditoria AS
SELECT 
    a.id_auditoria,
    u.nombre AS usuario_afectado,
    a.accion_realizada,
    a.fecha_accion
FROM auditoria_usuarios a
INNER JOIN usuarios u ON a.id_usuario = u.id_usuario;

-- Vista 7: Red de Seguidores (NUEVO)
CREATE VIEW vw_red_seguidores AS
SELECT 
    s.id_seguimiento,
    seguidor.nombre AS seguidor,
    seguido.nombre AS seguido,
    s.fecha_seguimiento
FROM seguidores s
INNER JOIN usuarios seguidor ON s.id_seguidor = seguidor.id_usuario
INNER JOIN usuarios seguido ON s.id_seguido = seguido.id_usuario;

-- Vista 8: Actividad Reciente / Notificaciones (NUEVO)
CREATE VIEW vw_actividad_reciente AS
SELECT 
    'Like' AS tipo_actividad,
    u.nombre AS actor,
    l.id_publicacion AS id_referencia,
    l.fecha_like AS fecha
FROM likes l
INNER JOIN usuarios u ON l.id_usuario = u.id_usuario
UNION ALL
SELECT 
    'Comentario',
    u.nombre,
    c.id_publicacion,
    c.fecha_comentario
FROM comentarios c
INNER JOIN usuarios u ON c.id_usuario = u.id_usuario
UNION ALL
SELECT 
    'Nuevo Seguidor',
    u.nombre,
    s.id_seguido,
    s.fecha_seguimiento
FROM seguidores s
INNER JOIN usuarios u ON s.id_seguidor = u.id_usuario;

-- =================================================================================
-- 6. POBLACIÓN DE DATOS (MOCK DATA)
-- =================================================================================
-- Insertar usuarios de prueba
CALL sp_gestion_usuarios('registrar', NULL, 'Seckoe', 'seckoe@synth.com', 'Hash123!', NULL);
CALL sp_gestion_usuarios('registrar', NULL, 'Tedss', 'tedss@synth.com', 'Hash123!', NULL);
CALL sp_gestion_usuarios('registrar', NULL, 'Admin', 'admin@synth.com', 'Hash123!', NULL);

-- Catálogos musicales
INSERT INTO cat_instrumentos (nombre) VALUES ('Sintetizador'), ('Piano'), ('Guitarra Eléctrica'), ('Bajo'), ('Batería');
INSERT INTO cat_generos (nombre) VALUES ('Synthwave'), ('Ambient'), ('Rock'), ('Electrónica'), ('Jazz');

-- Configurar perfiles musicales (Usuarios 1 y 2)
CALL sp_configurar_perfil_musical(1, 1, 'avanzado', 2, 'ambos'); -- Seckoe: Synth (Avanzado), Ambient (Toca y escucha)
CALL sp_configurar_perfil_musical(2, 3, 'intermedio', 3, 'escucha'); -- Tedss: Guitarra (Intermedio), Rock (Solo escucha)

-- Crear publicaciones
CALL sp_gestion_publicaciones('crear', NULL, 1, 'Explorando texturas de la ciudad en la madrugada.', 'audio', NULL, 'ruta/midnight_synths.mp3');
CALL sp_gestion_publicaciones('crear', NULL, 2, 'Partitura original para el tema de jefe final.', 'partitura', NULL, 'ruta/boss_theme.pdf');

-- Interacciones
INSERT INTO likes (id_publicacion, id_usuario) VALUES (1, 2);
INSERT INTO seguidores (id_seguidor, id_seguido) VALUES (2, 1);

-- Anotación colaborativa estilo Genius
CALL sp_agregar_anotacion(1, 2, '01:20 - 01:45', NULL, 'Gran transición, el uso del arpegiador aquí levanta toda la pista.');

select * from multimedia;

CALL sp_obtener_imagen_publicacion(2);

