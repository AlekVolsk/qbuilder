<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QBuilder\Condition\ConditionJoin;
use QBuilder\Exceptions\InvalidIdentifierException;
use QBuilder\Exceptions\InvalidQueryException;
use QBuilder\Exceptions\UnsupportedFeatureException;
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;

/**
 * @internal
 *
 * @coversNothing
 */
final class IndexHintTest extends TestCase
{
    public function testMysqlHintsOnFromTable(): void
    {
        $sql = (new QueryBuilder())->select('id')->from('orders', 'o')
            ->forceIndex('idx_created')
            ->ignoreIndex(['idx_a', 'idx_b'], QbConsts::INDEX_FOR_ORDER_BY)
            ->build(true)
        ;

        self::assertSame(
            'SELECT `o`.`id` FROM `orders` AS `o` FORCE INDEX (`idx_created`) '
                .'IGNORE INDEX FOR ORDER BY (`idx_a`, `idx_b`)',
            $sql
        );
    }

    public function testMysqlHintAppliesToLastJoin(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('id')->from('orders', 'o')
            ->useIndex('idx_status', QbConsts::INDEX_FOR_JOIN)
            ->leftJoin('users', 'u', ConditionJoin::create($qb, 'id', 'user_id', 'o'))
            ->useIndex('PRIMARY')
            ->build(true)
        ;

        self::assertSame(
            'SELECT `o`.`id` FROM `orders` AS `o` USE INDEX FOR JOIN (`idx_status`) '
                .'LEFT JOIN `users` AS `u` USE INDEX (`PRIMARY`) ON (`u`.`id` = `o`.`user_id`)',
            $sql
        );
    }

    public function testMysqlEmptyUseIndex(): void
    {
        $sql = (new QueryBuilder())->select('id')->from('orders')->useIndex([])->build(true);

        self::assertSame('SELECT `id` FROM `orders` USE INDEX ()', $sql);
    }

    public function testMssqlForceIndex(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_MSSQL);
        $sql = $qb->select('id')->from('orders', 'o')
            ->forceIndex('ix_created')
            ->forceIndex('ix_status')
            ->innerJoin('users', 'u', ConditionJoin::create($qb, 'id', 'user_id', 'o'))
            ->forceIndex('pk_users')
            ->build(true)
        ;

        self::assertStringContainsString('FROM [orders] AS [o] WITH (INDEX([ix_created], [ix_status])) INNER', $sql);
        self::assertStringContainsString('INNER JOIN [users] AS [u] WITH (INDEX([pk_users])) ON', $sql);
    }

    #[DataProvider('provideMssqlRejectsHintsWithoutEquivalentCases')]
    public function testMssqlRejectsHintsWithoutEquivalent(string $method, string $for): void
    {
        $qb = (new QueryBuilder(QbConsts::DRIVER_MSSQL))->select('id')->from('orders');
        $qb->{$method}('ix_created', $for);

        $this->expectException(UnsupportedFeatureException::class);

        $qb->build();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideMssqlRejectsHintsWithoutEquivalentCases(): iterable
    {
        yield 'use index' => ['useIndex', ''];

        yield 'ignore index' => ['ignoreIndex', ''];

        yield 'force index with scope' => ['forceIndex', QbConsts::INDEX_FOR_JOIN];
    }

    #[DataProvider('provideOtherDriversIgnoreHintsCases')]
    public function testOtherDriversIgnoreHints(string $driver, string $expected): void
    {
        $sql = (new QueryBuilder($driver))->select('id')->from('orders')
            ->useIndex('idx_a', QbConsts::INDEX_FOR_ORDER_BY)
            ->ignoreIndex('idx_b')
            ->build(true)
        ;

        self::assertSame($expected, $sql);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideOtherDriversIgnoreHintsCases(): iterable
    {
        yield 'postgresql' => [QbConsts::DRIVER_PGSQL, 'SELECT "id" FROM "orders"'];

        yield 'sqlite' => [QbConsts::DRIVER_SQLITE, 'SELECT "id" FROM "orders"'];

        yield 'oracle' => [QbConsts::DRIVER_ORACLE, 'SELECT "ID" FROM "ORDERS"'];

        yield 'clickhouse' => [QbConsts::DRIVER_CLICKHOUSE, 'SELECT `id` FROM `orders`'];
    }

    public function testHintBeforeFromThrows(): void
    {
        $this->expectException(InvalidQueryException::class);

        (new QueryBuilder())->select('id')->useIndex('idx_a');
    }

    public function testHintOnSubqueryThrows(): void
    {
        $qb = new QueryBuilder();

        $this->expectException(InvalidQueryException::class);

        $qb->select('id')->from($qb->subQuery()->select('id')->from('orders'), 's')->useIndex('idx_a');
    }

    public function testHintOutsideSelectThrows(): void
    {
        $this->expectException(InvalidQueryException::class);

        (new QueryBuilder())->update('orders')->useIndex('idx_a');
    }

    public function testUseAndForceCannotBeCombined(): void
    {
        $this->expectException(InvalidQueryException::class);

        (new QueryBuilder())->select('id')->from('orders')->useIndex('idx_a')->forceIndex('idx_b');
    }

    public function testEmptyListIsAllowedOnlyForUse(): void
    {
        $this->expectException(InvalidQueryException::class);

        (new QueryBuilder())->select('id')->from('orders')->forceIndex([]);
    }

    public function testInvalidScopeThrows(): void
    {
        $this->expectException(InvalidQueryException::class);

        (new QueryBuilder())->select('id')->from('orders')->useIndex('idx_a', 'WHERE');
    }

    public function testIndexNameWithDotThrows(): void
    {
        $this->expectException(InvalidQueryException::class);

        (new QueryBuilder())->select('id')->from('orders')->useIndex('db.idx_a');
    }

    public function testIndexNameWithInjectionThrows(): void
    {
        $this->expectException(InvalidIdentifierException::class);

        (new QueryBuilder())->select('id')->from('orders')->useIndex('idx) UNION SELECT 1 -- ');
    }

    public function testNewQueryResetsHints(): void
    {
        $qb = new QueryBuilder();
        $qb->select('id')->from('orders')->forceIndex('idx_a');

        self::assertSame('SELECT `id` FROM `users`', $qb->select('id')->from('users')->build(true));
    }
}
