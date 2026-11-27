<?php

namespace Tests\Unit;

use App\Support\SharedDatabaseAudit;
use App\Support\SharedDatabaseTransfer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SharedDatabaseReviewTest extends TestCase
{
    private function snapshot(): array
    {
        return ['review' => ['noticias:42' => ['fingerprint' => 'news-hash'], 'file:noticias/historica.jpg' => ['fingerprint' => 'image-hash']],
            'public' => ['noticias:42', 'file:noticias/historica.jpg']];
    }

    private function decisions(): array
    {
        return ['noticias:42' => ['fingerprint' => 'news-hash', 'classification' => 'APPROVED', 'reason' => 'Revisada por editor autorizado'],
            'file:noticias/historica.jpg' => ['fingerprint' => 'image-hash', 'classification' => 'APPROVED', 'reason' => 'Fotografía revisada']];
    }

    #[DataProvider('invalidReviews')]
    public function test_rejects_unapproved_or_stale_public_data(string $change): void
    {
        $review = $this->decisions();
        switch ($change) {
            case 'missing':unset($review['file:noticias/historica.jpg']);
                break;
            case 'extra':$review['usuarios:999'] = $review['noticias:42'];
                break;
            case 'stale':$review['noticias:42']['fingerprint'] = 'changed';
                break;
            case 'synthetic':$review['noticias:42']['classification'] = 'SYNTHETIC';
                break;
            case 'private':$review['noticias:42']['classification'] = 'PRIVATE-HISTORICAL';
                break;
            case 'unreviewed':$review['noticias:42']['classification'] = 'UNREVIEWED';
                break;
            case 'blank':$review['noticias:42']['reason'] = ' ';
                break;
        }
        $this->expectException(RuntimeException::class);
        SharedDatabaseAudit::approve($this->snapshot(), $review);
    }

    public static function invalidReviews(): iterable
    {
        foreach (['missing', 'extra', 'stale', 'synthetic', 'private', 'unreviewed', 'blank'] as $item) {
            yield [$item];
        }
    }

    public function test_private_synthetic_data_can_be_retained_without_approving_it_for_publication(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['public'] = [];
        $review = $this->decisions();
        foreach ($review as &$item) {
            $item['classification'] = 'SYNTHETIC';
        }unset($item);
        SharedDatabaseAudit::approve($snapshot, $review);
        self::assertCount(2, $review);
    }

    public function test_schema_comparison_preserves_constraints_and_only_ignores_counter_and_definer(): void
    {
        self::assertSame('CREATE TABLE t (id bigint PRIMARY KEY) ENGINE=InnoDB', SharedDatabaseAudit::normalizeDdl('CREATE TABLE t (id bigint PRIMARY KEY) ENGINE=InnoDB AUTO_INCREMENT=123'));
        self::assertNotSame(SharedDatabaseAudit::normalizeDdl('CREATE TABLE t (id bigint)'), SharedDatabaseAudit::normalizeDdl('CREATE TABLE t (id bigint PRIMARY KEY)'));
        self::assertSame('CREATE DEFINER=CURRENT_USER TRIGGER x', SharedDatabaseAudit::normalizeDdl('CREATE DEFINER=`schema`@`localhost` TRIGGER x'));
    }

    public function test_independent_keys_accept_existing_keys_without_regenerating_them(): void
    {
        $stage = ['APP_KEY' => 'base64:'.base64_encode(str_repeat('a', 32)), 'AUTH_JWT_SECRET' => str_repeat('b', 64)];
        $prod = ['APP_KEY' => 'base64:'.base64_encode(str_repeat('c', 32)), 'AUTH_JWT_SECRET' => str_repeat('d', 64)];
        SharedDatabaseTransfer::independentKeys($stage, $prod);
        self::assertNotSame($stage['APP_KEY'], $prod['APP_KEY']);
        $this->expectException(RuntimeException::class);
        SharedDatabaseTransfer::independentKeys($stage, [...$prod, 'APP_KEY' => $stage['APP_KEY']]);
    }
}
