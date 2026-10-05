<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use QBuilder\Condition\ConditionBy;
use QBuilder\Condition\ConditionJoin;
use QBuilder\Condition\Field;
use QBuilder\Drivers\Mysql\MysqlDriver;
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;
use Testo\Assert;
use Testo\Data\DataProvider;
use Testo\Test;

/**
 * @internal
 */
#[Test]
final class MysqlDriverTest
{
    public function testMysqlQuoting(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('id', 'name')->from('users')->build(true);

        Assert::same($sql, 'SELECT `id`, `name` FROM `users`');
    }

    public function testMysqlOnDuplicateKeyUpdate(): void
    {
        $qb = $this->getQueryBuilder();
        $conflictBuilder = $qb->conflictBuilder()
            ->set('name', 'John Updated')
            ->sqlFunction('updated_at', 'NOW()')
        ;

        $sql = $qb->insert('users')->insertRow(['email' => 'test@example.com', 'name' => 'John'])
            ->insertConflictHandler($conflictBuilder)
            ->build(true)
        ;

        $expected = 'INSERT INTO `users` (`email`, `name`) '
            . 'VALUES (\'test@example.com\', \'John\') '
            . 'ON DUPLICATE KEY UPDATE `name` = \'John Updated\', `updated_at` = NOW()';

        Assert::same($sql, $expected);
    }

    public function testMysqlOnDuplicateKeyUpdateValues(): void
    {
        $qb = $this->getQueryBuilder();
        $conflictBuilder = $qb->conflictBuilder()
            ->expression('counter', 'VALUES(counter)')
        ;

        $sql = $qb->insert('users')->insertRow(['id' => 1, 'counter' => 1])
            ->insertConflictHandler($conflictBuilder)
            ->build(true)
        ;

        $expected = 'INSERT INTO `users` (`id`, `counter`) VALUES (1, 1) '
            . 'ON DUPLICATE KEY UPDATE `counter` = VALUES(counter)';

        Assert::same($sql, $expected);
    }

    public function testMysqlOnDuplicateKeyUpdateIncrement(): void
    {
        $qb = $this->getQueryBuilder();
        $conflictBuilder = $qb->conflictBuilder()
            ->increment('views', 1)
        ;

        $sql = $qb->insert('users')->insertRow(['id' => 1, 'views' => 1])
            ->insertConflictHandler($conflictBuilder)
            ->build(true)
        ;

        $expected = 'INSERT INTO `users` (`id`, `views`) VALUES (1, 1) '
            . 'ON DUPLICATE KEY UPDATE `views` = `views` + 1';

        Assert::same($sql, $expected);
    }

    public function testMysqlOnDuplicateKeyUpdateDecrement(): void
    {
        $qb = $this->getQueryBuilder();
        $conflictBuilder = $qb->conflictBuilder()
            ->decrement('stock', 1)
        ;

        $sql = $qb->insert('users')->insertRow(['id' => 1, 'stock' => 10])
            ->insertConflictHandler($conflictBuilder)
            ->build(true)
        ;

        $expected = 'INSERT INTO `users` (`id`, `stock`) VALUES (1, 10) '
            . 'ON DUPLICATE KEY UPDATE `stock` = `stock` - 1';

        Assert::same($sql, $expected);
    }

    public function testMysqlLimit(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('*')->from('users')->limit(10)->build(true);

        Assert::same($sql, 'SELECT * FROM `users` LIMIT 10');
    }

    public function testMysqlLimitOffset(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('*')->from('users')->limit(10, 20)->build(true);

        Assert::same($sql, 'SELECT * FROM `users` LIMIT 20, 10');
    }

    public function testMysqlProcedureNoParams(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->procedure('get_all_users')->build(true);

        Assert::same($sql, 'CALL `get_all_users`()');
    }

    public function testMysqlProcedureWithParams(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->procedure('get_user_by_id', [1, 'active'])->build(true);

        Assert::same($sql, 'CALL `get_user_by_id`(1, \'active\')');
    }

    public function testMysqlUpdateWithLimit(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->update('users')->updateRow(['status' => 'inactive'])
            ->where()->eq('active', 0)->end()
            ->limit(10)
            ->build(true)
        ;

        Assert::same($sql, 'UPDATE `users` SET `status` = \'inactive\' WHERE (`active` = 0) LIMIT 10');
    }

    public function testMysqlDeleteWithLimit(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->delete('users')
            ->where()->eq('status', 'banned')->end()
            ->limit(5)
            ->build(true)
        ;

        Assert::same($sql, 'DELETE FROM `users` WHERE (`status` = \'banned\') LIMIT 5');
    }

    public function testMysqlInsertMultipleRows(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->insert('users')
            ->insertRow(['name' => 'John', 'age' => 30])
            ->insertRow(['name' => 'Jane', 'age' => 25])
            ->insertRow(['name' => 'Bob', 'age' => 35])
            ->build(true)
        ;

        $expected = 'INSERT INTO `users` (`name`, `age`) '
            . 'VALUES (\'John\', 30), (\'Jane\', 25), (\'Bob\', 35)';

        Assert::same($sql, $expected);
    }

