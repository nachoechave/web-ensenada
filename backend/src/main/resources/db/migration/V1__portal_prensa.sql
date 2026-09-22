CREATE TABLE usuarios (
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol VARCHAR(255) NOT NULL,
    activo BOOLEAN NOT NULL
);
CREATE TABLE usuario_roles (
    usuario_id BIGINT NOT NULL,
    rol VARCHAR(255) NOT NULL,
    PRIMARY KEY (usuario_id, rol),
    CONSTRAINT fk_usuario_roles FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);
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
