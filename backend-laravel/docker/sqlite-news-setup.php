<?php

use Tests\Fixtures\NewsFixtures;

require __DIR__.'/../vendor/autoload.php';

if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== '/tmp/ensenada-auth.sqlite') {
    throw new RuntimeException('La preparación de noticias SQLite requiere el contenedor descartable.');
}
$connection = new PDO('sqlite:/tmp/ensenada-news.sqlite');
$connection->exec('PRAGMA foreign_keys = ON');
if (! $connection->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'noticias'")->fetchColumn()) {
    $connection->beginTransaction();
    $connection->exec(file_get_contents(__DIR__.'/../database/schema/sqlite-news-fixture.sql'));
    NewsFixtures::insert($connection);
    $connection->commit();
}
$auth = new PDO('sqlite:/tmp/ensenada-auth.sqlite');
$auth->exec(file_get_contents(__DIR__.'/../database/schema/sqlite-audit-fixture.sql'));
$auth->exec("INSERT OR IGNORE INTO auth_mutexes (name) VALUES ('news-slug')");
