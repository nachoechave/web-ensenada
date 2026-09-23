CREATE TABLE publicaciones_hacienda (
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT NOT NULL,
    tipo VARCHAR(60) NOT NULL,
    fecha_publicacion DATE NOT NULL,
    estado VARCHAR(20) NOT NULL,
    CONSTRAINT ck_hacienda_estado CHECK (estado IN ('BORRADOR','PUBLICADA','ARCHIVADA')),
    CONSTRAINT ck_hacienda_tipo CHECK (tipo IN ('SITUACION_ECONOMICO_FINANCIERA','PRESUPUESTO','ORDENANZA_FISCAL','ORDENANZA_PRESUPUESTARIA','CIERRE_EJERCICIO','OTRO'))
);
CREATE INDEX idx_hacienda_publicas ON publicaciones_hacienda(estado, fecha_publicacion, id);
CREATE TABLE archivos_hacienda (
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    publicacion_hacienda_id BIGINT NOT NULL,
    nombre_original VARCHAR(255) NOT NULL,
    nombre_almacenado VARCHAR(50) NOT NULL UNIQUE,
    tipo_mime VARCHAR(30) NOT NULL,
    orden INTEGER NOT NULL,
    tamanio BIGINT NOT NULL,
    CONSTRAINT fk_archivo_hacienda FOREIGN KEY (publicacion_hacienda_id) REFERENCES publicaciones_hacienda(id),
    CONSTRAINT ck_archivo_hacienda_orden CHECK (orden >= 0),
    CONSTRAINT ck_archivo_hacienda_tamanio CHECK (tamanio > 0),
    CONSTRAINT ck_archivo_hacienda_mime CHECK (tipo_mime IN ('application/pdf','image/jpeg','image/png'))
);
CREATE INDEX idx_hacienda_archivos_orden ON archivos_hacienda(publicacion_hacienda_id, orden);
