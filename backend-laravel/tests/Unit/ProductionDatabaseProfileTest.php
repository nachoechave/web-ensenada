<?php

namespace Tests\Unit;

use App\Support\ProductionDatabaseProfile;
use App\Support\ProductionPermissions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ProductionDatabaseProfileTest extends TestCase
{
    private const USAGE = 'GRANT USAGE ON *.* TO `ensenada_web_app`@`localhost`';

    private const DML = 'GRANT SELECT, INSERT, UPDATE, DELETE ON `ensenada\\_web`.* TO `ensenada_web_app`@`localhost`';

    public function test_exact_cpanel_scope_passes_and_remains_distinct_from_strict(): void
    {
        ProductionPermissions::assertGrants([self::USAGE, self::DML], 'ensenada_web_app@localhost', 'cpanel-dml');
        self::assertTrue(true);
        $this->expectException(RuntimeException::class);
        ProductionPermissions::assertGrants([self::USAGE, self::DML], 'ensenada_web_app@localhost');
    }

    #[DataProvider('unsafeGrants')]
    public function test_rejects_every_permission_outside_the_exact_scope(array $lines): void
    {
        $this->expectException(RuntimeException::class);
        ProductionPermissions::assertGrants($lines, 'ensenada_web_app@localhost', 'cpanel-dml');
    }

    public static function unsafeGrants(): iterable
    {
        yield [[self::USAGE, str_replace(', DELETE', '', self::DML)]];
        yield [[self::USAGE, str_replace('SELECT, INSERT, UPDATE, DELETE', 'ALL PRIVILEGES', self::DML)]];
        yield [[self::USAGE, str_replace('SELECT, INSERT, UPDATE, DELETE', 'SELECT, INSERT, UPDATE, DELETE, CREATE', self::DML)]];
        yield [[self::USAGE, self::DML.' WITH GRANT OPTION']];
        yield [[self::USAGE, self::DML, 'GRANT SELECT ON *.* TO `ensenada_web_app`@`localhost`']];
        yield [[self::USAGE, self::DML, 'GRANT SELECT ON `other_db`.* TO `ensenada_web_app`@`localhost`']];
        yield [[self::USAGE, str_replace('ensenada\\_web', 'ensenada_web', self::DML)]];
        yield [[self::USAGE, str_replace('ensenada\\_web', 'ensenada%', self::DML)]];
        yield [[self::USAGE, self::DML, self::DML]];
        yield [[self::USAGE, self::DML, 'GRANT `dangerous_role` TO `ensenada_web_app`@`localhost`']];
        yield [[self::USAGE, self::DML, 'GRANT SELECT ON `ensenada_web`.`usuarios` TO `ensenada_web_app`@`localhost`']];
        yield [[str_replace('`localhost`', '`%`', self::USAGE), self::DML]];
    }

    public function test_schema_account_has_all_only_on_literal_database_without_delegation(): void
    {
        $grant = 'GRANT ALL PRIVILEGES ON `ensenada\\_web`.* TO `ensenada_web_schema`@`localhost`';
        ProductionPermissions::assertGrants([$grant], 'ensenada_web_schema@localhost', 'cpanel-dml', true);
        self::assertTrue(true);
        $this->expectException(RuntimeException::class);
        ProductionPermissions::assertGrants([$grant.' WITH GRANT OPTION'], 'ensenada_web_schema@localhost', 'cpanel-dml', true);
    }

    public function test_empty_template_is_never_an_acceptance(): void
    {
        $review = json_decode(file_get_contents(dirname(__DIR__, 2).'/deploy/production/cpanel-dml-risk.example.json'), true);
        $this->expectException(RuntimeException::class);
        ProductionDatabaseProfile::validateReview($review);
    }

    public function test_current_policy_needs_all_acknowledgements_and_named_evidence(): void
    {
        $review = ['version' => 1, 'profile' => 'cpanel-dml', 'database' => 'ensenada_web', 'policy_sha256' => ProductionDatabaseProfile::policyDigest(),
            'acknowledgements' => array_fill_keys(ProductionDatabaseProfile::ACKS, true)];
        foreach (['accepted_by', 'authority_reference', 'offsite_backup_reference', 'audit_monitoring_reference', 'incident_response_reference'] as $key) {
            $review[$key] = 'Synthetic test authority reference';
        }
        ProductionDatabaseProfile::validateReview($review);
        self::assertTrue(true);
        $review['acknowledgements']['audit_not_append_only'] = false;
        $this->expectException(RuntimeException::class);
        ProductionDatabaseProfile::validateReview($review);
    }
}
