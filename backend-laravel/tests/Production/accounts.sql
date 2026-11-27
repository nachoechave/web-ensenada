CREATE USER 'ensenada_web_app'@'127.0.0.1' IDENTIFIED BY 'synthetic-staging-runtime-only';
CREATE USER 'ensenada_web_schema'@'127.0.0.1' IDENTIFIED BY 'synthetic-schema-only';
CREATE USER 'ensenada_web_schema'@'localhost' IDENTIFIED BY 'synthetic-schema-only';
GRANT ALL ON ensenada_web.* TO 'ensenada_web_schema'@'127.0.0.1';
GRANT ALL ON ensenada_web.* TO 'ensenada_web_schema'@'localhost';
-- Review the app account host and the grantor's effective GRANT OPTION before applying.
-- The existing schema account and trigger DEFINER are left untouched.
GRANT SELECT, INSERT, UPDATE ON `ensenada_web`.`usuarios` TO 'ensenada_web_app'@'127.0.0.1';
GRANT SELECT, INSERT, DELETE ON `ensenada_web`.`usuario_roles` TO 'ensenada_web_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON `ensenada_web`.`noticias` TO 'ensenada_web_app'@'127.0.0.1';
GRANT SELECT, INSERT, DELETE ON `ensenada_web`.`noticia_imagenes` TO 'ensenada_web_app'@'127.0.0.1';
GRANT SELECT, INSERT ON `ensenada_web`.`auditoria_noticias` TO 'ensenada_web_app'@'127.0.0.1';
GRANT SELECT, UPDATE ON `ensenada_web`.`portal_site_content` TO 'ensenada_web_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON `ensenada_web`.`publicaciones_hacienda` TO 'ensenada_web_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON `ensenada_web`.`archivos_hacienda` TO 'ensenada_web_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON `ensenada_web`.`documentos_proveedores` TO 'ensenada_web_app'@'127.0.0.1';
GRANT SELECT, INSERT, DELETE ON `ensenada_web`.`auth_sessions` TO 'ensenada_web_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON `ensenada_web`.`auth_login_windows` TO 'ensenada_web_app'@'127.0.0.1';
GRANT SELECT, UPDATE (name) ON `ensenada_web`.`auth_mutexes` TO 'ensenada_web_app'@'127.0.0.1';
