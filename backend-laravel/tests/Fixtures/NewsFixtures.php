<?php

namespace Tests\Fixtures;

use PDO;

final class NewsFixtures
{
    public static function insert(PDO $connection): void
    {
        $insert = $connection->prepare('INSERT INTO noticias (id, titulo, slug, bajada, contenido, imagen, categoria, fecha_publicacion, estado, destacada) VALUES (?,?,?,?,?,?,?,?,?,?)');
        for ($id = 1; $id <= 52; $id++) {
            $insert->execute([$id, "Noticia sintética $id", "noticia-$id", "Bajada sintética $id", "Contenido sintético $id",
                '/assets/ensenada-hero.jpg', 'Actualidad', '2025-04-02', 'PUBLICADA', (int) in_array($id, [2, 50], true)]);
        }
        $insert->execute([61, 'Borrador privado', 'borrador-privado', 'Privada', 'No publicar', '', 'Actualidad', '2025-04-03', 'BORRADOR', 1]);
        $insert->execute([62, 'Archivo privado', 'archivo-privado', 'Privada', 'No publicar', '', 'Actualidad', '2025-04-04', 'ARCHIVADA', 1]);
        $insert->execute([63, 'Crónica sintética de Ensenada', 'cronica-ensenada-2', 'Una bajada para comprobar el HTML inicial.',
            'Texto de la noticia visible antes de ejecutar JavaScript.', '/assets/ensenada-hero.jpg', 'Cultura', '2099-04-05', 'PUBLICADA', 1]);
        $gallery = $connection->prepare('INSERT INTO noticia_imagenes (noticia_id, orden, url) VALUES (?,?,?)');
        $gallery->execute([63, 2, '/assets/mario-secco.jpg']);
        $gallery->execute([63, 0, '/assets/ensenada-hero.jpg']);
        $gallery->execute([63, 1, '/assets/escudo-ensenada.png']);
        $gallery->execute([61, 0, '/uploads/noticias/privada.jpg']);
    }
}
