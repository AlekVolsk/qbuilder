<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QBuilder\Condition\ConditionBy;
use QBuilder\Condition\Field;
use QBuilder\Exceptions\UnsupportedFeatureException;
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;

/**
 * @internal
 *
 * @coversNothing
 */
final class ConditionBitmaskTest extends TestCase
{
    public function testBitmaskSingleBitSet(): void
    {
        $qb = $this->createQueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->bitmask('permissions', [1 => 1])
            ->end()
            ->build(true)
        ;

        self::assertSame(
            'SELECT * FROM `users` WHERE ((`permissions` & 1 = 1))',
            $sql
        );
    }

    public function testBitmaskSingleBitUnset(): void
    {
        $qb = $this->createQueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->bitmask('permissions', [2 => 0])
            ->end()
            ->build(true)
        ;

        self::assertSame(
            'SELECT * FROM `users` WHERE ((`permissions` & 2 = 0))',
            $sql
        );
    }

    public function testBitmaskMultipleBits(): void
    {
        $qb = $this->createQueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->bitmask('permissions', [1 => 1, 2 => 0, 4 => 1])
            ->end()
            ->build(true)
        ;

        self::assertSame(
            'SELECT * FROM `users` WHERE ((`permissions` & 1 = 1) AND '
            .'(`permissions` & 2 = 0) AND (`permissions` & 4 = 4))',
            $sql
        );
    }

    public function testBitmaskWithBooleanStates(): void
    {
        $qb = $this->createQueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->bitmask('flags', [1 => true, 2 => false, 4 => true])
            ->end()
            ->build(true)
        ;

        self::assertSame(
            'SELECT * FROM `users` WHERE ((`flags` & 1 = 1) AND (`flags` & 2 = 0) AND (`flags` & 4 = 4))',
            $sql
        );
    }

    public function testNotBitmaskSingleBit(): void
    {
        $qb = $this->createQueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->notBitmask('permissions', [1 => 1])
            ->end()
            ->build(true)
        ;

        self::assertSame(
            'SELECT * FROM `users` WHERE (NOT ((`permissions` & 1 = 1)))',
            $sql
        );
    }

    public function testNotBitmaskMultipleBits(): void
    {
        $qb = $this->createQueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->notBitmask('permissions', [1 => 1, 4 => 1])
            ->end()
            ->build(true)
        ;

        self::assertSame(
            'SELECT * FROM `users` WHERE (NOT ((`permissions` & 1 = 1) AND (`permissions` & 4 = 4)))',
            $sql
        );
    }

    public function testBitmaskWithTableAlias(): void
    {
        $qb = $this->createQueryBuilder();
        $sql = $qb->select(Field::set('*', 'u'))
            ->from('users', 'u')
            ->where()
            ->bitmask('u.permissions', [1 => 1, 2 => 1])
            ->end()
            ->build(true)
        ;

        self::assertSame(
            'SELECT `u`.* FROM `users` AS `u` WHERE ((`u`.`permissions` & 1 = 1) AND (`u`.`permissions` & 2 = 2))',
            $sql
        );
    }

    public function testBitmaskCombinedWithOtherConditions(): void
    {
        $qb = $this->createQueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->eq('status', 'active')
            ->bitmask('permissions', [1 => 1])
            ->end()
            ->build(true)
        ;

        self::assertSame(
            "SELECT * FROM `users` WHERE (`status` = 'active') AND ((`permissions` & 1 = 1))",
            $sql
        );
    }

    public function testBitmaskAllDrivers(): void
    {
        $drivers = [
            QbConsts::DRIVER_MYSQL,
            QbConsts::DRIVER_PDO_MYSQL,
            QbConsts::DRIVER_POSTGRESQL,
            QbConsts::DRIVER_PGSQL,
            QbConsts::DRIVER_SQLITE,
            QbConsts::DRIVER_MSSQL,
            QbConsts::DRIVER_ORACLE,
            QbConsts::DRIVER_CLICKHOUSE,
        ];

        foreach ($drivers as $driver) {
            $qb = $this->createQueryBuilder($driver);
            $sql = $qb->select('*')
                ->from('users')
                ->where()
                ->bitmask('flags', [1 => 1])
                ->end()
                ->build(true)
            ;

            self::assertTrue(str_contains($sql, '& 1 = 1'), "Driver {$driver} should support bitmask");
        }
    }