    public function testMysqlEscapeLikePattern(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()->like('name', '50%', QbConsts::LIKE_RIGHT)->end()
            ->build(true)
        ;

        Assert::same($sql, "SELECT * FROM `users` WHERE (`name` LIKE '50!%%' ESCAPE '!')");
    }

    /**
     * @param non-empty-string $value
     */
    #[DataProvider('provideMysqlEscapeValueCases')]
    public function testMysqlEscapeValue(string $value, string $expected): void
    {
        Assert::same((new MysqlDriver())->quoteValue($value), $expected);
    }

    /**
     * @return iterable<string, array{non-empty-string, string}>
     */
    public static function provideMysqlEscapeValueCases(): iterable
    {
        yield 'single quote is doubled' => ["O'Neil", "'O''Neil'"];

        yield 'backslash is doubled' => ['C:\dir\\', "'C:\\\\dir\\\\'"];

        yield 'backslash before quote' => ["a\\'b", "'a\\\\''b'"];

        yield 'double quote as is' => ['say "hi"', "'say \"hi\"'"];

        yield 'control characters as is' => ["a\nb\rc\0d\x1ae", "'a\nb\rc\0d\x1ae'"];
    }

    public function testMysqlQuoteCannotBeClosedByBackslashPayload(): void
    {
        $sql = $this->getQueryBuilder()->select('name')->from('t')
            ->where()->eq('name', "a\\') OR 1=1 -- ")->end()
            ->build(true)
        ;

        Assert::same($sql, "SELECT `name` FROM `t` WHERE (`name` = 'a\\\\'') OR 1=1 -- ')");
    }

    public function testMysqlComplexJoin(): void
    {
        $qb = $this->getQueryBuilder();

        $joinCondition1 = new ConditionJoin($qb);
        $joinCondition1->eqField('o', 'user_id', 'u', 'id');

        $joinCondition2 = new ConditionJoin($qb);
        $joinCondition2->eqField('p', 'id', 'o', 'product_id');

        $orderBy = ConditionBy::orderBy()->desc('total', 'o');

        $sql = $qb->select(
            Field::set('id', 'u'),
            Field::set('name', 'u'),
            Field::set('total', 'o'),
            Field::set('title', 'p')
        )
            ->from('users', 'u')
            ->leftJoin('orders', 'o', $joinCondition1)
            ->leftJoin('products', 'p', $joinCondition2)
            ->where()->gte(Field::set('total', 'o'), 100)->end()
            ->orderBy($orderBy)
            ->limit(20)
            ->build(true)
        ;

        $expected = 'SELECT `u`.`id`, `u`.`name`, `o`.`total`, `p`.`title` '
            . 'FROM `users` AS `u` '
            . 'LEFT JOIN `orders` AS `o` ON (`o`.`user_id` = `u`.`id`) '
            . 'LEFT JOIN `products` AS `p` ON (`p`.`id` = `o`.`product_id`) '
            . 'WHERE (`o`.`total` >= 100) '
            . 'ORDER BY `o`.`total` DESC '
            . 'LIMIT 20';

        Assert::same($sql, $expected);
    }

    public function testMysqlSubqueryInSelect(): void
    {
        $qb = $this->getQueryBuilder();

        $subquery = $qb->subQuery()
            ->select(Field::set('COUNT(*)'))
            ->from('orders')
            ->where()->eqField('orders', 'user_id', 'users', 'id')->end()
        ;

        $sql = $qb->select('id', 'name', Field::subquery($subquery, 'order_count'))
            ->from('users')
            ->build(true)
        ;

        $expected = 'SELECT `id`, `name`, '
            . '(SELECT COUNT(*) FROM `orders` WHERE (`orders`.`user_id` = `users`.`id`)) '
            . 'AS `order_count` '
            . 'FROM `users`';

        Assert::same($sql, $expected);
    }

    public function testMysqlDistinct(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('user_id')->distinct()->from('orders')->build(true);

        Assert::same($sql, 'SELECT DISTINCT `user_id` FROM `orders`');
    }

    public function testMysqlDistinctMultipleFields(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('user_id', 'status')->distinct()->from('orders')->build(true);

        Assert::same($sql, 'SELECT DISTINCT `user_id`, `status` FROM `orders`');
    }

    public function testMysqlDistinctWithWhere(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('user_id')->distinct()->from('orders')
            ->where()->eq('status', 'completed')->end()
            ->build(true)
        ;

        Assert::same($sql, 'SELECT DISTINCT `user_id` FROM `orders` WHERE (`status` = \'completed\')');
    }

    public function testMysqlDistinctWithOrderBy(): void
    {
        $qb = $this->getQueryBuilder();
        $orderBy = ConditionBy::orderBy()->asc('category');
        $sql = $qb->select('category')->distinct()->from('products')
            ->orderBy($orderBy)
            ->build(true)
        ;

        Assert::same($sql, 'SELECT DISTINCT `category` FROM `products` ORDER BY `category` ASC');
    }

    public function testMysqlIgnoresFinalModifier(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select()->from('users')->final()->build(true);

        Assert::same($sql, 'SELECT * FROM `users`');
    }

    private function getQueryBuilder(): QueryBuilder
    {
        $qb = new QueryBuilder();
        $qb->setDriver(QbConsts::DRIVER_PDO_MYSQL);

        return $qb;
    }
}
