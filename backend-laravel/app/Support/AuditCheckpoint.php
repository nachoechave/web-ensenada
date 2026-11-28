<?php

namespace App\Support;

use PDO;
use RuntimeException;

/** Detection of changes to already observed audit rows; never claims DB immutability. */
final class AuditCheckpoint
{
    private static function rows(PDO $pdo): array
    {
        $rows = [];
        $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
        try {
            foreach ($pdo->query('SELECT * FROM auditoria_noticias ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $row = array_map(static fn ($v) => $v === null ? null : (string) $v, $row);
                ksort($row);
                $rows[(string) $row['id']] = hash_hmac('sha256', json_encode($row, JSON_THROW_ON_ERROR), SharedDatabaseLease::key());
            }
        } finally {
            $pdo->rollBack();
        }

        return $rows;
    }

    public static function capture(PDO $pdo, string $out, ?string $previous = null): void
    {
        $state = SharedDatabaseLease::state();
        $latest = $state['audit_latest_checkpoint'] ?? null;
        if ($latest !== null) {
            if ($previous !== null && $previous !== $latest['file']) {
                throw new RuntimeException('No se permite retroceder a un checkpoint anterior.');
            }
            $previous = $latest['file'];
            if (! is_file($previous) || is_link($previous) || ! hash_equals($latest['sha256'], hash_file('sha256', $previous))) {
                self::blockIncident();
                throw new RuntimeException('Checkpoint más reciente modificado; API cerrada.');
            }
        }
        // Compare and capture the SAME SQL snapshot; do not sign a second, later read.
        $current = self::rows($pdo);
        if ($previous !== null) {
            try {
                self::verifyRows($current, $previous);
            } catch (\Throwable $error) {
                self::blockIncident();
                throw new RuntimeException('Auditoría alterada; no se genera un checkpoint nuevo.', previous: $error);
            }
        }
        $body = ['version' => 1, 'database' => 'ensenada_web', 'epoch' => SharedDatabaseLease::state()['epoch'],
            'created_at' => gmdate(DATE_ATOM), 'rows' => $current];
        SharedDatabaseTransfer::newJson($out, ['checkpoint' => $body, 'mac' => hash_hmac('sha256', json_encode($body, JSON_THROW_ON_ERROR), SharedDatabaseLease::key())]);
        SharedDatabaseLease::save([...SharedDatabaseLease::state(), 'audit_latest_checkpoint' => ['file' => $out, 'sha256' => hash_file('sha256', $out)]]);
    }

    public static function verify(PDO $pdo, string $file): void
    {
        self::verifyRows(self::rows($pdo), $file);
    }

    private static function verifyRows(array $current, string $file): void
    {
        $record = SharedDatabaseTransfer::readJson($file);
        $body = $record['checkpoint'] ?? [];
        if (($body['version'] ?? 0) !== 1 || ($body['database'] ?? '') !== 'ensenada_web' || ! is_array($body['rows'] ?? null)
            || ! hash_equals($record['mac'] ?? '', hash_hmac('sha256', json_encode($body, JSON_THROW_ON_ERROR), SharedDatabaseLease::key()))) {
            throw new RuntimeException('Checkpoint de auditoría no auténtico.');
        }
        foreach ($body['rows'] as $id => $hash) {
            if (! isset($current[$id]) || ! hash_equals($hash, $current[$id])) {
                throw new RuntimeException('Fila observada de auditoría retirada o alterada.');
            }
        }
        if ($body['rows'] !== []) {
            $max = max(array_keys($body['rows']));
            foreach ($current as $id => $hash) {
                if ($id <= $max && ! isset($body['rows'][$id])) {
                    throw new RuntimeException('Inserción retroactiva en auditoría observada.');
                }
            }
        }
    }

    public static function attest(PDO $pdo, string $file, array $options): void
    {
        SharedDatabaseTransfer::flag($options, 'authorize-offsite-audit-copy');
        $state = SharedDatabaseLease::state();
        if ($state['phase'] !== 'prepared') {
            throw new RuntimeException('Anclar auditoría antes de activar, con la base congelada.');
        }
        $reference = $options['offsite-reference'] ?? '';
        if (! is_string($reference) || strlen(trim($reference)) < 8 || strlen($reference) > 500 || preg_match('/[\x00-\x1f\x7f]/', $reference)) {
            throw new RuntimeException('Referencia a custodia externa requerida.');
        }
        $current = self::rows($pdo);
        self::verifyRows($current, $file);
        $record = SharedDatabaseTransfer::readJson($file);
        if (($state['audit_latest_checkpoint']['file'] ?? '') !== $file || ($state['audit_latest_checkpoint']['sha256'] ?? '') !== hash_file('sha256', $file)) {
            throw new RuntimeException('Anclar únicamente el checkpoint más reciente.');
        }
        if ($record['checkpoint']['epoch'] !== $state['epoch']) {
            throw new RuntimeException('Checkpoint de otro corte.');
        }
        SharedDatabaseLease::save([...$state, 'audit_anchor' => ['file' => $file, 'sha256' => hash_file('sha256', $file), 'offsite_reference' => $reference], 'profile_activation' => null]);
    }

    public static function requireAnchor(PDO $pdo): void
    {
        if (ProductionDatabaseProfile::selected() === 'per-table') {
            return;
        }
        $anchor = SharedDatabaseLease::state()['audit_anchor'] ?? [];
        $file = $anchor['file'] ?? '';
        if (! is_file($file) || is_link($file) || ! hash_equals($anchor['sha256'] ?? '', hash_file('sha256', $file))) {
            throw new RuntimeException('Copia externa documentada del checkpoint pendiente o modificada.');
        }
        $current = self::rows($pdo);
        self::verifyRows($current, $file);
        $latest = SharedDatabaseLease::state()['audit_latest_checkpoint'] ?? null;
        if ($latest !== null) {
            if (! is_file($latest['file']) || is_link($latest['file']) || ! hash_equals($latest['sha256'], hash_file('sha256', $latest['file']))) {
                throw new RuntimeException('Checkpoint más reciente ausente o modificado.');
            }
            self::verifyRows($current, $latest['file']);
        }
    }

    private static function blockIncident(): void
    {
        $state = SharedDatabaseLease::state();
        SharedDatabaseTransfer::closeProduction();
        SharedDatabaseLease::save([...$state, 'integrity_blocked' => true, 'integrity_blocked_at' => gmdate(DATE_ATOM), 'phase' => 'paused']);
    }

    public static function monitor(PDO $pdo, string $file): void
    {
        $latest = SharedDatabaseLease::state()['audit_latest_checkpoint'] ?? null;
        if ($latest !== null && is_file($file) && ! is_link($file) && ! hash_equals($latest['sha256'], hash_file('sha256', $file)) && $file !== $latest['file']) {
            throw new RuntimeException('Verificar el checkpoint más reciente; no se permite retroceder la observación.');
        }
        try {
            self::verify($pdo, $file);
        } catch (\Throwable $error) {
            self::blockIncident();
            throw new RuntimeException('ALERTA de integridad: API cerrada; conservar evidencia y aplicar respuesta a incidentes.', previous: $error);
        }
    }
}
