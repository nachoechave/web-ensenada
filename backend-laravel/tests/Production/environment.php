<?php

$root = '/home/ensenada/backend-laravel-produccion';
$env = file_get_contents('/source/deploy/production/environment.example');
$env = str_replace('APP_KEY=', 'APP_KEY=base64:'.base64_encode(random_bytes(32)), $env);
$env = str_replace('AUTH_JWT_SECRET=', 'AUTH_JWT_SECRET='.bin2hex(random_bytes(48)), $env);
$env = str_replace('DB_PASSWORD=', 'DB_PASSWORD=synthetic-prod-runtime-only', $env);
file_put_contents($root.'/.env', $env);
file_put_contents('/home/ensenada/backend-laravel/.env', "APP_KEY=synthetic-other-key\nAUTH_JWT_SECRET=synthetic-other-jwt\nDB_PASSWORD=synthetic-staging-only\n");
file_put_contents('/tmp/prod-setup.cnf', "[client]\nhost=127.0.0.1\nuser=prod_setup\npassword=synthetic-prod-setup-only\ndatabase=ensenada_web_prod\n");
chmod('/tmp/prod-setup.cnf', 0600);
