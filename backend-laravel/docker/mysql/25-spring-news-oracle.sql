-- Only the historical Spring Boot oracle uses this disposable principal.
CREATE USER 'news_reader'@'%' IDENTIFIED BY 'synthetic-news-reader-only-1234';
GRANT SELECT ON ensenada_auth_test.noticias TO 'news_reader'@'%';
GRANT SELECT ON ensenada_auth_test.noticia_imagenes TO 'news_reader'@'%';
