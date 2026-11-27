<?php

declare(strict_types=1);
use App\Support\SharedDatabaseLease as Lease;
use App\Support\SharedDatabaseTransfer as Transfer;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
umask(0077);
require dirname(__DIR__, 2).'/vendor/autoload.php';
$operation = $argv[1] ?? '';
$options = [];
try {
    $allowed = ['schema-config', 'out', 'review', 'proof', 'minutes', 'authorize-staging-pause', 'confirm-exclusive-account-use', 'confirm-no-background-writers',
        'authorize-mysql-password-rotation', 'confirm-account-quota', 'authorize-reviewed-data', 'authorize-restore-proof',
        'authorize-session-revocation', 'authorize-production', 'authorize-production-pause', 'authorize-recovery-with-current-data'];
    foreach (array_slice($argv, 2) as $argument) {
        if (! preg_match('/\A--([a-z-]+)(?:=(.+))?\z/D', $argument, $parts) || ! in_array($parts[1], $allowed, true) || isset($options[$parts[1]])) {
            throw new RuntimeException('Argumentos inválidos.');
        }
        $options[$parts[1]] = $parts[2] ?? true;
    }
    Lease::lock($operation !== 'status');
    switch ($operation) {
        case 'status':$state = Lease::state();
            echo json_encode(array_intersect_key($state, array_flip(['owner', 'phase', 'epoch', 'expires_at', 'copied', 'revoked', 'activated_at'])), JSON_PRETTY_PRINT)."\n";
            break;
        case 'audit-live':Transfer::audit($options['schema-config'] ?? '', $options['out'] ?? '', true);
            echo "Auditoría provisional privada creada; no autoriza publicación.\n";
            break;
        case 'pause-stage':Transfer::pauseStage($options);
            echo "Staging aparcado y conservado; producción continúa cerrada.\n";
            break;
        case 'rotate-production':Transfer::rotateProduction($options);
            echo "Credencial rotada, conexiones anteriores cerradas y .env conservado. Sin habilitar tráfico.\n";
            break;
        case 'audit':Transfer::audit($options['schema-config'] ?? '', $options['out'] ?? '');
            echo "Esquema compatible; revisión privada creada sin modificar registros.\n";
            break;
        case 'copy-storage':Transfer::copyToProduction($options);
            echo "Archivos privados copiados/verificados sin sobrescribir ni retirar originales.\n";
            break;
        case 'backup':Transfer::backup($options['schema-config'] ?? '', $options['out'] ?? '');
            echo "Respaldo completo creado; restauración aislada todavía obligatoria.\n";
            break;
        case 'approve-data':Transfer::approve($options['schema-config'] ?? '', $options['review'] ?? '', $options);
            echo "Revisión manual vinculada a los datos y archivos actuales.\n";
            break;
        case 'attest-restore':Transfer::attestRestore($options['proof'] ?? '', $options);
            echo "Restauración vinculada al respaldo verificada.\n";
            break;
        case 'activate':Transfer::activate($options['schema-config'] ?? '', $options);
            echo "Producción habilitada explícitamente; sesiones anteriores revocadas. Apache no fue modificado.\n";
            break;
        case 'pause-production':Transfer::pauseProduction($options);
            echo "Producción pausada sin restaurar ni modificar contenido.\n";
            break;
        case 'recover-stage':Transfer::recovery($options);
            echo "Recuperación exclusiva y temporal de staging con los datos y archivos más recientes.\n";
            break;
        case 'close-recovery':Transfer::flag($options, 'authorize-production-pause');
            $state = Transfer::state();
            if ($state['owner'] !== 'recovery') {
                throw new RuntimeException('No hay recuperación activa.');
            }Transfer::state(['phase' => 'paused']);
            echo "Recuperación cerrada inmediatamente.\n";
            break;
        default:throw new RuntimeException('Operación: status, audit-live, pause-stage, rotate-production, audit, copy-storage, backup, approve-data, attest-restore, activate, pause-production, recover-stage, close-recovery.');
    }
} catch (Throwable $error) {
    $message = $error instanceof PDOException ? 'Operación MySQL rechazada; revisar credenciales/permisos en privado. No se habilitó ningún backend.' : $error->getMessage();
    fwrite(STDERR, 'BLOQUEADO: '.$message."\n");
    exit(1);
}
