<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use QBuilder\Condition\Field;
use QBuilder\Exceptions\InvalidQueryException;
use QBuilder\QueryBuilder;
use Testo\Assert;
use Testo\Expect;
use Testo\Test;

/**
 * @internal
 */
#[Test]
final class FieldAdvancedTest
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

        Assert::true($field->isSubquery());
        Assert::same($field->getFieldAlias(), 'order_count');
        Assert::true($field->isExpression());
        Assert::same($field->getSubquery(), $subquery);
    }

    public function testFieldSubqueryWithoutAlias(): void
    {
        $qb = new QueryBuilder();
        $subquery = $qb->subQuery()
            ->select('id')
            ->from('temp')
        ;

        $field = Field::subquery($subquery);

        Assert::true($field->isSubquery());
        Assert::same($field->getFieldAlias(), '');
        Assert::same($field->getSubquery(), $subquery);
    }

    public function testFieldSubqueryThrowsExceptionForNonSelectQuery(): void
    {
        $qb = new QueryBuilder();
        $qb->insert('users')->insertRow(['name' => 'Test']);

        Expect::exception(InvalidQueryException::class)->withMessageContaining('Subquery must be a SELECT statement');

        Field::subquery($qb, 'alias');
    }

    public function testIsSubqueryReturnsFalseForRegularField(): void
    {
        $field = Field::set('name');

        Assert::false($field->isSubquery());
        Assert::null($field->getSubquery());
    }

    public function testIsSubqueryReturnsTrueForSubqueryField(): void
    {
        $qb = new QueryBuilder();
        $subquery = $qb->subQuery()
            ->select('id')
            ->from('temp')
        ;

        $field = Field::subquery($subquery);

        Assert::true($field->isSubquery());
    }

    public function testGetSubqueryReturnsQueryBuilderInstance(): void
    {
        $qb = new QueryBuilder();
        $subquery = $qb->subQuery()
            ->select('id')
            ->from('temp')
        ;

        $field = Field::subquery($subquery);

        Assert::instanceOf($field->getSubquery(), QueryBuilder::class);
        Assert::same($field->getSubquery(), $subquery);
    }

    public function testGetSubqueryReturnsNullForRegularField(): void
    {
        $field = Field::set('name');

        Assert::null($field->getSubquery());
    }

    public function testSetFieldAliasMethod(): void
    {
        $field = Field::set('name');
        Assert::same($field->getFieldAlias(), '');

        $field->setFieldAlias('user_name');
        Assert::same($field->getFieldAlias(), 'user_name');
    }

    public function testSetFieldAliasReturnsSelfForChaining(): void
    {
        $field = Field::set('name');
        $result = $field->setFieldAlias('alias');

        Assert::same($result, $field);
    }
}
