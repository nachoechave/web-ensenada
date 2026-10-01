ALTER TABLE noticias ADD COLUMN slug VARCHAR(280) NULL;

UPDATE noticias
SET slug = CONCAT('noticia-', id)
WHERE slug IS NULL OR slug = '';

ALTER TABLE noticias MODIFY COLUMN slug VARCHAR(280) NOT NULL;

CREATE UNIQUE INDEX uk_noticias_slug ON noticias(slug);
