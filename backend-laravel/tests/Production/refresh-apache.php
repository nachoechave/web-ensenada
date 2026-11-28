<?php

require '/home/ensenada/backend-laravel-produccion/vendor/autoload.php';
use Symfony\Component\Process\Process;

$current = '/home/ensenada/public_html/.htaccess';
if (file_get_contents($current) !== file_get_contents('/source/tests/Production/original.htaccess')) {
    (new Process([PHP_BINARY, '/source/deploy/production/cutover.php', 'rollback', $current, '/source/tests/Production/original.htaccess', hash_file('sha256', $current), '--authorized']))->mustRun();
}
$candidate = '/tmp/candidate-'.bin2hex(random_bytes(8));
(new Process([PHP_BINARY, '/source/deploy/production/cutover.php', 'candidate', '/source/tests/Production/original.htaccess', $candidate, ...array_slice($argv, 1)]))->mustRun();
(new Process([PHP_BINARY, '/source/deploy/production/cutover.php', 'apply', $current, $candidate, hash_file('sha256', $current), '--authorized']))->mustRun();
