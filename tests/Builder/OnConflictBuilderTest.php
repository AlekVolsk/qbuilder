<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use QBuilder\Drivers\Mysql\MysqlDriver;
use QBuilder\Drivers\Mysql\MysqlOnDuplicateKeyUpdateBuilder;
use QBuilder\Drivers\Pgsql\PgsqlOnConflictBuilder;
use QBuilder\Drivers\Sqlite\SqliteOnConflictBuilder;
use QBuilder\Exceptions\MissingRequirementException;
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;
use Testo\Assert;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

/**
 * @internal
 */
#[Test]
final class OnConflictBuilderTest
{
    public function testMysqlConflictBuilderIsOnDuplicateKeyUpdate(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
        $builder = $qb->conflictBuilder();

        Assert::instanceOf($builder, MysqlOnDuplicateKeyUpdateBuilder::class);
    }

    public function testPgsqlConflictBuilderIsOnConflict(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_POSTGRESQL);
        $builder = $qb->conflictBuilder();

        Assert::instanceOf($builder, PgsqlOnConflictBuilder::class);
    }

    public function testSqliteConflictBuilderIsOnConflict(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_SQLITE);
        $builder = $qb->conflictBuilder();

        Assert::instanceOf($builder, SqliteOnConflictBuilder::class);
    }

    public function testMysqlBuildReturnsEmptyStringWhenUpdatesAreEmpty(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
        $builder = $qb->conflictBuilder();

        Assert::same($builder->build(), '');
    }

    public function testMysqlBuildGeneratesCorrectOnDuplicateKeyUpdateClause(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
        $builder = $qb->conflictBuilder();

        $sql = $builder->set('name', 'Updated')
            ->increment('views', 1)
            ->build()
        ;

        Assert::same($sql, '`name` = \'Updated\', `views` = `views` + 1');
    }

    public function testMysqlExcludedUsesValuesFunctionWhenVersionUnknown(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
        $builder = $qb->conflictBuilder();

        Assert::same($builder->excluded('email')->build(), '`email` = VALUES(`email`)');
    }

    public function testMysqlExcludedWithDifferentFieldNames(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
        $builder = $qb->conflictBuilder();

        Assert::same($builder->excluded('name', 'user_name')->build(), '`name` = VALUES(`user_name`)');
    }

    public function testMysqlExcludedUsesRowAliasOnMysql8019(): void
    {
        $qb = (new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL))->setServerVersion('8.0.45');
        $conflict = $qb->conflictBuilder()->excluded('email')->increment('n');

        $sql = $qb->insert('u')->insertRow(['id' => 1, 'email' => 'a'])
            ->insertConflictHandler($conflict)
            ->build(true)
        ;

        Assert::same($sql, "INSERT INTO `u` (`id`, `email`) VALUES (1, 'a') AS `new` "
            . 'ON DUPLICATE KEY UPDATE `email` = `new`.`email`, `n` = `n` + 1');
    }

    public function testMysqlExcludedUsesValuesFunctionOnMariadb(): void
    {
        $qb = (new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL))->setServerVersion('5.5.5-10.11.6-MariaDB-0+deb12u1');
        $conflict = $qb->conflictBuilder()->excluded('email');

        $sql = $qb->insert('u')->insertRow(['id' => 1, 'email' => 'a'])
            ->insertConflictHandler($conflict)
            ->build(true)
        ;

        Assert::same(
            $sql,
            "INSERT INTO `u` (`id`, `email`) VALUES (1, 'a') ON DUPLICATE KEY UPDATE `email` = VALUES(`email`)"
        );
    }

    /**
     * @param non-empty-string $version
     */
    #[DataProvider('provideMysqlRowAliasSupportByServerVersionCases')]
    public function testMysqlRowAliasSupportByServerVersion(string $version, bool $expected): void
    {
        Assert::same((new MysqlDriver())->setServerVersion($version)->supportsInsertRowAlias(), $expected);
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

        Assert::same($qb->getDriverInstance()->getServerVersion(), '8.0.45');
        Assert::same($qb->subQuery()->getDriverInstance()->getServerVersion(), '8.0.45');
    }

    public function testMysqlConflictTargetDoesNothing(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
        $builder = $qb->conflictBuilder();

        $result = $builder->conflictTarget(['email']);

        Assert::same($result, $builder);
    }

    public function testPgsqlBuildThrowsExceptionWhenConflictTargetNotSet(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_POSTGRESQL);
        $builder = $qb->conflictBuilder();

        Expect::exception(MissingRequirementException::class)
            ->withMessageContaining('Conflict target must be specified for PostgreSQL ON CONFLICT')
        ;

        $builder->set('name', 'Updated')->build();
    }

    public function testPgsqlBuildGeneratesCorrectOnConflictClause(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_POSTGRESQL);
        $builder = $qb->conflictBuilder();

        $sql = $builder->conflictTarget(['email'])
            ->set('name', 'Updated')
            ->build()
        ;

        Assert::same($sql, "\nON CONFLICT (\"email\")\nDO UPDATE SET \"name\" = 'Updated'");
    }

    public function testPgsqlBuildReturnsDoNothingWhenUpdatesAreEmpty(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_POSTGRESQL);
        $builder = $qb->conflictBuilder();

        $sql = $builder->conflictTarget(['email'])
            ->build()
        ;

        Assert::same($sql, "\nON CONFLICT (\"email\") DO NOTHING");
    }

    public function testPgsqlExcludedUsesExcludedKeyword(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_POSTGRESQL);
        $builder = $qb->conflictBuilder();

        $sql = $builder->conflictTarget(['email'])
            ->excluded('email')
            ->build()
        ;

        Assert::same($sql, "\nON CONFLICT (\"email\")\nDO UPDATE SET \"email\" = EXCLUDED.\"email\"");
    }

    public function testPgsqlExcludedWithDifferentFieldNames(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_POSTGRESQL);
        $builder = $qb->conflictBuilder();

        $sql = $builder->conflictTarget(['email'])
            ->excluded('name', 'user_name')
            ->build()
        ;

        Assert::same($sql, "\nON CONFLICT (\"email\")\nDO UPDATE SET \"name\" = EXCLUDED.\"user_name\"");
    }

    public function testPgsqlConflictTargetWithMultipleFields(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_POSTGRESQL);
        $builder = $qb->conflictBuilder();

        $sql = $builder->conflictTarget(['user_id', 'product_id'])
            ->set('quantity', 10)
            ->build()
        ;

        Assert::same($sql, "\nON CONFLICT (\"user_id\", \"product_id\")\nDO UPDATE SET \"quantity\" = 10");
    }

    public function testSqliteBuildGeneratesCorrectOnConflictClause(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_SQLITE);
        $builder = $qb->conflictBuilder();

        $sql = $builder->conflictTarget(['email'])
            ->set('name', 'Updated')
            ->increment('views', 1)
            ->build()
        ;

        Assert::same($sql, "\nON CONFLICT (\"email\")\nDO UPDATE SET \"name\" = 'Updated', \"views\" = \"views\" + 1");
    }

    public function testSqliteBuildWithEmptyConflictTargets(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_SQLITE);
        $builder = $qb->conflictBuilder();

        $sql = $builder->set('name', 'Updated')
            ->build()
        ;

        Assert::same($sql, "\nON CONFLICT\nDO UPDATE SET \"name\" = 'Updated'");
    }

    public function testSqliteExcludedUsesExcludedKeyword(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_SQLITE);
        $builder = $qb->conflictBuilder();

        $sql = $builder->conflictTarget(['email'])
            ->excluded('email')
            ->build()
        ;

        Assert::same($sql, "\nON CONFLICT (\"email\")\nDO UPDATE SET \"email\" = EXCLUDED.\"email\"");
    }

    public function testSqliteExcludedWithDifferentFieldNames(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_SQLITE);
        $builder = $qb->conflictBuilder();

        $sql = $builder->conflictTarget(['email'])
            ->excluded('name', 'user_name')
            ->build()
        ;

        Assert::same($sql, "\nON CONFLICT (\"email\")\nDO UPDATE SET \"name\" = EXCLUDED.\"user_name\"");
    }

    public function testSqliteConflictTargetWithMultipleFields(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_SQLITE);
        $builder = $qb->conflictBuilder();

        $sql = $builder->conflictTarget(['user_id', 'product_id'])
            ->set('quantity', 10)
            ->build()
        ;

        Assert::same($sql, "\nON CONFLICT (\"user_id\", \"product_id\")\nDO UPDATE SET \"quantity\" = 10");
    }

    public function testExpressionMethod(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
        $builder = $qb->conflictBuilder();

        $sql = $builder->expression('price', 'price * 1.1')
            ->build()
        ;

        Assert::same($sql, '`price` = price * 1.1');
    }

    public function testCaseExpressionMethod(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
        $builder = $qb->conflictBuilder();

        $sql = $builder->caseExpression('status', [
            'WHEN count > 10 THEN \'active\'',
            'ELSE \'pending\'',
        ])
            ->build()
        ;

        Assert::same($sql, '`status` = CASE WHEN count > 10 THEN \'active\' ELSE \'pending\' END');
    }

    public function testSetWithNullValue(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
        $builder = $qb->conflictBuilder();

        $sql = $builder->set('name', null)
            ->build()
        ;

        Assert::same($sql, '`name` = NULL');
    }

    public function testSetWithBooleanValues(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
        $builder = $qb->conflictBuilder();

        $sql = $builder->set('active', true)
            ->build()
        ;

        Assert::same($sql, '`active` = 1');

        $builder2 = $qb->conflictBuilder();
        $sql2 = $builder2->set('active', false)
            ->build()
        ;

        Assert::same($sql2, '`active` = 0');
    }

    public function testSetWithNumericValues(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
        $builder = $qb->conflictBuilder();

        $sql = $builder->set('age', 25)
            ->build()
        ;

        Assert::same($sql, '`age` = 25');
    }

    public function testIncrementMethod(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
        $builder = $qb->conflictBuilder();

        $sql = $builder->increment('views', 1)
            ->build()
        ;

        Assert::same($sql, '`views` = `views` + 1');
    }

    public function testDecrementMethod(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
        $builder = $qb->conflictBuilder();

        $sql = $builder->decrement('balance', 10)
            ->build()
        ;

        Assert::same($sql, '`balance` = `balance` - 10');
    }

    public function testSetNullMethod(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
        $builder = $qb->conflictBuilder();

        $sql = $builder->setNull('name')
            ->build()
        ;

        Assert::same($sql, '`name` = NULL');
    }

    public function testSqlFunctionMethod(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
        $builder = $qb->conflictBuilder();

        $sql = $builder->sqlFunction('updated_at', 'NOW()')
            ->build()
        ;

        Assert::same($sql, '`updated_at` = NOW()');
    }

    public function testMultipleUpdateOperations(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
        $builder = $qb->conflictBuilder();

        $sql = $builder->set('name', 'Updated')
            ->increment('views', 1)
            ->sqlFunction('updated_at', 'NOW()')
            ->build()
        ;

        Assert::same($sql, '`name` = \'Updated\', `views` = `views` + 1, `updated_at` = NOW()');
    }
}
