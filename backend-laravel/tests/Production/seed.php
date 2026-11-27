<?php

$pdo = new PDO('mysql:host=127.0.0.1;dbname=ensenada_web_prod', 'prod_setup', 'synthetic-prod-setup-only', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$hash = password_hash('Synthetic-Production-Test-123!', PASSWORD_BCRYPT, ['cost' => 4]);
foreach (['PRENSA', 'HACIENDA'] as $role) {
    $pdo->prepare('INSERT INTO usuarios(nombre,email,password,activo,rol,token_version) VALUES(?,?,?,1,?,0)')->execute([$role, strtolower($role).'@example.test', $hash, $role]);
    $pdo->prepare('INSERT INTO usuario_roles(usuario_id,rol) VALUES(?,?)')->execute([$pdo->lastInsertId(), $role]);
}
