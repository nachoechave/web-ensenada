CREATE TABLE portal_site_content (
    id BIGINT NOT NULL PRIMARY KEY,
    contenido_json TEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
