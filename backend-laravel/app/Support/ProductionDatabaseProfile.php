<?php

namespace App\Support;

use RuntimeException;

/** Signed, local risk acceptance. Never an .env switch or a grant escalation. */
final class ProductionDatabaseProfile
{
    public const ACKS = ['database_wide_mutation', 'audit_not_append_only', 'sql_compromise_can_escalate_roles', 'shared_unix_account',
        'schema_account_offline', 'offsite_backups', 'periodic_audit_checks', 'unobserved_changes_between_checkpoints'];

    public static function policyDigest(): string
    {
        $file = dirname(__DIR__, 2).'/deploy/production/CPANEL-DML-RISK.md';
        if (is_link($file) || ! is_file($file)) {
            throw new RuntimeException('Política de riesgo ausente.');
        }

        return hash_file('sha256', $file);
    }

    public static function validateReview(array $review): void
    {
        if (($review['version'] ?? null) !== 1 || ($review['profile'] ?? '') !== 'cpanel-dml' || ($review['database'] ?? '') !== 'ensenada_web'
            || ($review['policy_sha256'] ?? '') !== self::policyDigest() || array_keys($review['acknowledgements'] ?? []) !== self::ACKS) {
            throw new RuntimeException('Aceptación de riesgo incompleta o de otra política.');
        }
        foreach (self::ACKS as $key) {
            if ($review['acknowledgements'][$key] !== true) {
                throw new RuntimeException('Riesgo no aceptado: '.$key);
            }
        }
        foreach (['accepted_by', 'authority_reference', 'offsite_backup_reference', 'audit_monitoring_reference', 'incident_response_reference'] as $key) {
            $value = $review[$key] ?? null;
            if (! is_string($value) || strlen(trim($value)) < 8 || strlen($value) > 500 || preg_match('/[\x00-\x1f\x7f]/', $value)) {
                throw new RuntimeException('Responsable o evidencia documental pendiente: '.$key);
            }
        }
    }

    public static function selected(): string
    {
        $state = SharedDatabaseLease::state();
        $profile = $state['database_profile'] ?? 'per-table';
        if (! empty($state['integrity_blocked'])) {
            throw new RuntimeException('Incidente de integridad pendiente; conservar evidencia sin habilitar backends.');
        }
        if ($profile === 'per-table') {
            return $profile;
        }
        if ($profile !== 'cpanel-dml' || ! empty($state['integrity_blocked'])) {
            throw new RuntimeException('Perfil SQL no autorizado o incidente de integridad.');
        }
        $receipt = $state['risk_acceptance'] ?? [];
        $file = SharedDatabaseLease::ROOT.'/'.($receipt['file'] ?? '');
        if (! preg_match('/\Arisk-[a-f0-9]{64}[.]json\z/D', $receipt['file'] ?? '') || is_link($file) || ! is_file($file) || (fileperms($file) & 0777) !== 0600
            || ! hash_equals($receipt['sha256'] ?? '', hash_file('sha256', $file))) {
            throw new RuntimeException('Comprobante de aceptación ausente o alterado.');
        }
        $record = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
        self::validateReview($record['review'] ?? []);

        return $profile;
    }

    public static function configure(array $options): void
    {
        $state = SharedDatabaseLease::state();
        $profile = $options['profile'] ?? '';
        if (($state['owner'] ?? '') !== 'staging' || ($state['phase'] ?? '') !== 'legacy' || is_file(SharedDatabaseLease::PROD.'/.iwinds-production-enabled')) {
            throw new RuntimeException('Elegir perfil solo con staging propietario y producción cerrada, antes del corte.');
        }
        if ($profile === 'per-table') {
            SharedDatabaseLease::save([...$state, 'database_profile' => 'per-table', 'risk_acceptance' => null, 'profile_activation' => null, 'audit_anchor' => null]);

            return;
        }
        if ($profile !== 'cpanel-dml') {
            throw new RuntimeException('Perfil válido: per-table o cpanel-dml.');
        }
        SharedDatabaseTransfer::flag($options, 'accept-cpanel-dml-production-risk');
        $review = SharedDatabaseTransfer::readJson($options['risk-review'] ?? '');
        self::validateReview($review);
        ProductionPermissions::verifyPdo(SharedDatabaseAudit::connect(SharedDatabaseAudit::env(SharedDatabaseLease::STAGE)), 'cpanel-dml');
        ProductionPermissions::verifySchemaPdo(SharedDatabaseAudit::connect(SharedDatabaseAudit::schemaConfig($options['schema-config'] ?? '')));
        $record = ['accepted_at' => gmdate(DATE_ATOM), 'review' => $review];
        $bytes = json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
        $sha = hash('sha256', $bytes);
        $file = 'risk-'.$sha.'.json';
        SharedDatabaseLease::atomic(SharedDatabaseLease::ROOT.'/'.$file, $bytes);
        SharedDatabaseLease::save([...$state, 'database_profile' => $profile, 'risk_acceptance' => ['file' => $file, 'sha256' => $sha],
            'profile_activation' => null, 'audit_anchor' => null]);
    }

    public static function requireActivationFlag(array $options): void
    {
        if (self::selected() === 'cpanel-dml') {
            SharedDatabaseTransfer::flag($options, 'accept-cpanel-dml-production-risk');
        }
    }

    public static function activationRecord(): ?array
    {
        if (self::selected() === 'per-table') {
            return null;
        }
        $s = SharedDatabaseLease::state();

        return ['epoch' => $s['epoch'], 'risk_sha256' => $s['risk_acceptance']['sha256'], 'anchor_sha256' => $s['audit_anchor']['sha256'] ?? ''];
    }

    public static function verifyActivation(): void
    {
        if (self::selected() === 'per-table') {
            return;
        }
        $expected = self::activationRecord();
        $actual = SharedDatabaseLease::state()['profile_activation'] ?? null;
        if (empty($expected['anchor_sha256']) || $actual !== $expected) {
            throw new RuntimeException('Activación cpanel-dml sin autorización y anclaje vinculados al corte.');
        }
    }
}
