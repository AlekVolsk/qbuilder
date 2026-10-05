<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use QBuilder\Builder\RecursiveCteBuilder;
use QBuilder\Condition\ConditionBy;
use QBuilder\Condition\ConditionJoin;
use QBuilder\Condition\Field;
use QBuilder\Exceptions\InvalidQueryException;
use QBuilder\Exceptions\MissingRequirementException;
use QBuilder\QueryBuilder;
use Testo\Assert;
use Testo\Expect;
use Testo\Test;

/**
 * @internal
 */
#[Test]
final class RecursiveCteBuilderTest
{
    public function testRecursiveCteSimple(): void
    {
        $qb = new QueryBuilder();

        $baseQuery = $qb->subQuery()
            ->select('id', 'name', 'parent_id')
            ->from('categories')
            ->where()->isNull('parent_id')->end()
        ;

        $joinCondition = new ConditionJoin($qb);
        $joinCondition->eqField('c', 'parent_id', 'ct', 'id');

        $recursiveQuery = $qb->subQuery()
            ->select('id', 'name', 'parent_id')
            ->from('categories', 'c')
            ->innerJoin('category_tree', 'ct', $joinCondition)
        ;

        $finalQuery = $qb->subQuery()->select('*')->from('category_tree');

        $cte = new RecursiveCteBuilder($qb, 'category_tree');
        $sql = $cte->baseQuery($baseQuery)
            ->recursiveQuery($recursiveQuery)
            ->finalSelect($finalQuery)
            ->build(true)
        ;

        $expected = 'WITH RECURSIVE `category_tree` AS ( '
            . 'SELECT `id`, `name`, `parent_id` FROM `categories` WHERE (`parent_id` IS NULL) '
            . 'UNION ALL '
            . 'SELECT `c`.`id`, `c`.`name`, `c`.`parent_id` FROM `categories` AS `c` '
            . 'INNER JOIN `category_tree` AS `ct` ON (`c`.`parent_id` = `ct`.`id`) '
            . ') SELECT * FROM `category_tree`';

        Assert::same($sql, $expected);
    }

    public function testRecursiveCteWithPath(): void
    {
        $qb = new QueryBuilder();

        $baseQuery = $qb->subQuery()
            ->select('id', 'name', 'parent_id', Field::set('name', '', 'path'))
            ->from('categories')
            ->where()->isNull('parent_id')->end()
        ;

        $joinCondition = new ConditionJoin($qb);
        $joinCondition->eqField('c', 'parent_id', 'ct', 'id');

        $orderBy = ConditionBy::orderBy()->asc('name', 'c');

        $recursiveQuery = $qb->subQuery()
            ->select(
                Field::set('id', 'c'),
                Field::set('name', 'c'),
                Field::set('parent_id', 'c'),
                Field::set("CONCAT(ct.path, ' > ', c.name)", '', 'path')
            )
            ->from('categories', 'c')
            ->innerJoin('category_tree', 'ct', $joinCondition)
            ->orderBy($orderBy)
        ;

        $finalOrderBy = ConditionBy::orderBy()->asc('path');

        $finalQuery = $qb->subQuery()
            ->select('id', 'name', 'path')
            ->from('category_tree')
            ->orderBy($finalOrderBy)
        ;

        $cte = new RecursiveCteBuilder($qb, 'category_tree');
        $sql = $cte->baseQuery($baseQuery)
            ->recursiveQuery($recursiveQuery)
            ->finalSelect($finalQuery)
            ->build(true)
        ;

        $expected = 'WITH RECURSIVE `category_tree` AS ( '
            . 'SELECT `id`, `name`, `parent_id`, `name` AS `path` FROM `categories` '
            . 'WHERE (`parent_id` IS NULL) '
            . 'UNION ALL '
            . 'SELECT `c`.`id`, `c`.`name`, `c`.`parent_id`, '
            . "CONCAT(`ct`.`path`, ' > ', `c`.`name`) AS `path` "
            . 'FROM `categories` AS `c` '
            . 'INNER JOIN `category_tree` AS `ct` ON (`c`.`parent_id` = `ct`.`id`) '
            . 'ORDER BY `c`.`name` ASC '
            . ') SELECT `id`, `name`, `path` FROM `category_tree` ORDER BY `path` ASC';

        Assert::same($sql, $expected);
    }

    public function testCreateStaticMethod(): void
    {
        $qb = new QueryBuilder();
        $cte = RecursiveCteBuilder::create($qb, 'TestCTE');

        Assert::instanceOf($cte, RecursiveCteBuilder::class);
        Assert::same($cte->getCteName(), 'TestCTE');
    }

