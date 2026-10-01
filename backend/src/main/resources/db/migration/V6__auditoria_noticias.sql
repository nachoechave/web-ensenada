CREATE TABLE auditoria_noticias (
    id BIGINT NOT NULL AUTO_INCREMENT,
    noticia_id BIGINT NOT NULL,
    noticia_titulo VARCHAR(255) NOT NULL,
    accion VARCHAR(40) NOT NULL,
    estado_resultante VARCHAR(30) NOT NULL,
    usuario_nombre VARCHAR(255) NOT NULL,
    usuario_email VARCHAR(255) NOT NULL,
    fecha TIMESTAMP(6) NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_auditoria_noticias_fecha (fecha),
    INDEX idx_auditoria_noticias_noticia (noticia_id)
);
