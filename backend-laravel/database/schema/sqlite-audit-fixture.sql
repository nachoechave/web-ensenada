CREATE TABLE IF NOT EXISTS auditoria_noticias (
    id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    noticia_id BIGINT NOT NULL,
    noticia_titulo VARCHAR(255) NOT NULL,
    accion VARCHAR(40) NOT NULL,
    estado_resultante VARCHAR(30) NOT NULL,
    usuario_nombre VARCHAR(255) NOT NULL,
    usuario_email VARCHAR(255) NOT NULL,
    fecha TIMESTAMP(6) NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_auditoria_noticias_fecha ON auditoria_noticias(fecha);
CREATE INDEX IF NOT EXISTS idx_auditoria_noticias_noticia ON auditoria_noticias(noticia_id);