    public function testGetCteNameReturnsCteName(): void
    {
        $qb = new QueryBuilder();
        $cte = new RecursiveCteBuilder($qb, 'CategoryTree');

        Assert::same($cte->getCteName(), 'CategoryTree');
    }

    public function testBaseQueryThrowsExceptionForNonSelectQuery(): void
    {
        $qb = new QueryBuilder();
        $cte = new RecursiveCteBuilder($qb);

        Expect::exception(InvalidQueryException::class)->withMessageContaining('Base query must be a SELECT statement');

        $insertQuery = $qb->insert('categories')->insertRow(['name' => 'Test']);
        $cte->baseQuery($insertQuery);
    }

    public function testRecursiveQueryThrowsExceptionForNonSelectQuery(): void
    {
        $qb = new QueryBuilder();
        $cte = new RecursiveCteBuilder($qb);

        Expect::exception(InvalidQueryException::class)
            ->withMessageContaining('Recursive query must be a SELECT statement')
        ;

        $updateQuery = $qb->update('categories')->updateRow(['name' => 'Test']);
        $cte->recursiveQuery($updateQuery);
    }

    public function testFinalSelectThrowsExceptionForNonSelectQuery(): void
    {
        $qb = new QueryBuilder();
        $cte = new RecursiveCteBuilder($qb);

        Expect::exception(InvalidQueryException::class)
            ->withMessageContaining('Final query must be a SELECT statement')
        ;

        $deleteQuery = $qb->delete('categories');
        $cte->finalSelect($deleteQuery);
    }

    public function testBuildThrowsExceptionWhenBaseQueryIsMissing(): void
    {
        $qb = new QueryBuilder();
        $cte = new RecursiveCteBuilder($qb);

        $recursiveQuery = $qb->subQuery()->select('*')->from('categories');
        $finalQuery = $qb->subQuery()->select('*')->from('category_tree');

        $cte->recursiveQuery($recursiveQuery)->finalSelect($finalQuery);

        Expect::exception(MissingRequirementException::class)
            ->withMessageContaining('Base query is required for recursive CTE')
        ;

        $cte->build();
    }

    public function testBuildThrowsExceptionWhenRecursiveQueryIsMissing(): void
    {
        $qb = new QueryBuilder();
        $cte = new RecursiveCteBuilder($qb);

        $baseQuery = $qb->subQuery()->select('*')->from('categories');
        $finalQuery = $qb->subQuery()->select('*')->from('category_tree');

        $cte->baseQuery($baseQuery)->finalSelect($finalQuery);

        Expect::exception(MissingRequirementException::class)
            ->withMessageContaining('Recursive query is required for recursive CTE')
        ;

        $cte->build();
    }

    public function testBuildThrowsExceptionWhenFinalSelectIsMissing(): void
    {
        $qb = new QueryBuilder();
        $cte = new RecursiveCteBuilder($qb);

        $baseQuery = $qb->subQuery()->select('*')->from('categories');
        $recursiveQuery = $qb->subQuery()->select('*')->from('categories', 'c');

        $cte->baseQuery($baseQuery)->recursiveQuery($recursiveQuery);

        Expect::exception(MissingRequirementException::class)
            ->withMessageContaining('Final SELECT query is required for recursive CTE')
        ;

        $cte->build();
    }

    public function testBuildWithCompactFlag(): void
    {
        $qb = new QueryBuilder();

        $baseQuery = $qb->subQuery()
            ->select('id', 'name')
            ->from('categories')
            ->where()->isNull('parent_id')->end()
        ;

        $recursiveQuery = $qb->subQuery()
            ->select('id', 'name')
            ->from('categories', 'c')
        ;

        $finalQuery = $qb->subQuery()->select('*')->from('category_tree');

        $cte = new RecursiveCteBuilder($qb, 'category_tree');
        $sql = $cte->baseQuery($baseQuery)
            ->recursiveQuery($recursiveQuery)
            ->finalSelect($finalQuery)
            ->build(true)
        ;

        Assert::same(
            $sql,
            'WITH RECURSIVE `category_tree` AS ( SELECT `id`, `name` FROM `categories` WHERE '
                . '(`parent_id` IS NULL) UNION ALL SELECT `c`.`id`, `c`.`name` FROM `categories` AS `c` ) '
                . 'SELECT * FROM `category_tree`'
        );
    }

    public function testConstructorValidatesCteName(): void
    {
        $qb = new QueryBuilder();
        $cte = new RecursiveCteBuilder($qb, 'valid_cte_name');

        Assert::same($cte->getCteName(), 'valid_cte_name');
    }
}
