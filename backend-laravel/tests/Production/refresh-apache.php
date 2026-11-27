<?php

require '/home/ensenada/backend-laravel-produccion/vendor/autoload.php';
use Symfony\Component\Process\Process;

$candidate = '/tmp/candidate-'.bin2hex(random_bytes(8));
(new Process([PHP_BINARY, '/source/deploy/production/cutover.php', 'candidate', '/source/tests/Production/original.htaccess', $candidate, ...array_slice($argv, 1)]))->mustRun();
$current = '/home/ensenada/public_html/.htaccess';
(new Process([PHP_BINARY, '/source/deploy/production/cutover.php', 'apply', $current, $candidate, hash_file('sha256', $current), '--authorized']))->mustRun();
