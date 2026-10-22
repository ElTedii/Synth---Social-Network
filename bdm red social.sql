CREATE DATABASE IF NOT EXISTS red_social_bdm;
USE red_social_bdm;

-- =================================================================================
-- 1. TABLAS PRINCIPALES
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

-- Nueva tabla de auditoría para Triggers
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

CREATE TABLE multimedia (
    id_multimedia    INT AUTO_INCREMENT PRIMARY KEY, 
    id_publicacion   INT NOT NULL, 
    tipo             ENUM('imagen','video') NOT NULL, 
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

-- =================================================================================
-- 2. TRIGGERS (NUEVO)
-- =================================================================================
DELIMITER //

-- Trigger 1: Auditoría cuando se modifica un usuario (ej. cambio de estado o correo)
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

-- Trigger 2: Validar que no se pueda dar "like" a una publicación eliminada
CREATE TRIGGER trg_validar_like
BEFORE INSERT ON likes
FOR EACH ROW
BEGIN
    DECLARE estado_pub ENUM('activa','eliminada');
    
    -- Consultamos el estado de la publicación a la que se le quiere dar like
    SELECT estado INTO estado_pub FROM publicaciones WHERE id_publicacion = NEW.id_publicacion;
    
    IF estado_pub = 'eliminada' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No puedes dar like a una publicación eliminada.';
    END IF;
END //

-- Trigger 3: Validar a nivel lógico (adicional al CHECK) que no te puedas seguir a ti mismo
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
-- 3. STORED PROCEDURES (PROCEDIMIENTOS ALMACENADOS)
-- =================================================================================
DELIMITER //

-- (Mantuve intacto tu procedimiento original)
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
        ELSE
            UPDATE usuarios SET nombre = p_nombre, correo = p_correo, contrasena = p_contrasena, foto_perfil = p_foto_perfil
            WHERE id_usuario = p_id_usuario;
            SELECT 'Usuario modificado correctamente' AS mensaje;
        END IF;
    ELSE
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Accion no valida';
    END IF;
END //


-- NUEVO PROCEDURE: Gestión de Publicaciones (Crear y Eliminar lógicamente)
CREATE PROCEDURE sp_gestion_publicaciones(
    IN p_accion VARCHAR(20),
    IN p_id_publicacion INT,
    IN p_id_usuario INT,
    IN p_contenido VARCHAR(500),
    IN p_tipo_multimedia ENUM('imagen','video'),
    IN p_archivo MEDIUMBLOB,
    IN p_ruta_archivo VARCHAR(255)
)
BEGIN
    DECLARE v_id_nueva_pub INT;

    -- CREAR UNA NUEVA PUBLICACIÓN
    IF p_accion = 'crear' THEN
        -- Insertamos el texto principal
        INSERT INTO publicaciones (id_usuario, contenido) 
        VALUES (p_id_usuario, p_contenido);
        
        -- Obtenemos el ID generado
        SET v_id_nueva_pub = LAST_INSERT_ID();
        
        -- Si trae algún archivo (BLOB) o ruta adjunta, insertamos en multimedia
        IF (p_archivo IS NOT NULL OR p_ruta_archivo IS NOT NULL) AND p_tipo_multimedia IS NOT NULL THEN
            INSERT INTO multimedia (id_publicacion, tipo, archivo, ruta_archivo)
            VALUES (v_id_nueva_pub, p_tipo_multimedia, p_archivo, p_ruta_archivo);
        END IF;
        
        SELECT 'Publicación creada exitosamente.' AS mensaje, v_id_nueva_pub AS id_publicacion;

    -- ELIMINAR (Soft Delete) PUBLICACIÓN
    ELSEIF p_accion = 'eliminar' THEN
        UPDATE publicaciones 
        SET estado = 'eliminada' 
        WHERE id_publicacion = p_id_publicacion AND id_usuario = p_id_usuario;
        
        SELECT 'Publicación marcada como eliminada.' AS mensaje;
        
    ELSE
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Acción no válida en publicaciones.';
    END IF;
END //


-- NUEVO PROCEDURE: Reportes y Estadísticas (Para la página reportes.html)
CREATE PROCEDURE sp_reporte_estadisticas(
    IN p_fecha_inicio DATE,
    IN p_fecha_fin DATE
)
BEGIN
    -- Este procedimiento te devolverá los datos clave para tu dashboard en un solo llamado.
    -- Ajustamos las fechas para abarcar todo el día final (23:59:59)
    DECLARE v_fecha_fin_ajustada DATETIME;
    SET v_fecha_fin_ajustada = CONCAT(p_fecha_fin, ' 23:59:59');

    SELECT 
        (SELECT COUNT(*) FROM publicaciones WHERE fecha_publicacion BETWEEN p_fecha_inicio AND v_fecha_fin_ajustada AND estado = 'activa') AS total_publicaciones,
        (SELECT COUNT(*) FROM likes WHERE fecha_like BETWEEN p_fecha_inicio AND v_fecha_fin_ajustada) AS total_likes,
        (SELECT COUNT(*) FROM seguidores WHERE fecha_seguimiento BETWEEN p_fecha_inicio AND v_fecha_fin_ajustada) AS nuevos_seguidores;
END //

DELIMITER ;

-- =================================================================================
-- 4. VISTAS (VIEWS)
-- =================================================================================
-- Vista requerida para simplificar el despliegue del feed de publicaciones
CREATE VIEW vw_feed_publicaciones AS
SELECT 
    p.id_publicacion,
    u.nombre AS autor,
    p.fecha_publicacion,
    p.contenido,
    m.tipo AS tipo_multimedia,
    m.ruta_archivo,
    (SELECT COUNT(*) FROM likes l WHERE l.id_publicacion = p.id_publicacion) AS total_likes
FROM publicaciones p
INNER JOIN usuarios u ON p.id_usuario = u.id_usuario
LEFT JOIN multimedia m ON p.id_publicacion = m.id_publicacion
WHERE p.estado = 'activa'
ORDER BY p.fecha_publicacion DESC;

-- =================================================================================
-- 5. POBLACIÓN DE DATOS (MOCK DATA)
-- =================================================================================
-- Insertar usuarios de prueba usando el SP
CALL sp_gestion_usuarios('registrar', NULL, 'Seckoe', 'seckoe@synth.com', 'Hash123!', NULL);
CALL sp_gestion_usuarios('registrar', NULL, 'Tedss', 'tedss@synth.com', 'Hash123!', NULL);
CALL sp_gestion_usuarios('registrar', NULL, 'Admin', 'admin@synth.com', 'Hash123!', NULL);

-- Crear publicaciones usando el SP
CALL sp_gestion_publicaciones('crear', NULL, 1, 'Explorando texturas de la ciudad en la madrugada.', 'video', NULL, 'ruta/video.mp4');
CALL sp_gestion_publicaciones('crear', NULL, 2, 'Ep. 4: Game Audio Architecture & C++', 'imagen', NULL, 'ruta/portada.png');

-- Generar interacciones para probar Triggers y Estadísticas
INSERT INTO likes (id_publicacion, id_usuario) VALUES (1, 2);
INSERT INTO likes (id_publicacion, id_usuario) VALUES (1, 3);
INSERT INTO seguidores (id_seguidor, id_seguido) VALUES (2, 1);