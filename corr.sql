DROP PROCEDURE IF EXISTS sp_gestion_likes;

DELIMITER //

CREATE PROCEDURE sp_gestion_likes(
    IN p_accion VARCHAR(20),
    IN p_id_publicacion INT,
    IN p_id_usuario INT
)
BEGIN
    DECLARE v_dio_like TINYINT(1) DEFAULT 0;
    DECLARE v_total INT DEFAULT 0;

    IF p_accion = 'toggle' THEN
        IF EXISTS (
            SELECT 1 FROM likes
            WHERE id_publicacion = p_id_publicacion AND id_usuario = p_id_usuario
        ) THEN
            DELETE FROM likes
            WHERE id_publicacion = p_id_publicacion AND id_usuario = p_id_usuario;
            SET v_dio_like = 0;
        ELSE
            INSERT INTO likes (id_publicacion, id_usuario)
            VALUES (p_id_publicacion, p_id_usuario);
            SET v_dio_like = 1;
        END IF;

        SELECT COUNT(*) INTO v_total
        FROM likes
        WHERE id_publicacion = p_id_publicacion;

        SELECT v_dio_like AS usuario_dio_like, v_total AS total_likes;

    ELSEIF p_accion = 'listar' THEN
        SELECT
            id_usuario_like,
            nombre_usuario,
            fecha_like
        FROM vw_likes_publicaciones
        WHERE id_publicacion = p_id_publicacion
        ORDER BY fecha_like DESC;
    ELSE
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Acción no válida en likes.';
    END IF;
END //

DELIMITER ;

CREATE OR REPLACE VIEW vw_likes_publicaciones AS
SELECT
    l.id_like,
    l.id_publicacion,
    p.id_usuario AS id_autor,
    p.titulo,
    p.contenido,
    l.id_usuario AS id_usuario_like,
    u.nombre AS nombre_usuario,
    l.fecha_like
FROM likes l
INNER JOIN publicaciones p
    ON l.id_publicacion = p.id_publicacion
INNER JOIN usuarios u
    ON l.id_usuario = u.id_usuario
WHERE p.estado = 'activa';