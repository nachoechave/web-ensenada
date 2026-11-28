CREATE TABLE noticias (
    id INTEGER NOT NULL PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    bajada VARCHAR(500) NOT NULL,
    contenido TEXT NOT NULL,
    imagen VARCHAR(1000) NOT NULL,
    categoria VARCHAR(255) NOT NULL,
    fecha_publicacion DATE NOT NULL,
    estado VARCHAR(255) NOT NULL,
    destacada BOOLEAN NOT NULL,
    slug VARCHAR(280) COLLATE NOCASE NOT NULL,
    CONSTRAINT ck_noticia_estado CHECK (estado IN ('BORRADOR', 'PUBLICADA', 'ARCHIVADA'))
);
CREATE INDEX idx_noticias_publicas ON noticias(estado, destacada, id);
CREATE UNIQUE INDEX uk_noticias_slug ON noticias(slug);
CREATE TABLE noticia_imagenes (
    noticia_id BIGINT NOT NULL,
    orden INT NOT NULL,
    url VARCHAR(1000) NOT NULL,
    PRIMARY KEY (noticia_id, orden),
    CONSTRAINT fk_noticia_imagenes_noticia FOREIGN KEY (noticia_id) REFERENCES noticias(id) ON DELETE CASCADE
);
