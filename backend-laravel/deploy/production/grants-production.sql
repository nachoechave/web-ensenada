-- Review the app account host and the grantor's effective GRANT OPTION before applying.
-- The existing schema account and trigger DEFINER are left untouched.
-- Authorized grantor only, with the dedicated app account confirmed and staging closed.
-- The database-wide cpanel-dml grant must exist for this first REVOKE. If the account
-- already has exactly the per-table profile, skip reapplying this script and run the checker.
-- No global privileges, accounts, passwords or other databases are changed.
REVOKE ALL PRIVILEGES ON `ensenada_web`.* FROM 'ensenada_web_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON `ensenada_web`.`usuarios` TO 'ensenada_web_app'@'localhost';
GRANT SELECT, INSERT, DELETE ON `ensenada_web`.`usuario_roles` TO 'ensenada_web_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON `ensenada_web`.`noticias` TO 'ensenada_web_app'@'localhost';
GRANT SELECT, INSERT, DELETE ON `ensenada_web`.`noticia_imagenes` TO 'ensenada_web_app'@'localhost';
GRANT SELECT, INSERT ON `ensenada_web`.`auditoria_noticias` TO 'ensenada_web_app'@'localhost';
GRANT SELECT, UPDATE ON `ensenada_web`.`portal_site_content` TO 'ensenada_web_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON `ensenada_web`.`publicaciones_hacienda` TO 'ensenada_web_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON `ensenada_web`.`archivos_hacienda` TO 'ensenada_web_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON `ensenada_web`.`documentos_proveedores` TO 'ensenada_web_app'@'localhost';
GRANT SELECT, INSERT, DELETE ON `ensenada_web`.`auth_sessions` TO 'ensenada_web_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON `ensenada_web`.`auth_login_windows` TO 'ensenada_web_app'@'localhost';
GRANT SELECT, UPDATE (name) ON `ensenada_web`.`auth_mutexes` TO 'ensenada_web_app'@'localhost';
