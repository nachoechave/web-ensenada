-- Disposable Docker fixture only, never execute in iWinds.
CREATE USER 'ensenada_web_schema'@'127.0.0.1' IDENTIFIED BY 'synthetic-schema-only';
CREATE USER 'ensenada_web_schema'@'localhost' IDENTIFIED BY 'synthetic-schema-only';
GRANT ALL ON `ensenada\_web`.* TO 'ensenada_web_schema'@'127.0.0.1';
GRANT ALL ON `ensenada\_web`.* TO 'ensenada_web_schema'@'localhost';
