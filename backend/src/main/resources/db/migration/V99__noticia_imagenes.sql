CREATE TABLE noticia_imagenes (
    noticia_id BIGINT NOT NULL,
    orden INT NOT NULL,
    url VARCHAR(1000) NOT NULL,
    PRIMARY KEY (noticia_id, orden),
    CONSTRAINT fk_noticia_imagenes_noticia FOREIGN KEY (noticia_id) REFERENCES noticias(id) ON DELETE CASCADE
);

INSERT INTO noticia_imagenes (noticia_id, orden, url)
SELECT id, 0, imagen FROM noticias WHERE imagen IS NOT NULL AND imagen <> '';
