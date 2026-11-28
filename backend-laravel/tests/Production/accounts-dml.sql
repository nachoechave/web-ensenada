-- Disposable fixture: exact cPanel scopes, no global rights or GRANT OPTION.
CREATE USER 'ensenada_web_app'@'localhost' IDENTIFIED BY 'synthetic-staging-runtime-only';
CREATE USER 'ensenada_web_schema'@'127.0.0.1' IDENTIFIED BY 'synthetic-schema-only';
CREATE USER 'ensenada_web_schema'@'localhost' IDENTIFIED BY 'synthetic-schema-only';
GRANT SELECT,INSERT,UPDATE,DELETE ON `ensenada\_web`.* TO 'ensenada_web_app'@'localhost';
GRANT ALL PRIVILEGES ON `ensenada\_web`.* TO 'ensenada_web_schema'@'127.0.0.1';
GRANT ALL PRIVILEGES ON `ensenada\_web`.* TO 'ensenada_web_schema'@'localhost';
