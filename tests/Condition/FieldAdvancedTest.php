<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use PHPUnit\Framework\TestCase;
use QBuilder\Condition\Field;
use QBuilder\Exceptions\InvalidQueryException;
use QBuilder\QueryBuilder;

/**
 * @internal
 *
 * @coversNothing
 */
final class FieldAdvancedTest extends TestCase
{
    public function testFieldSubqueryCreatesSubqueryField(): void
    {
        $qb = new QueryBuilder();
        $subquery = $qb->subQuery()
            ->select(Field::set('COUNT(*)'))
            ->from('orders')
            ->where()
            ->eq('user_id', 1)
            ->end()
        ;

        $field = Field::subquery($subquery, 'order_count');

        self::assertTrue($field->isSubquery());
        self::assertSame('order_count', $field->getFieldAlias());
        self::assertTrue($field->isExpression());
        self::assertSame($subquery, $field->getSubquery());
    }

    public function testFieldSubqueryWithoutAlias(): void
    {
        $qb = new QueryBuilder();
        $subquery = $qb->subQuery()
            ->select('id')
            ->from('temp')
        ;

        $field = Field::subquery($subquery);

        self::assertTrue($field->isSubquery());
        self::assertSame('', $field->getFieldAlias());
        self::assertSame($subquery, $field->getSubquery());
    }

    public function testFieldSubqueryThrowsExceptionForNonSelectQuery(): void
    {
        $qb = new QueryBuilder();
        $qb->insert('users')->insertRow(['name' => 'Test']);

        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('Subquery must be a SELECT statement');

        Field::subquery($qb, 'alias');
    }

    public function testIsSubqueryReturnsFalseForRegularField(): void
    {
        $field = Field::set('name');

        self::assertFalse($field->isSubquery());
        self::assertNull($field->getSubquery());
    }

    public function testIsSubqueryReturnsTrueForSubqueryField(): void
    {
        $qb = new QueryBuilder();
        $subquery = $qb->subQuery()
            ->select('id')
            ->from('temp')
        ;

        $field = Field::subquery($subquery);

        self::assertTrue($field->isSubquery());
    }

    public function testGetSubqueryReturnsQueryBuilderInstance(): void
    {
        $qb = new QueryBuilder();
        $subquery = $qb->subQuery()
            ->select('id')
            ->from('temp')
        ;

        $field = Field::subquery($subquery);

        self::assertInstanceOf(QueryBuilder::class, $field->getSubquery());
        self::assertSame($subquery, $field->getSubquery());
    }

    public function testGetSubqueryReturnsNullForRegularField(): void
    {
        $field = Field::set('name');

        self::assertNull($field->getSubquery());
    }

    public function testSetFieldAliasMethod(): void
    {
        $field = Field::set('name');
        self::assertSame('', $field->getFieldAlias());

        $field->setFieldAlias('user_name');
        self::assertSame('user_name', $field->getFieldAlias());
    }

    public function testSetFieldAliasReturnsSelfForChaining(): void
    {
        $field = Field::set('name');
        $result = $field->setFieldAlias('alias');

        self::assertSame($field, $result);
    }
}