    public function testFindInSetMysqlSingleValue(): void
    {
        $qb = $this->createQueryBuilder(QbConsts::DRIVER_MYSQL);
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->findInSet('tags', 'admin')
            ->end()
            ->build(true)
        ;

        self::assertSame(
            "SELECT * FROM `users` WHERE (FIND_IN_SET('admin', `tags`))",
            $sql
        );
    }

    public function testFindInSetMysqlMultipleValues(): void
    {
        $qb = $this->createQueryBuilder(QbConsts::DRIVER_MYSQL);
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->findInSet('tags', ['admin', 'moderator'])
            ->end()
            ->build(true)
        ;

        self::assertSame(
            "SELECT * FROM `users` WHERE (FIND_IN_SET('admin', `tags`) OR FIND_IN_SET('moderator', `tags`))",
            $sql
        );
    }

    public function testFindInSetWithTableAlias(): void
    {
        $qb = $this->createQueryBuilder(QbConsts::DRIVER_MYSQL);
        $sql = $qb->select(Field::set('*', 'u'))
            ->from('users', 'u')
            ->where()
            ->findInSet('u.roles', 'admin')
            ->end()
            ->build(true)
        ;

        self::assertSame(
            "SELECT `u`.* FROM `users` AS `u` WHERE (FIND_IN_SET('admin', `u`.`roles`))",
            $sql
        );
    }

    public function testFindInSetCombinedWithOtherConditions(): void
    {
        $qb = $this->createQueryBuilder(QbConsts::DRIVER_MYSQL);
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->eq('status', 'active')
            ->findInSet('tags', 'premium')
            ->end()
            ->build(true)
        ;

        self::assertSame(
            "SELECT * FROM `users` WHERE (`status` = 'active') AND (FIND_IN_SET('premium', `tags`))",
            $sql
        );
    }

    #[DataProvider('provideFindInSetThrowsExceptionForUnsupportedDriversCases')]
    public function testFindInSetThrowsExceptionForUnsupportedDrivers(string $driver): void
    {
        $this->expectException(UnsupportedFeatureException::class);
        $this->expectExceptionMessage("FIND_IN_SET is not supported by driver '{$driver}'");

        $qb = $this->createQueryBuilder($driver);
        $qb->select('*')
            ->from('users')
            ->where()
            ->findInSet('tags', 'admin')
            ->end()
            ->build(true)
        ;
    }

    /**
     * @return iterable<string, array<string>>
     */
    public static function provideFindInSetThrowsExceptionForUnsupportedDriversCases(): iterable
    {
        yield 'PostgreSQL' => [QbConsts::DRIVER_POSTGRESQL];
        yield 'PostgreSQL alias' => [QbConsts::DRIVER_PGSQL];
        yield 'SQLite' => [QbConsts::DRIVER_SQLITE];
        yield 'MS SQL Server' => [QbConsts::DRIVER_MSSQL];
        yield 'Oracle' => [QbConsts::DRIVER_ORACLE];
        yield 'ClickHouse' => [QbConsts::DRIVER_CLICKHOUSE];
    }

    public function testFindInSetWithSpecialCharacters(): void
    {
        $qb = $this->createQueryBuilder(QbConsts::DRIVER_MYSQL);
        $sql = $qb->select('*')
            ->from('users')
            ->where()
            ->findInSet('tags', "admin's")
            ->end()
            ->build(true)
        ;

        self::assertTrue(str_contains($sql, 'FIND_IN_SET('));
        self::assertTrue(str_contains($sql, '`tags`)'));
    }

    public function testBitmaskInHavingClause(): void
    {
        $qb = $this->createQueryBuilder();
        $groupBy = ConditionBy::groupBy()->add('user_id');

        $sql = $qb->select(Field::set('user_id'), Field::set('SUM(amount)', '', 'total'))
            ->from('transactions')
            ->groupBy($groupBy)
            ->having()
            ->bitmask('user_id', [1 => 1])
            ->end()
            ->build(true)
        ;

        self::assertSame(
            'SELECT `user_id`, SUM(`amount`) AS `total` FROM `transactions` '
            .'GROUP BY `user_id` HAVING ((`user_id` & 1 = 1))',
            $sql
        );
    }

    protected function createQueryBuilder(string $driver = QbConsts::DRIVER_MYSQL): QueryBuilder
    {
        return new QueryBuilder($driver);
    }
}
