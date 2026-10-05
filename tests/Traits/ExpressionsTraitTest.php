<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use QBuilder\Exceptions\InvalidIdentifierException;
use QBuilder\QueryBuilder;
use Testo\Assert;
use Testo\Expect;
use Testo\Test;

/**
 * @internal
 */
#[Test]
final class ExpressionsTraitTest
{
    public function testExpressionWithValidExpression(): void
    {
        $qb = new QueryBuilder();
        $builder = $qb->conflictBuilder();

        $builder->expression('price', 'price * 1.1');
        $result = $builder->build();

        Assert::same($result, '`price` = price * 1.1');
    }

    public function testExpressionWithArithmeticExpression(): void
    {
        $qb = new QueryBuilder();
        $builder = $qb->conflictBuilder();

        $builder->expression('count', 'count + 1');
        $result = $builder->build();

        Assert::same($result, '`count` = count + 1');
    }

    public function testExpressionWithParentheses(): void
    {
        $qb = new QueryBuilder();
        $builder = $qb->conflictBuilder();

        $builder->expression('total', '(price * quantity) + tax');
        $result = $builder->build();

        Assert::same($result, '`total` = (price * quantity) + tax');
    }

    public function testExpressionWithInvalidCharactersThrowsException(): void
    {
        $qb = new QueryBuilder();
        $builder = $qb->conflictBuilder();

        Expect::exception(InvalidIdentifierException::class)->withMessageContaining('Invalid expression');

        $builder->expression('price', 'price * 1.1; DROP TABLE users');
    }

    public function testExpressionWithDangerousPatternsThrowsException(): void
    {
        $qb = new QueryBuilder();
        $builder = $qb->conflictBuilder();

        Expect::exception(InvalidIdentifierException::class)->withMessageContaining('dangerous SQL patterns');

        $builder->expression('price', 'price DROP TABLE users');
    }

    public function testExpressionWithDropTableThrowsException(): void
    {
        $qb = new QueryBuilder();
        $builder = $qb->conflictBuilder();

        Expect::exception(InvalidIdentifierException::class)->withMessageContaining('dangerous SQL patterns');

        $builder->expression('price', 'price DROP TABLE users');
    }

    public function testCaseExpressionWithValidCase(): void
    {
        $qb = new QueryBuilder();
        $builder = $qb->conflictBuilder();

        $builder->caseExpression('status', [
            'WHEN count > 10 THEN "active"',
            'ELSE "pending"',
        ]);
        $result = $builder->build();

        Assert::same($result, '`status` = CASE WHEN count > 10 THEN "active" ELSE "pending" END');
    }

    public function testCaseExpressionWithMultipleWhenClauses(): void
    {
        $qb = new QueryBuilder();
        $builder = $qb->conflictBuilder();

        $builder->caseExpression('discount', [
            'WHEN total > 1000 THEN 0.1',
            'WHEN total > 500 THEN 0.05',
            'ELSE 0',
        ]);
        $result = $builder->build();

        Assert::same($result, '`discount` = CASE WHEN total > 1000 THEN 0.1 WHEN total > 500 THEN 0.05 ELSE 0 END');
    }

    public function testCaseExpressionWithInvalidFormatThrowsException(): void
    {
        $qb = new QueryBuilder();
        $builder = $qb->conflictBuilder();

        Expect::exception(InvalidIdentifierException::class)->withMessageContaining('Invalid CASE condition');

        $builder->caseExpression('status', ['INVALID FORMAT']);
    }

    public function testCaseExpressionWithDangerousPatternsThrowsException(): void
    {
        $qb = new QueryBuilder();
        $builder = $qb->conflictBuilder();

        Expect::exception(InvalidIdentifierException::class)->withMessageContaining('dangerous SQL patterns');

        $builder->caseExpression('status', [
            'WHEN count > 10 THEN "active"; DROP TABLE users',
        ]);
    }

    public function testExpressionValidatesFieldName(): void
    {
        $qb = new QueryBuilder();
        $builder = $qb->conflictBuilder();

        Expect::exception(InvalidIdentifierException::class);

        $builder->expression('invalid-field-name;', 'price * 1.1');
    }

    public function testMultipleExpressionsCanBeChained(): void
    {
        $qb = new QueryBuilder();
        $builder = $qb->conflictBuilder();

        $builder->expression('price', 'price * 1.1')
            ->expression('total', 'price + tax')
            ->caseExpression('status', ['WHEN count > 10 THEN "active"', 'ELSE "pending"'])
        ;

        $result = $builder->build();
        Assert::same(
            $result,
            '`price` = price * 1.1, `total` = price + tax, `status` = CASE WHEN count > 10 THEN '
                . '"active" ELSE "pending" END'
        );
    }

    public function testExpressionWithDivision(): void
    {
        $qb = new QueryBuilder();
        $builder = $qb->conflictBuilder();

        $builder->expression('average', 'total / count');
        $result = $builder->build();

        Assert::same($result, '`average` = total / count');
    }

    public function testExpressionWithModulo(): void
    {
        $qb = new QueryBuilder();
        $builder = $qb->conflictBuilder();

        $builder->expression('remainder', 'count % 10');
        $result = $builder->build();

        Assert::same($result, '`remainder` = count % 10');
    }
}
