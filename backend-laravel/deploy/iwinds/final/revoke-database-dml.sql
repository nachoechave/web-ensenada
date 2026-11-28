-- Apply only after checking the effective account and the existing escaped schema grant.
-- Apply schema-grants.sql first, with the API closed, using the authorized grantor.
REVOKE SELECT, INSERT, UPDATE, DELETE ON `ensenada\_web`.* FROM 'ensenada_web_app'@'localhost';
