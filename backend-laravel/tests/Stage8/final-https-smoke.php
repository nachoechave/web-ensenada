<?php

declare(strict_types=1);

use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

if (! is_file('/.dockerenv') || getenv('IW_FINAL_TEST') !== 'yes') {
    exit(1);
}
require '/home/ensenada/backend-laravel/vendor/autoload.php';

function interactive(string $command, array $answers): void
{
    $input = new InputStream;
    $process = new Process(['script', '-qef', '-E', 'never', '-c', $command, '/dev/null'], input: $input, timeout: 240);
    $process->start();
    $offset = 0;
    foreach ($answers as [$prompt, $value]) {
        while (($position = strpos($process->getOutput(), $prompt, $offset)) === false && $process->isRunning()) {
            $process->checkTimeout();
            usleep(30000);
        }
        if ($position === false) {
            fwrite(STDERR, $process->getOutput().$process->getErrorOutput());
            throw new RuntimeException('No apareció el prompt '.$prompt);
        }
        $offset = $position + strlen($prompt);
        $input->write($value."\n");
    }
    $input->close();
    $result = $process->wait();
    echo $process->getOutput().$process->getErrorOutput();
    if ($result !== 0) {
        exit($result);
    }
}

$account = 'super_admin@example.test';
$password = 'Synthetic-final-https-only-1234';
if (! is_file('/home/ensenada/backend-laravel/.iwinds-staging-admin-rotation')) {
    interactive('php /home/ensenada/backend-laravel/deploy/iwinds/provision-staging.php rotate-admin', [
        ['Correo del SUPER_ADMIN', $account],
        ['Escribí ROTAR', 'ROTAR'],
        ['Contraseña nueva', $password],
        ['Confirmar contraseña', $password],
    ]);
}
interactive('bash /home/ensenada/iwinds-final/accept.sh --write-synthetic /tmp/synthetic-backup', [
    ['Usuario Basic', 'synthetic-basic'],
    ['Contraseña Basic', 'synthetic-password'],
    ['escribiendo STAGING SINTETICO:', 'STAGING SINTETICO'],
    ['Usuario Basic', 'synthetic-basic'],
    ['Contraseña Basic', 'synthetic-password'],
    ['Correo del SUPER_ADMIN', $account],
    ['Contraseña de Laravel', $password],
    ['Usuario Basic', 'synthetic-basic'],
    ['Contraseña Basic', 'synthetic-password'],
]);
