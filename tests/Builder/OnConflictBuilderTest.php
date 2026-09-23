<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QBuilder\Drivers\Mysql\MysqlDriver;
use QBuilder\Drivers\Mysql\MysqlOnDuplicateKeyUpdateBuilder;
use QBuilder\Drivers\Pgsql\PgsqlOnConflictBuilder;
use QBuilder\Drivers\Sqlite\SqliteOnConflictBuilder;
use QBuilder\Exceptions\MissingRequirementException;
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;

/**
 * @internal
 *
 * @coversNothing
 */
final class OnConflictBuilderTest extends TestCase
{
    public function testMysqlOnDuplicateKeyUpdateBuilderCreateReturnsInstance(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
        $driver = $qb->getDriverInstance();
        $builder = MysqlOnDuplicateKeyUpdateBuilder::create($qb, $driver);

        self::assertInstanceOf(MysqlOnDuplicateKeyUpdateBuilder::class, $builder);
    }

    public function testPgsqlOnConflictBuilderCreateReturnsInstance(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_POSTGRESQL);
        $driver = $qb->getDriverInstance();
        $builder = PgsqlOnConflictBuilder::create($qb, $driver);

        self::assertInstanceOf(PgsqlOnConflictBuilder::class, $builder);
    }

    public function testSqliteOnConflictBuilderCreateReturnsInstance(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_SQLITE);
        $driver = $qb->getDriverInstance();
        $builder = SqliteOnConflictBuilder::create($qb, $driver);

        self::assertInstanceOf(SqliteOnConflictBuilder::class, $builder);
    }

    public function testMysqlBuildReturnsEmptyStringWhenUpdatesAreEmpty(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
        $driver = $qb->getDriverInstance();
        $builder = MysqlOnDuplicateKeyUpdateBuilder::create($qb, $driver);

        self::assertSame('', $builder->build());
    }

    public function testMysqlBuildGeneratesCorrectOnDuplicateKeyUpdateClause(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
        $driver = $qb->getDriverInstance();
        $builder = MysqlOnDuplicateKeyUpdateBuilder::create($qb, $driver);

        $sql = $builder->set('name', 'Updated')
            ->increment('views', 1)
            ->build()
        ;

        self::assertStringContainsString('`name` = \'Updated\'', $sql);
        self::assertStringContainsString('`views` = `views` + 1', $sql);
    }

    public function testMysqlExcludedUsesValuesFunctionWhenVersionUnknown(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
        $builder = MysqlOnDuplicateKeyUpdateBuilder::create($qb, $qb->getDriverInstance());

        self::assertSame('`email` = VALUES(`email`)', $builder->excluded('email')->build());
    }

    public function testMysqlExcludedWithDifferentFieldNames(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
        $builder = MysqlOnDuplicateKeyUpdateBuilder::create($qb, $qb->getDriverInstance());

        self::assertSame('`name` = VALUES(`user_name`)', $builder->excluded('name', 'user_name')->build());
    }

    public function testMysqlExcludedUsesRowAliasOnMysql8019(): void
    {
        $qb = (new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL))->setServerVersion('8.0.45');
        $conflict = $qb->conflictBuilder()->excluded('email')->increment('n');

        $sql = $qb->insert('u')->insertRow(['id' => 1, 'email' => 'a'])
            ->insertConflictHandler($conflict)
            ->build(true)
        ;

        self::assertSame(
            "INSERT INTO `u` (`id`, `email`) VALUES (1, 'a') AS `new` "
                .'ON DUPLICATE KEY UPDATE `email` = `new`.`email`, `n` = `n` + 1',
            $sql
        );
    }

    public function testMysqlExcludedUsesValuesFunctionOnMariadb(): void
    {
        $qb = (new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL))->setServerVersion('5.5.5-10.11.6-MariaDB-0+deb12u1');
        $conflict = $qb->conflictBuilder()->excluded('email');

        $sql = $qb->insert('u')->insertRow(['id' => 1, 'email' => 'a'])
            ->insertConflictHandler($conflict)
            ->build(true)
        ;

        self::assertSame(
            "INSERT INTO `u` (`id`, `email`) VALUES (1, 'a') ON DUPLICATE KEY UPDATE `email` = VALUES(`email`)",
            $sql
        );
    }

    /**
     * @param non-empty-string $version
     */
    #[DataProvider('provideMysqlRowAliasSupportByServerVersionCases')]
    public function testMysqlRowAliasSupportByServerVersion(string $version, bool $expected): void
    {
        self::assertSame($expected, (new MysqlDriver())->setServerVersion($version)->supportsInsertRowAlias());
    }

    /**
     * @return iterable<string, array{non-empty-string, bool}>
     */
    public static function provideMysqlRowAliasSupportByServerVersionCases(): iterable
    {
        yield 'mysql 8.0.18' => ['8.0.18', false];

        yield 'mysql 8.0.19' => ['8.0.19', true];

        yield 'mysql 8.4 lts' => ['8.4.3', true];

        yield 'mysql 9 with suffix' => ['9.1.0-commercial', true];

        yield 'mysql 5.7' => ['5.7.44-log', false];

        yield 'mariadb' => ['10.11.6-MariaDB', false];

        yield 'mariadb with compat prefix' => ['5.5.5-11.4.2-MariaDB-ubu2404', false];

        yield 'garbage' => ['unknown', false];
    }

    public function testServerVersionIsInheritedBySubqueryAndAppliedAfterDriverCreated(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
        $qb->getDriverInstance();
        $qb->setServerVersion('8.0.45');

        self::assertSame('8.0.45', $qb->getDriverInstance()->getServerVersion());
        self::assertSame('8.0.45', $qb->subQuery()->getDriverInstance()->getServerVersion());
    }

    public function testMysqlConflictTargetDoesNothing(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
        $driver = $qb->getDriverInstance();
        $builder = MysqlOnDuplicateKeyUpdateBuilder::create($qb, $driver);

        $result = $builder->conflictTarget(['email']);

        self::assertSame($builder, $result);
    }

    public function testMysqlBuildConflictClauseReturnsEmptyString(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
        $driver = $qb->getDriverInstance();
        $builder = MysqlOnDuplicateKeyUpdateBuilder::create($qb, $driver);

        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildConflictClause');
        $method->setAccessible(true);
        $result = $method->invoke($builder);

        self::assertSame('', $result);
    }

    public function testMysqlBuildUpdatePrefixReturnsOnDuplicateKeyUpdate(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
        $driver = $qb->getDriverInstance();
        $builder = MysqlOnDuplicateKeyUpdateBuilder::create($qb, $driver);

        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildUpdatePrefix');
        $method->setAccessible(true);
        $result = $method->invoke($builder);

        self::assertSame('ON DUPLICATE KEY UPDATE', $result);
    }

    public function testPgsqlBuildThrowsExceptionWhenConflictTargetNotSet(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_POSTGRESQL);
        $driver = $qb->getDriverInstance();
        $builder = PgsqlOnConflictBuilder::create($qb, $driver);

        $this->expectException(MissingRequirementException::class);
        $this->expectExceptionMessage('Conflict target must be specified for PostgreSQL ON CONFLICT');

        $builder->set('name', 'Updated')->build();
    }

    public function testPgsqlBuildGeneratesCorrectOnConflictClause(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_POSTGRESQL);
        $driver = $qb->getDriverInstance();
        $builder = PgsqlOnConflictBuilder::create($qb, $driver);

        $sql = $builder->conflictTarget(['email'])
            ->set('name', 'Updated')
            ->increment('views', 1)
            ->build()
        ;

        self::assertStringContainsString('ON CONFLICT ("email")', $sql);
        self::assertStringContainsString('DO UPDATE SET', $sql);
        self::assertStringContainsString('"name" = \'Updated\'', $sql);
        self::assertStringContainsString('"views" = "views" + 1', $sql);
    }

    public function testPgsqlBuildReturnsDoNothingWhenUpdatesAreEmpty(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_POSTGRESQL);
        $driver = $qb->getDriverInstance();
        $builder = PgsqlOnConflictBuilder::create($qb, $driver);

        $sql = $builder->conflictTarget(['email'])
            ->build()
        ;

        self::assertStringContainsString('ON CONFLICT ("email") DO NOTHING', $sql);
    }

    public function testPgsqlExcludedUsesExcludedKeyword(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_POSTGRESQL);
        $driver = $qb->getDriverInstance();
        $builder = PgsqlOnConflictBuilder::create($qb, $driver);

        $sql = $builder->conflictTarget(['email'])
            ->excluded('email')
            ->build()
        ;

        self::assertStringContainsString('EXCLUDED."email"', $sql);
    }

    public function testPgsqlExcludedWithDifferentFieldNames(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_POSTGRESQL);
        $driver = $qb->getDriverInstance();
        $builder = PgsqlOnConflictBuilder::create($qb, $driver);

        $sql = $builder->conflictTarget(['email'])
            ->excluded('name', 'user_name')
            ->build()
        ;

        self::assertStringContainsString('EXCLUDED."user_name"', $sql);
    }

    public function testPgsqlConflictTargetWithMultipleFields(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_POSTGRESQL);
        $driver = $qb->getDriverInstance();
        $builder = PgsqlOnConflictBuilder::create($qb, $driver);

        $sql = $builder->conflictTarget(['user_id', 'product_id'])
            ->set('quantity', 10)
            ->build()
        ;

        self::assertStringContainsString('ON CONFLICT ("user_id", "product_id")', $sql);
    }

    public function testPgsqlBuildConflictClauseThrowsExceptionWhenConflictTargetsAreEmpty(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_POSTGRESQL);
        $driver = $qb->getDriverInstance();
        $builder = PgsqlOnConflictBuilder::create($qb, $driver);

        $this->expectException(MissingRequirementException::class);
        $this->expectExceptionMessage('PostgreSQL ON CONFLICT requires conflict target fields');

        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildConflictClause');
        $method->setAccessible(true);
        $method->invoke($builder);
    }

    public function testSqliteBuildGeneratesCorrectOnConflictClause(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_SQLITE);
        $driver = $qb->getDriverInstance();
        $builder = SqliteOnConflictBuilder::create($qb, $driver);

        $sql = $builder->conflictTarget(['email'])
            ->set('name', 'Updated')
            ->increment('views', 1)
            ->build()
        ;

        self::assertStringContainsString('ON CONFLICT ("email")', $sql);
        self::assertStringContainsString('DO UPDATE SET', $sql);
        self::assertStringContainsString('"name" = \'Updated\'', $sql);
        self::assertStringContainsString('"views" = "views" + 1', $sql);
    }

    public function testSqliteBuildWithEmptyConflictTargets(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_SQLITE);
        $driver = $qb->getDriverInstance();
        $builder = SqliteOnConflictBuilder::create($qb, $driver);

        $sql = $builder->set('name', 'Updated')
            ->build()
        ;

        self::assertStringContainsString('ON CONFLICT', $sql);
        self::assertStringContainsString('DO UPDATE SET', $sql);
        self::assertStringNotContainsString('ON CONFLICT ("', $sql);
    }

    public function testSqliteExcludedUsesExcludedKeyword(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_SQLITE);
        $driver = $qb->getDriverInstance();
        $builder = SqliteOnConflictBuilder::create($qb, $driver);

        $sql = $builder->conflictTarget(['email'])
            ->excluded('email')
            ->build()
        ;

        self::assertStringContainsString('EXCLUDED."email"', $sql);
    }

    public function testSqliteExcludedWithDifferentFieldNames(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_SQLITE);
        $driver = $qb->getDriverInstance();
        $builder = SqliteOnConflictBuilder::create($qb, $driver);

        $sql = $builder->conflictTarget(['email'])
            ->excluded('name', 'user_name')
            ->build()
        ;

        self::assertStringContainsString('EXCLUDED."user_name"', $sql);
    }

    public function testSqliteConflictTargetWithMultipleFields(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_SQLITE);
        $driver = $qb->getDriverInstance();
        $builder = SqliteOnConflictBuilder::create($qb, $driver);

        $sql = $builder->conflictTarget(['user_id', 'product_id'])
            ->set('quantity', 10)
            ->build()
        ;

        self::assertStringContainsString('ON CONFLICT ("user_id", "product_id")', $sql);
    }

    public function testExpressionMethod(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
        $driver = $qb->getDriverInstance();
        $builder = MysqlOnDuplicateKeyUpdateBuilder::create($qb, $driver);

        $sql = $builder->expression('price', 'price * 1.1')
            ->build()
        ;

        self::assertStringContainsString('`price` = price * 1.1', $sql);
    }

    public function testCaseExpressionMethod(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
        $driver = $qb->getDriverInstance();
        $builder = MysqlOnDuplicateKeyUpdateBuilder::create($qb, $driver);

        $sql = $builder->caseExpression('status', [
            'WHEN count > 10 THEN \'active\'',
            'ELSE \'pending\'',
        ])
            ->build()
        ;

        self::assertStringContainsString('CASE', $sql);
        self::assertStringContainsString('WHEN count > 10 THEN \'active\'', $sql);
        self::assertStringContainsString('ELSE \'pending\'', $sql);
    }

    public function testSetWithNullValue(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
        $driver = $qb->getDriverInstance();
        $builder = MysqlOnDuplicateKeyUpdateBuilder::create($qb, $driver);

        $sql = $builder->set('name', null)
            ->build()
        ;

        self::assertStringContainsString('`name` = NULL', $sql);
    }

    public function testSetWithBooleanValues(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
        $driver = $qb->getDriverInstance();
        $builder = MysqlOnDuplicateKeyUpdateBuilder::create($qb, $driver);

        $sql = $builder->set('active', true)
            ->build()
        ;

        self::assertStringContainsString('`active` = 1', $sql);

        $builder2 = MysqlOnDuplicateKeyUpdateBuilder::create($qb, $driver);
        $sql2 = $builder2->set('active', false)
            ->build()
        ;

        self::assertStringContainsString('`active` = 0', $sql2);
    }

    public function testSetWithNumericValues(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
        $driver = $qb->getDriverInstance();
        $builder = MysqlOnDuplicateKeyUpdateBuilder::create($qb, $driver);

        $sql = $builder->set('age', 25)
            ->build()
        ;

        self::assertStringContainsString('`age` = 25', $sql);
    }

    public function testIncrementMethod(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
        $driver = $qb->getDriverInstance();
        $builder = MysqlOnDuplicateKeyUpdateBuilder::create($qb, $driver);

        $sql = $builder->increment('views', 1)
            ->build()
        ;

        self::assertStringContainsString('`views` = `views` + 1', $sql);
    }

    public function testDecrementMethod(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
        $driver = $qb->getDriverInstance();
        $builder = MysqlOnDuplicateKeyUpdateBuilder::create($qb, $driver);

        $sql = $builder->decrement('balance', 10)
            ->build()
        ;

        self::assertStringContainsString('`balance` = `balance` - 10', $sql);
    }

    public function testSetNullMethod(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
        $driver = $qb->getDriverInstance();
        $builder = MysqlOnDuplicateKeyUpdateBuilder::create($qb, $driver);

        $sql = $builder->setNull('name')
            ->build()
        ;

        self::assertStringContainsString('`name` = NULL', $sql);
    }

    public function testSqlFunctionMethod(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
        $driver = $qb->getDriverInstance();
        $builder = MysqlOnDuplicateKeyUpdateBuilder::create($qb, $driver);

        $sql = $builder->sqlFunction('updated_at', 'NOW()')
            ->build()
        ;

        self::assertStringContainsString('`updated_at` = NOW()', $sql);
    }

    public function testMultipleUpdateOperations(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
        $driver = $qb->getDriverInstance();
        $builder = MysqlOnDuplicateKeyUpdateBuilder::create($qb, $driver);

        $sql = $builder->set('name', 'Updated')
            ->increment('views', 1)
            ->sqlFunction('updated_at', 'NOW()')
            ->build()
        ;

        self::assertStringContainsString('`name` = \'Updated\'', $sql);
        self::assertStringContainsString('`views` = `views` + 1', $sql);
        self::assertStringContainsString('`updated_at` = NOW()', $sql);
    }
}
