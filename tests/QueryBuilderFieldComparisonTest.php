<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use QBuilder\Condition\ConditionJoin;
use QBuilder\Condition\Field;
use QBuilder\QueryBuilder;
use Testo\Assert;
use Testo\Test;

/**
 * @internal
 */
#[Test]
final class QueryBuilderFieldComparisonTest
{
    public function testEqField(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users', 'u')
            ->where()->eqField('u', 'department_id', 'd', 'id')->end()
            ->build(true)
        ;

        Assert::same($sql, 'SELECT `u`.* FROM `users` AS `u` WHERE (`u`.`department_id` = `d`.`id`)');
    }

    public function testNeqField(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()->neqField('', 'created_by', '', 'id')->end()
            ->build(true)
        ;

        Assert::same($sql, 'SELECT * FROM `users` WHERE (`created_by` != `id`)');
    }

    public function testGtField(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('employees')
            ->where()->gtField('', 'salary', '', 'min_salary')->end()
            ->build(true)
        ;

        Assert::same($sql, 'SELECT * FROM `employees` WHERE (`salary` > `min_salary`)');
    }

    public function testGteField(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('employees')
            ->where()->gteField('', 'salary', '', 'min_salary')->end()
            ->build(true)
        ;

        Assert::same($sql, 'SELECT * FROM `employees` WHERE (`salary` >= `min_salary`)');
    }

    public function testLtField(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('products')
            ->where()->ltField('', 'price', '', 'max_price')->end()
            ->build(true)
        ;

        Assert::same($sql, 'SELECT * FROM `products` WHERE (`price` < `max_price`)');
    }

    public function testLteField(): void
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from('orders')
            ->where()->lteField('', 'discount', '', 'max_discount')->end()
            ->build(true)
        ;

