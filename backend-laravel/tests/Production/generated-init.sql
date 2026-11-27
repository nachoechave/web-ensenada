-- MariaDB 10.11 only. Run in batch mode without --force or reconnect.
-- Set @ensenada_convert_empty_latin1 = 1 explicitly to change an empty latin1 database.
-- The DDL body matches schema-init.sql and must remain aligned.
SET @schema_lock = CONCAT('web-ensenada-schema:', DATABASE());
SET @schema_guard = IF(
    BINARY DATABASE() IN ('ensenada_web', 'ensenada_auth_test', 'ensenada_auth_unit')
    AND SUBSTRING_INDEX(CURRENT_USER(), '@', 1) NOT IN ('ensenada_web_app', 'ensenada_web_news_reader', 'auth_runtime', 'news_reader')
    AND VERSION() REGEXP '^(5[.]5[.]5-)?10[.]11[.][0-9]+-MariaDB'
    AND GET_LOCK(@schema_lock, 0) = 1,
    'SELECT 1 AS mariadb_and_lock_ok',
    'SELECT * FROM information_schema.ERROR_VERSION_BASE_USUARIO_O_BLOQUEO'
);
PREPARE schema_guard FROM @schema_guard;
EXECUTE schema_guard;
DEALLOCATE PREPARE schema_guard;

SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME
INTO @schema_charset, @schema_collation
FROM information_schema.SCHEMATA WHERE BINARY SCHEMA_NAME = BINARY DATABASE();
SET @schema_guard = IF(
    ((@schema_charset = 'utf8mb4' AND @schema_collation = 'utf8mb4_unicode_520_ci')
        OR (@ensenada_convert_empty_latin1 = 1 AND @schema_charset = 'latin1'
            AND @schema_collation = 'latin1_swedish_ci'))
    AND NOT EXISTS (SELECT 1 FROM information_schema.TABLES WHERE BINARY TABLE_SCHEMA = BINARY DATABASE())
    AND NOT EXISTS (SELECT 1 FROM information_schema.TRIGGERS WHERE BINARY TRIGGER_SCHEMA = BINARY DATABASE())
    AND NOT EXISTS (SELECT 1 FROM information_schema.ROUTINES WHERE BINARY ROUTINE_SCHEMA = BINARY DATABASE())
    AND NOT EXISTS (SELECT 1 FROM information_schema.EVENTS WHERE BINARY EVENT_SCHEMA = BINARY DATABASE()),
    'SELECT 1 AS empty_schema_and_collation_ok',
    'SELECT * FROM information_schema.ERROR_ESQUEMA_NO_VACIO_O_COLLATION'
);
PREPARE schema_guard FROM @schema_guard;
EXECUTE schema_guard;
DEALLOCATE PREPARE schema_guard;

SET @schema_guard = IF(@schema_charset = 'latin1',
    CONCAT('ALTER DATABASE `', DATABASE(), '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci'),
    'SELECT 1 AS database_defaults_ok'
);
PREPARE schema_guard FROM @schema_guard;
EXECUTE schema_guard;
DEALLOCATE PREPARE schema_guard;
SET SESSION foreign_key_checks = 1;
SET SESSION unique_checks = 1;
SET SESSION check_constraint_checks = 1;
SET SESSION explicit_defaults_for_timestamp = 1;

