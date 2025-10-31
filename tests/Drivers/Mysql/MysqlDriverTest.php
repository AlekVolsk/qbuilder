<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use PHPUnit\Framework\TestCase;
use QBuilder\Condition\ConditionBy;
use QBuilder\Condition\ConditionJoin;
use QBuilder\Condition\Field;
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;

/**
 * @internal
 *
 * @coversNothing
 */
final class MysqlDriverTest extends TestCase
{
    public function testMysqlQuoting(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('id', 'name')->from('users')->build(true);

        self::assertSame('SELECT `id`, `name` FROM `users`', $sql);
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
            .'VALUES (\'test@example.com\', \'John\') '
            .'ON DUPLICATE KEY UPDATE `name` = \'John Updated\', `updated_at` = NOW()';

        self::assertSame($expected, $sql);
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
            .'ON DUPLICATE KEY UPDATE `counter` = VALUES(counter)';

        self::assertSame($expected, $sql);
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
            .'ON DUPLICATE KEY UPDATE `views` = `views` + 1';

        self::assertSame($expected, $sql);
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
            .'ON DUPLICATE KEY UPDATE `stock` = `stock` - 1';

        self::assertSame($expected, $sql);
    }

    public function testMysqlLimit(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('*')->from('users')->limit(10)->build(true);

        self::assertSame('SELECT * FROM `users` LIMIT 10', $sql);
    }

    public function testMysqlLimitOffset(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('*')->from('users')->limit(10, 20)->build(true);

        self::assertSame('SELECT * FROM `users` LIMIT 20, 10', $sql);
    }

    public function testMysqlProcedureNoParams(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->procedure('get_all_users')->build(true);

        self::assertSame('CALL `get_all_users`()', $sql);
    }

    public function testMysqlProcedureWithParams(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->procedure('get_user_by_id', [1, 'active'])->build(true);

        self::assertSame('CALL `get_user_by_id`(1, \'active\')', $sql);
    }

    public function testMysqlUpdateWithLimit(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->update('users')->updateRow(['status' => 'inactive'])
            ->where()->eq('active', 0)->end()
            ->limit(10)
            ->build(true)
        ;

        self::assertSame('UPDATE `users` SET `status` = \'inactive\' WHERE (`active` = 0) LIMIT 10', $sql);
    }

    public function testMysqlDeleteWithLimit(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->delete('users')
            ->where()->eq('status', 'banned')->end()
            ->limit(5)
            ->build(true)
        ;

        self::assertSame('DELETE FROM `users` WHERE (`status` = \'banned\') LIMIT 5', $sql);
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
            .'VALUES (\'John\', 30), (\'Jane\', 25), (\'Bob\', 35)';

        self::assertSame($expected, $sql);
    }

    public function testMysqlEscapeLikePattern(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()->like('name', '50%', QbConsts::LIKE_RIGHT)->end()
            ->build(true)
        ;

        self::assertSame('SELECT * FROM `users` WHERE (`name` LIKE \'50\\\%\')', $sql);
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
            .'FROM `users` AS `u` '
            .'LEFT JOIN `orders` AS `o` ON (`o`.`user_id` = `u`.`id`) '
            .'LEFT JOIN `products` AS `p` ON (`p`.`id` = `o`.`product_id`) '
            .'WHERE (`o`.`total` >= 100) '
            .'ORDER BY `o`.`total` DESC '
            .'LIMIT 20';

        self::assertSame($expected, $sql);
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
            .'(SELECT COUNT(*) FROM `orders` WHERE (`orders`.`user_id` = `users`.`id`)) '
            .'AS `order_count` '
            .'FROM `users`';

        self::assertSame($expected, $sql);
    }

    public function testMysqlDistinct(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('user_id')->distinct()->from('orders')->build(true);

        self::assertSame('SELECT DISTINCT `user_id` FROM `orders`', $sql);
    }

    public function testMysqlDistinctMultipleFields(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('user_id', 'status')->distinct()->from('orders')->build(true);

        self::assertSame('SELECT DISTINCT `user_id`, `status` FROM `orders`', $sql);
    }

    public function testMysqlDistinctWithWhere(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('user_id')->distinct()->from('orders')
            ->where()->eq('status', 'completed')->end()
            ->build(true)
        ;

        self::assertSame('SELECT DISTINCT `user_id` FROM `orders` WHERE (`status` = \'completed\')', $sql);
    }

    public function testMysqlDistinctWithOrderBy(): void
    {
        $qb = $this->getQueryBuilder();
        $orderBy = ConditionBy::orderBy()->asc('category');
        $sql = $qb->select('category')->distinct()->from('products')
            ->orderBy($orderBy)
            ->build(true)
        ;

        self::assertSame('SELECT DISTINCT `category` FROM `products` ORDER BY `category` ASC', $sql);
    }

    public function testMysqlIgnoresFinalModifier(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select()->from('users')->final()->build(true);

        self::assertSame('SELECT * FROM `users`', $sql);
    }

    private function getQueryBuilder(): QueryBuilder
    {
        $qb = new QueryBuilder();
        $qb->setDriver(QbConsts::DRIVER_PDO_MYSQL);

        return $qb;
    }
}
