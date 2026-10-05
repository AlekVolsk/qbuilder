<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use QBuilder\Condition\ConditionJoin;
use QBuilder\Exceptions\InvalidIdentifierException;
use QBuilder\Exceptions\InvalidQueryException;
use QBuilder\Exceptions\UnsupportedFeatureException;
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;
use Testo\Assert;
use Testo\Assert\ExpectException;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

/**
 * @internal
 */
#[Test]
final class IndexHintTest
{
    public function testMysqlHintsOnFromTable(): void
    {
        $sql = (new QueryBuilder())->select('id')->from('orders', 'o')
            ->forceIndex('idx_created')
            ->ignoreIndex(['idx_a', 'idx_b'], QbConsts::INDEX_FOR_ORDER_BY)
            ->build(true)
        ;

        Assert::same($sql, 'SELECT `o`.`id` FROM `orders` AS `o` FORCE INDEX (`idx_created`) '
            . 'IGNORE INDEX FOR ORDER BY (`idx_a`, `idx_b`)');
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

        Assert::same($sql, 'SELECT `o`.`id` FROM `orders` AS `o` USE INDEX FOR JOIN (`idx_status`) '
            . 'LEFT JOIN `users` AS `u` USE INDEX (`PRIMARY`) ON (`u`.`id` = `o`.`user_id`)');
    }

    public function testMysqlEmptyUseIndex(): void
    {
        $sql = (new QueryBuilder())->select('id')->from('orders')->useIndex([])->build(true);

        Assert::same($sql, 'SELECT `id` FROM `orders` USE INDEX ()');
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

        Assert::same(
            $sql,
            'SELECT [id] FROM [orders] AS [o] WITH (INDEX([ix_created], [ix_status])) INNER JOIN '
                . '[users] AS [u] WITH (INDEX([pk_users])) ON ([u].[id] = [o].[user_id])'
        );
    }

    /**
     * @param \Closure(QueryBuilder): QueryBuilder $addHint
     */
    #[DataProvider('provideMssqlRejectsHintsWithoutEquivalentCases')]
    public function testMssqlRejectsHintsWithoutEquivalent(\Closure $addHint): void
    {
        $qb = $addHint((new QueryBuilder(QbConsts::DRIVER_MSSQL))->select('id')->from('orders'));

        Expect::exception(UnsupportedFeatureException::class);

        $qb->build();
    }

    /**
     * @return iterable<string, array{\Closure(QueryBuilder): QueryBuilder}>
     */
    public static function provideMssqlRejectsHintsWithoutEquivalentCases(): iterable
    {
        yield 'use index' => [static fn (QueryBuilder $qb): QueryBuilder => $qb->useIndex('ix_created')];

        yield 'ignore index' => [static fn (QueryBuilder $qb): QueryBuilder => $qb->ignoreIndex('ix_created')];

        yield 'force index with scope' => [
            static fn (QueryBuilder $qb): QueryBuilder => $qb->forceIndex('ix_created', QbConsts::INDEX_FOR_JOIN),
        ];
    }

    #[DataProvider('provideOtherDriversIgnoreHintsCases')]
    public function testOtherDriversIgnoreHints(string $driver, string $expected): void
    {
        $sql = (new QueryBuilder($driver))->select('id')->from('orders')
            ->useIndex('idx_a', QbConsts::INDEX_FOR_ORDER_BY)
            ->ignoreIndex('idx_b')
            ->build(true)
        ;

        Assert::same($sql, $expected);
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

    #[ExpectException(InvalidQueryException::class)]
    public function testHintBeforeFromThrows(): void
    {
        (new QueryBuilder())->select('id')->useIndex('idx_a');
    }

    public function testHintOnSubqueryThrows(): void
    {
        $qb = new QueryBuilder();

        Expect::exception(InvalidQueryException::class);

        $qb->select('id')->from($qb->subQuery()->select('id')->from('orders'), 's')->useIndex('idx_a');
    }

    #[ExpectException(InvalidQueryException::class)]
    public function testHintOutsideSelectThrows(): void
    {
        (new QueryBuilder())->update('orders')->useIndex('idx_a');
    }

    #[ExpectException(InvalidQueryException::class)]
    public function testUseAndForceCannotBeCombined(): void
    {
        (new QueryBuilder())->select('id')->from('orders')->useIndex('idx_a')->forceIndex('idx_b');
    }

    #[ExpectException(InvalidQueryException::class)]
    public function testEmptyListIsAllowedOnlyForUse(): void
    {
        (new QueryBuilder())->select('id')->from('orders')->forceIndex([]);
    }

    #[ExpectException(InvalidQueryException::class)]
    public function testInvalidScopeThrows(): void
    {
        (new QueryBuilder())->select('id')->from('orders')->useIndex('idx_a', 'WHERE');
    }

    #[ExpectException(InvalidQueryException::class)]
    public function testIndexNameWithDotThrows(): void
    {
        (new QueryBuilder())->select('id')->from('orders')->useIndex('db.idx_a');
    }

    #[ExpectException(InvalidIdentifierException::class)]
    public function testIndexNameWithInjectionThrows(): void
    {
        (new QueryBuilder())->select('id')->from('orders')->useIndex('idx) UNION SELECT 1 -- ');
    }

    public function testNewQueryResetsHints(): void
    {
        $qb = new QueryBuilder();
        $qb->select('id')->from('orders')->forceIndex('idx_a');

        Assert::same($qb->select('id')->from('users')->build(true), 'SELECT `id` FROM `users`');
    }
}
