<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use PHPUnit\Framework\TestCase;
use QBuilder\Drivers\DriverFactory;
use QBuilder\Drivers\Mysql\MysqlOnDuplicateKeyUpdateBuilder;
use QBuilder\Exceptions\InvalidIdentifierException;
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;

/**
 * @internal
 *
 * @coversNothing
 */
final class ExpressionsTraitTest extends TestCase
{
    public function testExpressionWithValidExpression(): void
    {
        $qb = new QueryBuilder();
        $driver = DriverFactory::create(QbConsts::DRIVER_MYSQL);
        $builder = MysqlOnDuplicateKeyUpdateBuilder::create($qb, $driver);

        $builder->expression('price', 'price * 1.1');
        $result = $builder->build();

        self::assertStringContainsString('`price` = price * 1.1', $result);
    }

    public function testExpressionWithArithmeticExpression(): void
    {
        $qb = new QueryBuilder();
        $driver = DriverFactory::create(QbConsts::DRIVER_MYSQL);
        $builder = MysqlOnDuplicateKeyUpdateBuilder::create($qb, $driver);

        $builder->expression('count', 'count + 1');
        $result = $builder->build();

        self::assertStringContainsString('`count` = count + 1', $result);
    }

    public function testExpressionWithParentheses(): void
    {
        $qb = new QueryBuilder();
        $driver = DriverFactory::create(QbConsts::DRIVER_MYSQL);
        $builder = MysqlOnDuplicateKeyUpdateBuilder::create($qb, $driver);

        $builder->expression('total', '(price * quantity) + tax');
        $result = $builder->build();

        self::assertStringContainsString('`total` = (price * quantity) + tax', $result);
    }

    public function testExpressionWithInvalidCharactersThrowsException(): void
    {
        $qb = new QueryBuilder();
        $driver = DriverFactory::create(QbConsts::DRIVER_MYSQL);
        $builder = MysqlOnDuplicateKeyUpdateBuilder::create($qb, $driver);

        $this->expectException(InvalidIdentifierException::class);
        $this->expectExceptionMessage('Invalid expression');

        $builder->expression('price', 'price * 1.1; DROP TABLE users');
    }

    public function testExpressionWithDangerousPatternsThrowsException(): void
    {
        $qb = new QueryBuilder();
        $driver = DriverFactory::create(QbConsts::DRIVER_MYSQL);
        $builder = MysqlOnDuplicateKeyUpdateBuilder::create($qb, $driver);

        $this->expectException(InvalidIdentifierException::class);
        $this->expectExceptionMessage('dangerous SQL patterns');

        $builder->expression('price', 'price DROP TABLE users');
    }

    public function testExpressionWithDropTableThrowsException(): void
    {
        $qb = new QueryBuilder();
        $driver = DriverFactory::create(QbConsts::DRIVER_MYSQL);
        $builder = MysqlOnDuplicateKeyUpdateBuilder::create($qb, $driver);

        $this->expectException(InvalidIdentifierException::class);
        $this->expectExceptionMessage('dangerous SQL patterns');

        $builder->expression('price', 'price DROP TABLE users');
    }

    public function testCaseExpressionWithValidCase(): void
    {
        $qb = new QueryBuilder();
        $driver = DriverFactory::create(QbConsts::DRIVER_MYSQL);
        $builder = MysqlOnDuplicateKeyUpdateBuilder::create($qb, $driver);

        $builder->caseExpression('status', [
            'WHEN count > 10 THEN "active"',
            'ELSE "pending"',
        ]);
        $result = $builder->build();

        self::assertStringContainsString('`status` = CASE', $result);
        self::assertStringContainsString('WHEN count > 10 THEN "active"', $result);
        self::assertStringContainsString('ELSE "pending"', $result);
        self::assertStringContainsString('END', $result);
    }

    public function testCaseExpressionWithMultipleWhenClauses(): void
    {
        $qb = new QueryBuilder();
        $driver = DriverFactory::create(QbConsts::DRIVER_MYSQL);
        $builder = MysqlOnDuplicateKeyUpdateBuilder::create($qb, $driver);

        $builder->caseExpression('discount', [
            'WHEN total > 1000 THEN 0.1',
            'WHEN total > 500 THEN 0.05',
            'ELSE 0',
        ]);
        $result = $builder->build();

        self::assertStringContainsString('CASE', $result);
        self::assertStringContainsString('WHEN total > 1000 THEN 0.1', $result);
        self::assertStringContainsString('WHEN total > 500 THEN 0.05', $result);
        self::assertStringContainsString('ELSE 0', $result);
    }

    public function testCaseExpressionWithInvalidFormatThrowsException(): void
    {
        $qb = new QueryBuilder();
        $driver = DriverFactory::create(QbConsts::DRIVER_MYSQL);
        $builder = MysqlOnDuplicateKeyUpdateBuilder::create($qb, $driver);

        $this->expectException(InvalidIdentifierException::class);
        $this->expectExceptionMessage('Invalid CASE condition');

        $builder->caseExpression('status', ['INVALID FORMAT']);
    }

    public function testCaseExpressionWithDangerousPatternsThrowsException(): void
    {
        $qb = new QueryBuilder();
        $driver = DriverFactory::create(QbConsts::DRIVER_MYSQL);
        $builder = MysqlOnDuplicateKeyUpdateBuilder::create($qb, $driver);

        $this->expectException(InvalidIdentifierException::class);
        $this->expectExceptionMessage('dangerous SQL patterns');

        $builder->caseExpression('status', [
            'WHEN count > 10 THEN "active"; DROP TABLE users',
        ]);
    }

    public function testExpressionValidatesFieldName(): void
    {
        $qb = new QueryBuilder();
        $driver = DriverFactory::create(QbConsts::DRIVER_MYSQL);
        $builder = MysqlOnDuplicateKeyUpdateBuilder::create($qb, $driver);

        $this->expectException(InvalidIdentifierException::class);

        $builder->expression('invalid-field-name;', 'price * 1.1');
    }

    public function testMultipleExpressionsCanBeChained(): void
    {
        $qb = new QueryBuilder();
        $driver = DriverFactory::create(QbConsts::DRIVER_MYSQL);
        $builder = MysqlOnDuplicateKeyUpdateBuilder::create($qb, $driver);

        $builder->expression('price', 'price * 1.1')
            ->expression('total', 'price + tax')
            ->caseExpression('status', ['WHEN count > 10 THEN "active"', 'ELSE "pending"'])
        ;

        $result = $builder->build();
        self::assertStringContainsString('`price` = price * 1.1', $result);
        self::assertStringContainsString('`total` = price + tax', $result);
        self::assertStringContainsString('CASE', $result);
    }

    public function testExpressionWithDivision(): void
    {
        $qb = new QueryBuilder();
        $driver = DriverFactory::create(QbConsts::DRIVER_MYSQL);
        $builder = MysqlOnDuplicateKeyUpdateBuilder::create($qb, $driver);

        $builder->expression('average', 'total / count');
        $result = $builder->build();

        self::assertStringContainsString('`average` = total / count', $result);
    }

    public function testExpressionWithModulo(): void
    {
        $qb = new QueryBuilder();
        $driver = DriverFactory::create(QbConsts::DRIVER_MYSQL);
        $builder = MysqlOnDuplicateKeyUpdateBuilder::create($qb, $driver);

        $builder->expression('remainder', 'count % 10');
        $result = $builder->build();

        self::assertStringContainsString('`remainder` = count % 10', $result);
    }
}
