CREATE TABLE documentos_proveedores (
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(180) NOT NULL,
    descripcion VARCHAR(500) NOT NULL,
    nombre_original VARCHAR(255) NOT NULL,
    nombre_almacenado VARCHAR(255) NOT NULL,
    tipo_mime VARCHAR(120) NOT NULL,
    tamanio BIGINT NOT NULL,
    orden INT NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT uk_documentos_proveedores_archivo UNIQUE (nombre_almacenado)
);

CREATE INDEX idx_documentos_proveedores_publicos
    ON documentos_proveedores(activo, orden, id);