        Assert::same($sql, 'SELECT * FROM `orders` WHERE (`discount` <= `max_discount`)');
    }

    public function testFieldComparisonInJoin(): void
    {
        $qb = new QueryBuilder();
        $joinCondition = new ConditionJoin($qb);
        $joinCondition->eqField('e', 'department_id', 'd', 'id');
        $joinCondition->and()->gteField('e', 'salary', 'd', 'min_salary');

        $sql = $qb->select('*')
            ->from('employees', 'e')
            ->leftJoin('departments', 'd', $joinCondition)
            ->build(true)
        ;

        $expected = 'SELECT `e`.* FROM `employees` AS `e` '
            . 'LEFT JOIN `departments` AS `d` '
            . 'ON (`e`.`department_id` = `d`.`id`) AND (`e`.`salary` >= `d`.`min_salary`)';

        Assert::same($sql, $expected);
    }

    public function testFieldComparisonInCorrelatedSubquery(): void
    {
        $qb = new QueryBuilder();

        $subquery = $qb->subQuery()
            ->select(Field::set('COUNT(*)'))
            ->from('orders', 'o')
            ->where()
            ->eqField('o', 'user_id', 'u', 'id')
            ->and()->gtField('o', 'total', 'u', 'credit_limit')
            ->end()
        ;

        $sql = $qb->select('id', 'name', Field::subquery($subquery, 'high_orders'))
            ->from('users', 'u')
            ->build(true)
        ;

        $expected = 'SELECT `u`.`id`, `u`.`name`, '
            . '(SELECT COUNT(*) FROM `orders` AS `o` '
            . 'WHERE (`o`.`user_id` = `u`.`id`) AND (`o`.`total` > `u`.`credit_limit`)) '
            . 'AS `high_orders` '
            . 'FROM `users` AS `u`';

        Assert::same($sql, $expected);
    }

    public function testJoinNeqField(): void
    {
        $qb = new QueryBuilder();
        $joinCondition = new ConditionJoin($qb);
        $joinCondition->eqField('o', 'user_id', 'u', 'id');
        $joinCondition->and()->neqField('o', 'status', 'u', 'default_status');

        $sql = $qb->select('*')
            ->from('orders', 'o')
            ->leftJoin('users', 'u', $joinCondition)
            ->build(true)
        ;

        $expected = 'SELECT `o`.* FROM `orders` AS `o` '
            . 'LEFT JOIN `users` AS `u` '
            . 'ON (`o`.`user_id` = `u`.`id`) AND (`o`.`status` != `u`.`default_status`)';

        Assert::same($sql, $expected);
    }

    public function testJoinGtField(): void
    {
        $qb = new QueryBuilder();
        $joinCondition = new ConditionJoin($qb);
        $joinCondition->eqField('o', 'product_id', 'p', 'id');
        $joinCondition->and()->gtField('o', 'quantity', 'p', 'min_order_qty');

        $sql = $qb->select('*')
            ->from('orders', 'o')
            ->innerJoin('products', 'p', $joinCondition)
            ->build(true)
        ;

        $expected = 'SELECT `o`.* FROM `orders` AS `o` '
            . 'INNER JOIN `products` AS `p` '
            . 'ON (`o`.`product_id` = `p`.`id`) AND (`o`.`quantity` > `p`.`min_order_qty`)';

        Assert::same($sql, $expected);
    }

    public function testJoinLtField(): void
    {
        $qb = new QueryBuilder();
        $joinCondition = new ConditionJoin($qb);
        $joinCondition->eqField('o', 'product_id', 'p', 'id');
        $joinCondition->and()->ltField('o', 'price', 'p', 'max_price');

        $sql = $qb->select('*')
            ->from('orders', 'o')
            ->innerJoin('products', 'p', $joinCondition)
            ->build(true)
        ;

        $expected = 'SELECT `o`.* FROM `orders` AS `o` '
            . 'INNER JOIN `products` AS `p` '
            . 'ON (`o`.`product_id` = `p`.`id`) AND (`o`.`price` < `p`.`max_price`)';

        Assert::same($sql, $expected);
    }

    public function testJoinLteField(): void
    {
        $qb = new QueryBuilder();
        $joinCondition = new ConditionJoin($qb);
        $joinCondition->eqField('o', 'user_id', 'u', 'id');
        $joinCondition->and()->lteField('o', 'discount', 'u', 'max_discount');

        $sql = $qb->select('*')
            ->from('orders', 'o')
            ->leftJoin('users', 'u', $joinCondition)
            ->build(true)
        ;

        $expected = 'SELECT `o`.* FROM `orders` AS `o` '
            . 'LEFT JOIN `users` AS `u` '
            . 'ON (`o`.`user_id` = `u`.`id`) AND (`o`.`discount` <= `u`.`max_discount`)';

        Assert::same($sql, $expected);
    }

    public function testJoinMultipleFieldComparisons(): void
    {
        $qb = new QueryBuilder();
        $joinCondition = new ConditionJoin($qb);
        $joinCondition->eqField('o', 'user_id', 'u', 'id');
        $joinCondition->and()->gteField('o', 'created_at', 'u', 'last_login');
        $joinCondition->and()->ltField('o', 'total', 'u', 'credit_limit');

        $sql = $qb->select('*')
            ->from('orders', 'o')
            ->leftJoin('users', 'u', $joinCondition)
            ->build(true)
        ;

        $expected = 'SELECT `o`.* FROM `orders` AS `o` '
            . 'LEFT JOIN `users` AS `u` '
            . 'ON (`o`.`user_id` = `u`.`id`) AND (`o`.`created_at` >= `u`.`last_login`) '
            . 'AND (`o`.`total` < `u`.`credit_limit`)';

        Assert::same($sql, $expected);
    }

    public function testEqFieldWithFieldObjects(): void
    {
        $qb = new QueryBuilder();
        $field1 = Field::set('department_id', 'u');
        $field2 = Field::set('id', 'd');

        $sql = $qb->select('*')
            ->from('users', 'u')
            ->where()->eqField('', $field1, '', $field2)->end()
            ->build(true)
        ;

        Assert::same($sql, 'SELECT `u`.* FROM `users` AS `u` WHERE (`u`.`department_id` = `d`.`id`)');
    }

    public function testNeqFieldWithFieldObjects(): void
    {
        $qb = new QueryBuilder();
        $field1 = Field::set('created_by');
        $field2 = Field::set('id');

        $sql = $qb->select('*')
            ->from('users')
            ->where()->neqField('', $field1, '', $field2)->end()
            ->build(true)
        ;

        Assert::same($sql, 'SELECT * FROM `users` WHERE (`created_by` != `id`)');
    }

    public function testGtFieldWithFieldObjects(): void
    {
        $qb = new QueryBuilder();
        $field1 = Field::set('salary', 'e');
        $field2 = Field::set('min_salary', 'd');

        $sql = $qb->select('*')
            ->from('employees', 'e')
            ->where()->gtField('', $field1, '', $field2)->end()
            ->build(true)
        ;

        Assert::same($sql, 'SELECT `e`.* FROM `employees` AS `e` WHERE (`e`.`salary` > `d`.`min_salary`)');
    }

    public function testGteFieldWithFieldObjects(): void
    {
        $qb = new QueryBuilder();
        $field1 = Field::set('salary', 'e');
        $field2 = Field::set('min_salary', 'd');

        $sql = $qb->select('*')
            ->from('employees', 'e')
            ->where()->gteField('', $field1, '', $field2)->end()
            ->build(true)
        ;

        Assert::same($sql, 'SELECT `e`.* FROM `employees` AS `e` WHERE (`e`.`salary` >= `d`.`min_salary`)');
    }

    public function testLtFieldWithFieldObjects(): void
    {
        $qb = new QueryBuilder();
        $field1 = Field::set('price', 'p');
        $field2 = Field::set('max_price', 'p');

        $sql = $qb->select('*')
            ->from('products', 'p')
            ->where()->ltField('', $field1, '', $field2)->end()
            ->build(true)
        ;

        Assert::same($sql, 'SELECT `p`.* FROM `products` AS `p` WHERE (`p`.`price` < `p`.`max_price`)');
    }

    public function testLteFieldWithFieldObjects(): void
    {
        $qb = new QueryBuilder();
        $field1 = Field::set('discount', 'o');
        $field2 = Field::set('max_discount', 'u');

        $sql = $qb->select('*')
            ->from('orders', 'o')
            ->where()->lteField('', $field1, '', $field2)->end()
            ->build(true)
        ;

        Assert::same($sql, 'SELECT `o`.* FROM `orders` AS `o` WHERE (`o`.`discount` <= `u`.`max_discount`)');
    }

    public function testFieldComparisonWithMixedFieldAndString(): void
    {
        $qb = new QueryBuilder();
        $field1 = Field::set('price', 'products');

        $sql = $qb->select('*')
            ->from('products')
            ->where()->eqField('', $field1, 'products', 'cost')->end()
            ->build(true)
        ;

        Assert::same($sql, 'SELECT * FROM `products` WHERE (`products`.`price` = `products`.`cost`)');
    }

    public function testFieldComparisonWithStringAndFieldObject(): void
    {
        $qb = new QueryBuilder();
        $field2 = Field::set('cost', 'products');

        $sql = $qb->select('*')
            ->from('products')
            ->where()->eqField('products', 'price', '', $field2)->end()
            ->build(true)
        ;

        Assert::same($sql, 'SELECT * FROM `products` WHERE (`products`.`price` = `products`.`cost`)');
    }
}
