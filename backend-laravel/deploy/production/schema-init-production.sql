-- Intentionally disabled. Existing ensenada_web must never be initialized or recreated.
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Esquema existente: ejecutar auditoria, no inicializacion';
