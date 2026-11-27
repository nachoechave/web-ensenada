-- Review the app account host and the grantor's effective GRANT OPTION before applying.
-- The existing schema account and trigger DEFINER are left untouched.
GRANT SELECT, INSERT, UPDATE ON `ensenada_web_prod`.`usuarios` TO 'ensenada_web_prod_app'@'localhost';
GRANT SELECT, INSERT, DELETE ON `ensenada_web_prod`.`usuario_roles` TO 'ensenada_web_prod_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON `ensenada_web_prod`.`noticias` TO 'ensenada_web_prod_app'@'localhost';
GRANT SELECT, INSERT, DELETE ON `ensenada_web_prod`.`noticia_imagenes` TO 'ensenada_web_prod_app'@'localhost';
GRANT SELECT, INSERT ON `ensenada_web_prod`.`auditoria_noticias` TO 'ensenada_web_prod_app'@'localhost';
GRANT SELECT, UPDATE ON `ensenada_web_prod`.`portal_site_content` TO 'ensenada_web_prod_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON `ensenada_web_prod`.`publicaciones_hacienda` TO 'ensenada_web_prod_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON `ensenada_web_prod`.`archivos_hacienda` TO 'ensenada_web_prod_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON `ensenada_web_prod`.`documentos_proveedores` TO 'ensenada_web_prod_app'@'localhost';
GRANT SELECT, INSERT, DELETE ON `ensenada_web_prod`.`auth_sessions` TO 'ensenada_web_prod_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON `ensenada_web_prod`.`auth_login_windows` TO 'ensenada_web_prod_app'@'localhost';
GRANT SELECT, UPDATE (name) ON `ensenada_web_prod`.`auth_mutexes` TO 'ensenada_web_prod_app'@'localhost';
