<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use QBuilder\Exceptions\MissingRequirementException;
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;
use Testo\Assert;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

/**
 * @internal
 */
#[Test]
final class AbstractSqlBuilderTest
{
    public function testBuildInsertFromSubquery(): void
    {
        $qb = $this->getQueryBuilder();
        $subquery = $qb->subQuery()
            ->select('name', 'email')
            ->from('temp_users')
            ->where()
            ->eq('verified', 1)
            ->end()
        ;

        $qb->insert('users')
            ->insertFrom($subquery, ['name', 'email'])
        ;

        $sql = $qb->build(true);

        Assert::same(
            $sql,
            'INSERT INTO `users` (`name`, `email`) SELECT `name`, `email` FROM `temp_users` WHERE '
                . '(`verified` = 1)'
        );
    }

    public function testBuildUpdateWithSubquery(): void
    {
        $qb = $this->getQueryBuilder();
        $subquery = $qb->subQuery()
            ->select('user_id')
            ->from('temp_updates')
            ->where()
            ->eq('status', 1)
            ->end()
        ;

        $qb->updateFromSelect('users', $subquery, ['status' => 'verified'], 'id');

        $sql = $qb->build(true);

        Assert::same(
            $sql,
            'UPDATE `users` SET `status` = \'verified\' WHERE `id` IN ( SELECT `user_id` FROM '
                . '`temp_updates` WHERE (`status` = 1) )'
        );
    }

    public function testBuildDeleteWithSubquery(): void
    {
        $qb = $this->getQueryBuilder();
        $subquery = $qb->subQuery()
            ->select('user_id')
            ->from('temp_deletions')
            ->where()
            ->eq('status', 'deleted')
            ->end()
        ;

        $qb->deleteFromSelect('users', $subquery, 'id');

        $sql = $qb->build(true);

        Assert::same(
            $sql,
            'DELETE FROM `users` WHERE `id` IN ( SELECT `user_id` FROM `temp_deletions` WHERE '
                . '(`status` = \'deleted\') )'
        );
    }

    public function testBuildUpdateWithSubqueryWithCustomIdField(): void
    {
        $qb = $this->getQueryBuilder();
        $subquery = $qb->subQuery()
            ->select('user_id')
            ->from('temp_updates')
        ;

        $qb->updateFromSelect('users', $subquery, ['status' => 'verified'], 'user_id');

        $sql = $qb->build(true);

        Assert::same(
            $sql,
            'UPDATE `users` SET `status` = \'verified\' WHERE `user_id` IN ( SELECT `user_id` FROM '
                . '`temp_updates` )'
        );
    }

    public function testBuildDeleteWithSubqueryWithCustomIdField(): void
    {
        $qb = $this->getQueryBuilder();
        $subquery = $qb->subQuery()
            ->select('order_id')
            ->from('temp_deletions')
        ;

        $qb->deleteFromSelect('orders', $subquery, 'order_id');

        $sql = $qb->build(true);

        Assert::same($sql, 'DELETE FROM `orders` WHERE `order_id` IN ( SELECT `order_id` FROM `temp_deletions` )');
    }

    public function testBuildUpdateSetsFormatsUpdateDataCorrectly(): void
    {
        $qb = $this->getQueryBuilder();
        $qb->update('users')
            ->updateRow(['name' => 'John', 'age' => 30, 'active' => true])
        ;

        $sql = $qb->build(true);

        Assert::same($sql, 'UPDATE `users` SET `name` = \'John\', `age` = 30, `active` = 1');
    }

    public function testBuildUpdateSetsWithNullValues(): void
    {
        $qb = $this->getQueryBuilder();
        $qb->update('users')
            ->updateRow(['name' => null, 'age' => 30])
        ;

        $sql = $qb->build(true);

        Assert::same($sql, 'UPDATE `users` SET `name` = NULL, `age` = 30');
    }