-- Flyway V1 + V3. Final column order preserves the historical definitions.
CREATE TABLE usuarios (
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol VARCHAR(255) NOT NULL,
    activo BOOLEAN NOT NULL,
    token_version BIGINT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE usuario_roles (
    usuario_id BIGINT NOT NULL,
    rol VARCHAR(255) NOT NULL,
    PRIMARY KEY (usuario_id, rol),
    CONSTRAINT fk_usuario_roles FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- Flyway V1 + V5. A clean schema needs neither nullable slugs nor a backfill.
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
    slug VARCHAR(280) NOT NULL,
    CONSTRAINT ck_noticia_estado CHECK (estado IN ('BORRADOR', 'PUBLICADA', 'ARCHIVADA'))
) ENGINE=InnoDB;
CREATE INDEX idx_noticias_publicas ON noticias(estado, destacada, id);
CREATE UNIQUE INDEX uk_noticias_slug ON noticias(slug);

-- Flyway V2.
CREATE TABLE publicaciones_hacienda (
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT NOT NULL,
    tipo VARCHAR(60) NOT NULL,
    fecha_publicacion DATE NOT NULL,
    estado VARCHAR(20) NOT NULL,
    CONSTRAINT ck_hacienda_estado CHECK (estado IN ('BORRADOR','PUBLICADA','ARCHIVADA')),
    CONSTRAINT ck_hacienda_tipo CHECK (tipo IN ('SITUACION_ECONOMICO_FINANCIERA','PRESUPUESTO','ORDENANZA_FISCAL','ORDENANZA_PRESUPUESTARIA','CIERRE_EJERCICIO','OTRO'))
) ENGINE=InnoDB;
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
) ENGINE=InnoDB;
CREATE INDEX idx_hacienda_archivos_orden ON archivos_hacienda(publicacion_hacienda_id, orden);

-- Flyway V4 DDL only. Restore singleton id=1 through a separate approved data operation.
CREATE TABLE portal_site_content (
    id BIGINT NOT NULL PRIMARY KEY,
    contenido_json TEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Flyway V6, V7 and V99 DDL only.
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
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;
CREATE INDEX idx_documentos_proveedores_publicos ON documentos_proveedores(activo, orden, id);

CREATE TABLE noticia_imagenes (
    noticia_id BIGINT NOT NULL,
    orden INT NOT NULL,
    url VARCHAR(1000) NOT NULL,
    PRIMARY KEY (noticia_id, orden),
    CONSTRAINT fk_noticia_imagenes_noticia FOREIGN KEY (noticia_id) REFERENCES noticias(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Laravel runtime auxiliaries. Test baselines and migration ledgers are excluded.
CREATE TABLE auth_sessions (
    token_hash VARCHAR(64) NOT NULL PRIMARY KEY,
    usuario_id BIGINT NOT NULL,
    token_version BIGINT NOT NULL,
    created_at BIGINT NOT NULL,
    expires_at BIGINT NOT NULL,
    CONSTRAINT auth_sessions_usuario_id_foreign FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX auth_sessions_expires_at_index (expires_at)
) ENGINE=InnoDB;

CREATE TABLE auth_login_windows (
    ip_hash VARCHAR(64) NOT NULL PRIMARY KEY,
    attempts INT UNSIGNED NOT NULL,
    expires_at BIGINT NOT NULL,
    INDEX auth_login_windows_expires_at_index (expires_at)
) ENGINE=InnoDB;

CREATE TABLE auth_mutexes (
    name VARCHAR(40) NOT NULL PRIMARY KEY
) ENGINE=InnoDB;

DELIMITER $$
CREATE TRIGGER revoke_auth_sessions
BEFORE UPDATE ON usuarios FOR EACH ROW
BEGIN
    IF NOT (CAST(OLD.password AS BINARY) <=> CAST(NEW.password AS BINARY))
        OR NOT (CAST(OLD.email AS BINARY) <=> CAST(NEW.email AS BINARY))
        OR (OLD.activo = 1 AND NEW.activo = 0) THEN
        SET NEW.token_version = GREATEST(NEW.token_version, OLD.token_version + 1);
        DELETE FROM auth_sessions WHERE usuario_id = OLD.id;
    END IF;
END$$
DELIMITER ;

INSERT INTO auth_mutexes (name) VALUES ('login'), ('news-slug'), ('proveedores'), ('schema-ready');
SELECT RELEASE_LOCK(@schema_lock) AS schema_lock_released;
