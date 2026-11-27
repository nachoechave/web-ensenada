CREATE TABLE noticias (
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    bajada VARCHAR(500) NOT NULL,
    contenido TEXT NOT NULL,
    imagen VARCHAR(1000) NOT NULL,
    categoria VARCHAR(255) NOT NULL,
    fecha_publicacion DATE NOT NULL,
    estado VARCHAR(255) NOT NULL,
    destacada BOOLEAN NOT NULL,
    CONSTRAINT ck_noticia_estado CHECK (estado IN ('BORRADOR', 'PUBLICADA', 'ARCHIVADA'))
);
CREATE INDEX idx_noticias_publicas ON noticias(estado, destacada, id);
ALTER TABLE noticias ADD COLUMN slug VARCHAR(280) NULL;
ALTER TABLE noticias MODIFY COLUMN slug VARCHAR(280) NOT NULL;
CREATE UNIQUE INDEX uk_noticias_slug ON noticias(slug);
CREATE TABLE noticia_imagenes (
    noticia_id BIGINT NOT NULL,
    orden INT NOT NULL,
    url VARCHAR(1000) NOT NULL,
    PRIMARY KEY (noticia_id, orden),
    CONSTRAINT fk_noticia_imagenes_noticia FOREIGN KEY (noticia_id) REFERENCES noticias(id) ON DELETE CASCADE
);