    public function testBuildUpdateSetsWithBooleanValues(): void
    {
        $qb = $this->getQueryBuilder();
        $qb->update('users')
            ->updateRow(['active' => true, 'verified' => false])
        ;

        $sql = $qb->build(true);

        Assert::same($sql, 'UPDATE `users` SET `active` = 1, `verified` = 0');
    }

    public function testBuildInsertRowsWithMultipleRows(): void
    {
        $qb = $this->getQueryBuilder();
        $qb->insert('users')
            ->insertRow(['name' => 'John', 'age' => 30])
            ->insertRow(['name' => 'Jane', 'age' => 25])
        ;

        $sql = $qb->build(true);

        Assert::same($sql, 'INSERT INTO `users` (`name`, `age`) VALUES (\'John\', 30), (\'Jane\', 25)');
    }

    public function testBuildInsertRowsWithNullValues(): void
    {
        $qb = $this->getQueryBuilder();
        $qb->insert('users')
            ->insertRow(['name' => 'John', 'age' => null, 'email' => 'john@example.com'])
        ;

        $sql = $qb->build(true);

        Assert::same(
            $sql,
            'INSERT INTO `users` (`name`, `age`, `email`) VALUES (\'John\', NULL, \'john@example.com\')'
        );
    }

    public function testBuildInsertRowsWithNumericValues(): void
    {
        $qb = $this->getQueryBuilder();
        $qb->insert('products')
            ->insertRow(['name' => 'Product', 'price' => 99.99, 'quantity' => 10])
        ;

        $sql = $qb->build(true);

        Assert::same($sql, 'INSERT INTO `products` (`name`, `price`, `quantity`) VALUES (\'Product\', 99.99, 10)');
    }

    /**
     * @param \Closure(QueryBuilder): QueryBuilder $statement
     * @param non-empty-string                     $message
     */
    #[DataProvider('provideStatementWithoutDataIsRejectedCases')]
    public function testStatementWithoutDataIsRejected(string $driver, \Closure $statement, string $message): void
    {
        $qb = $statement(new QueryBuilder($driver));

        Expect::exception(MissingRequirementException::class)->withMessageContaining($message);

        $qb->build();
    }

    /**
     * @return iterable<string, array{string, \Closure(QueryBuilder): QueryBuilder, non-empty-string}>
     */
    public static function provideStatementWithoutDataIsRejectedCases(): iterable
    {
        foreach ([QbConsts::DRIVER_PDO_MYSQL, QbConsts::DRIVER_PGSQL, QbConsts::DRIVER_SQLITE] as $driver) {
            yield "{$driver}: INSERT without rows" => [
                $driver,
                static fn (QueryBuilder $qb): QueryBuilder => $qb->insert('users'),
                'INSERT needs at least one field',
            ];

            yield "{$driver}: INSERT of an empty row" => [
                $driver,
                static fn (QueryBuilder $qb): QueryBuilder => $qb->insert('users')->insertRow([]),
                'INSERT needs at least one field',
            ];

            yield "{$driver}: UPDATE without data" => [
                $driver,
                static fn (QueryBuilder $qb): QueryBuilder => $qb->update('users')->updateRow([]),
                'UPDATE needs at least one field',
            ];
        }

        yield 'clickhouse: UPDATE without data' => [
            QbConsts::DRIVER_CLICKHOUSE,
            static fn (QueryBuilder $qb): QueryBuilder => $qb->update('events'),
            'UPDATE needs at least one field',
        ];

        yield 'mssql: INSERT without rows' => [
            QbConsts::DRIVER_MSSQL,
            static fn (QueryBuilder $qb): QueryBuilder => $qb->insert('users'),
            'INSERT needs at least one field',
        ];
    }

    private function getQueryBuilder(): QueryBuilder
    {
        $qb = new QueryBuilder();
        $qb->setDriver(QbConsts::DRIVER_PDO_MYSQL);

        return $qb;
    }
}
