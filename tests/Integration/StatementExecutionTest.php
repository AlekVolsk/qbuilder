<?php

declare(strict_types=1);

namespace QBuilder\Tests\Integration;

use QBuilder\Builder\UnionBuilder;
use QBuilder\Condition\ConditionBy;
use QBuilder\Condition\ConditionJoin;
use QBuilder\Condition\Field;
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;
use Testo\Assert;
use Testo\Core\Exception\SkipTest;
use Testo\Data\DataProvider;
use Testo\Test;

/**
 * Executes statements of every query type on live MySQL, PostgreSQL and SQLite.
 *
 * MySQL and PostgreSQL run only when QBUILDER_TEST_MYSQL_DSN / QBUILDER_TEST_PGSQL_DSN are set
 * (plus optional _USER and _PASSWORD). Every test creates its own tables and drops them afterwards.
 *
 * @internal
 */
#[Test]
final class StatementExecutionTest
{
    private const string MYSQL = 'mysql';
    private const string PGSQL = 'pgsql';
    private const string SQLITE = 'sqlite';

    #[DataProvider('provideAllEngines')]
    public function testMultiRowInsertAndInsertFromSelect(string $engine): void
    {
        $this->withTables($engine, [
            'qbt_src' => 'id INT, s VARCHAR(20)',
            'qbt_dst' => 'id INT, s VARCHAR(20)',
        ], function (\PDO $pdo, string $driver): void {
            $pdo->exec(
                (new QueryBuilder($driver))->insert('qbt_src')
                    ->insertRow(['id' => 1, 's' => 'a'])
                    ->insertRow(['id' => 2, 's' => 'b'])
                    ->build()
            );

            $qb = new QueryBuilder($driver);
            $source = $qb->subQuery()->select('id', 's')->from('qbt_src')->where()->gt('id', 1)->end();
            $pdo->exec($qb->insert('qbt_dst')->insertFrom($source, ['id', 's'])->build());

            Assert::same($this->rows($pdo, 'SELECT id, s FROM qbt_src ORDER BY id'), [['1', 'a'], ['2', 'b']]);
            Assert::same($this->rows($pdo, 'SELECT id, s FROM qbt_dst'), [['2', 'b']]);
        });
    }

    #[DataProvider('provideAllEngines')]
    public function testUpdateAndDeleteByCondition(string $engine): void
    {
        $this->withTables($engine, ['qbt_d' => 'id INT, v INT'], function (\PDO $pdo, string $driver): void {
            $pdo->exec('INSERT INTO qbt_d VALUES (1, 1), (2, 2), (3, 3)');

            $pdo->exec(
                (new QueryBuilder($driver))->update('qbt_d')->updateRow(['v' => 10])
                    ->where()->eq('id', 1)->end()
                    ->build()
            );
            $pdo->exec((new QueryBuilder($driver))->delete('qbt_d')->where()->gt('id', 2)->end()->build());

            Assert::same($this->rows($pdo, 'SELECT id, v FROM qbt_d ORDER BY id'), [['1', '10'], ['2', '2']]);
        });
    }

    #[DataProvider('provideAllEngines')]
    public function testUpdateAndDeleteFromSelect(string $engine): void
    {
        $this->withTables($engine, [
            'qbt_a' => 'id INT, v INT',
            'qbt_b' => 'id INT',
        ], function (\PDO $pdo, string $driver): void {
            $pdo->exec('INSERT INTO qbt_a VALUES (1, 0), (2, 0), (3, 0)');
            $pdo->exec('INSERT INTO qbt_b VALUES (2), (3)');

            $qb = new QueryBuilder($driver);
            $pdo->exec(
                $qb->updateFromSelect('qbt_a', $qb->subQuery()->select('id')->from('qbt_b'), ['v' => 5])->build()
            );

            Assert::same(
                $this->rows($pdo, 'SELECT id, v FROM qbt_a ORDER BY id'),
                [['1', '0'], ['2', '5'], ['3', '5']]
            );

            $qb = new QueryBuilder($driver);
            $source = $qb->subQuery()->select('id')->from('qbt_b')->where()->eq('id', 3)->end();
            $pdo->exec($qb->deleteFromSelect('qbt_a', $source)->build());

            Assert::same($this->rows($pdo, 'SELECT id FROM qbt_a ORDER BY id'), [['1'], ['2']]);
        });
    }

    #[DataProvider('provideConflictEngines')]
    public function testUpsertReferencesCurrentAndProposedValues(string $engine): void
    {
        $this->withTables(
            $engine,
            ['qbt_u' => 'id INT PRIMARY KEY, email VARCHAR(20), n INT NOT NULL, m INT NOT NULL'],
            function (\PDO $pdo, string $driver): void {
                $pdo->exec("INSERT INTO qbt_u VALUES (1, 'a', 0, 10)");

                $qb = new QueryBuilder($driver);
                $conflict = $qb->conflictBuilder()->conflictTarget(['id'])
                    ->excluded('email')
                    ->increment('n')
                    ->decrement('m', 3)
                ;
                $pdo->exec(
                    $qb->insert('qbt_u')->insertRow(['id' => 1, 'email' => 'b', 'n' => 0, 'm' => 0])
                        ->insertConflictHandler($conflict)
                        ->build()
                );

                Assert::same($this->rows($pdo, 'SELECT email, n, m FROM qbt_u'), [['b', '1', '7']]);
            }
        );
    }

    #[DataProvider('provideAllEngines')]
    public function testUnionAllWithOrderByLimitAndOffset(string $engine): void
    {
        $this->withTables($engine, ['qbt_x' => 'id INT'], function (\PDO $pdo, string $driver): void {
            $pdo->exec('INSERT INTO qbt_x VALUES (1), (2), (3)');

            $qb = new QueryBuilder($driver);
            $sql = (new UnionBuilder($qb))
                ->add($qb->subQuery()->select('id')->from('qbt_x')->where()->lt('id', 3)->end())
                ->add($qb->subQuery()->select('id')->from('qbt_x')->where()->gt('id', 1)->end())
                ->all()
                ->orderBy(ConditionBy::orderBy()->desc('id'))
                ->limit(2, 1)
                ->build()
            ;

            Assert::same($this->rows($pdo, $sql), [['2'], ['2']]);
        });
    }

    #[DataProvider('provideAllEngines')]
    public function testRecursiveCteWalksTheWholeTree(string $engine): void
    {
        $this->withTables($engine, ['qbt_tree' => 'id INT, parent_id INT'], function (\PDO $pdo, string $driver): void {
            $pdo->exec('INSERT INTO qbt_tree VALUES (1, NULL), (2, 1), (3, 2), (4, NULL)');

            $qb = new QueryBuilder($driver);
            $base = $qb->subQuery()->select('id', 'parent_id')->from('qbt_tree')->where()->eq('id', 1)->end();
            $recursive = $qb->subQuery()
                ->select(Field::set('id', 't'), Field::set('parent_id', 't'))
                ->from('qbt_tree', 't')
                ->innerJoin('RecursiveCTE', 'r', ConditionJoin::create($qb, 'id', 'parent_id', 't'))
            ;
            $final = $qb->subQuery()->select('id')->from('RecursiveCTE')->orderBy(ConditionBy::orderBy()->asc('id'));

            $sql = $qb->recursiveCte()->baseQuery($base)->recursiveQuery($recursive)->finalSelect($final)->build();

            Assert::same($this->rows($pdo, $sql), [['1'], ['2'], ['3']]);
        });
    }

    #[DataProvider('provideAllEngines')]
    public function testSelectLimitWithOffset(string $engine): void
    {
        $this->withTables($engine, ['qbt_l' => 'id INT'], function (\PDO $pdo, string $driver): void {
            $pdo->exec('INSERT INTO qbt_l VALUES (1), (2), (3), (4)');

            $sql = (new QueryBuilder($driver))->select('id')->from('qbt_l')
                ->orderBy(ConditionBy::orderBy()->asc('id'))
                ->limit(2, 1)
                ->build()
            ;

            Assert::same($this->rows($pdo, $sql), [['2'], ['3']]);
        });
    }

    public function testPgsqlLimitWithTiesKeepsTiedRows(): void
    {
        $this->withTables(self::PGSQL, ['qbt_w' => 'id INT, s INT'], function (\PDO $pdo, string $driver): void {
            $pdo->exec('INSERT INTO qbt_w VALUES (1, 10), (2, 10), (3, 5)');

            $sql = (new QueryBuilder($driver))->select('id')->from('qbt_w')
                ->orderBy(ConditionBy::orderBy()->desc('s'))
                ->limitWithTies(1)
                ->build()
            ;

            $ids = $this->rows($pdo, $sql);
            sort($ids);

            Assert::same($ids, [['1'], ['2']]);
        });
    }

    #[DataProvider('provideAllEngines')]
    public function testGroupByWithHavingAggregate(string $engine): void
    {
        $this->withTables($engine, ['qbt_g' => 'c VARCHAR(5), v INT'], function (\PDO $pdo, string $driver): void {
            $pdo->exec("INSERT INTO qbt_g VALUES ('a', 1), ('a', 2), ('b', 1)");

            $sql = (new QueryBuilder($driver))->select('c', Field::set('COUNT(*)', '', 'cnt'))->from('qbt_g')
                ->groupBy(ConditionBy::groupBy()->add('c'))
                ->having()->gt(Field::set('COUNT(*)'), 1)->end()
                ->build()
            ;

            Assert::same($this->rows($pdo, $sql), [['a', '2']]);
        });
    }

    #[DataProvider('provideAllEngines')]
    public function testSelectFromDerivedTable(string $engine): void
    {
        $this->withTables($engine, ['qbt_dt' => 'id INT'], function (\PDO $pdo, string $driver): void {
            $pdo->exec('INSERT INTO qbt_dt VALUES (1), (2), (3)');

            $qb = new QueryBuilder($driver);
            $sql = $qb->select('id')
                ->from($qb->subQuery()->select('id')->from('qbt_dt')->where()->gt('id', 1)->end(), 'q')
                ->orderBy(ConditionBy::orderBy()->asc('id'))
                ->build()
            ;

            Assert::same($this->rows($pdo, $sql), [['2'], ['3']]);
        });
    }

    #[DataProvider('provideFullJoinEngines')]
    public function testFullJoinKeepsUnmatchedRowsOfBothSides(string $engine): void
    {
        $this->withTables($engine, [
            'qbt_fa' => 'id INT',
            'qbt_fb' => 'id INT',
        ], function (\PDO $pdo, string $driver): void {
            $pdo->exec('INSERT INTO qbt_fa VALUES (1), (2)');
            $pdo->exec('INSERT INTO qbt_fb VALUES (2), (3)');

            $qb = new QueryBuilder($driver);
            $sql = $qb->select(Field::set('id', 'a', 'a_id'), Field::set('id', 'b', 'b_id'))->from('qbt_fa', 'a')
                ->fullJoin('qbt_fb', 'b', ConditionJoin::create($qb, 'id', 'id', 'a'))
                ->build()
            ;

            $rows = $this->rows($pdo, $sql);
            usort(
                $rows,
                static fn (array $x, array $y): int => [$x[0] ?? '9', $x[1] ?? '9'] <=> [$y[0] ?? '9', $y[1] ?? '9']
            );

            Assert::same($rows, [['1', null], ['2', '2'], [null, '3']]);
        });
    }

    #[DataProvider('provideAllEngines')]
    public function testReservedWordsAsIdentifiers(string $engine): void
    {
        $quote = self::MYSQL === $engine ? '`' : '"';
        $this->withTables(
            $engine,
            ["{$quote}order{$quote}" => "{$quote}group{$quote} INT, {$quote}select{$quote} INT"],
            function (\PDO $pdo, string $driver): void {
                $pdo->exec(
                    (new QueryBuilder($driver))->insert('order')->insertRow(['group' => 1, 'select' => 2])->build()
                );

                $sql = (new QueryBuilder($driver))->select('group')->from('order')
                    ->where()->eq('select', 2)->end()
                    ->orderBy(ConditionBy::orderBy()->asc('group'))
                    ->build()
                ;

                Assert::same($this->rows($pdo, $sql), [['1']]);
            }
        );
    }

    #[DataProvider('provideAllEngines')]
    public function testNumericExtremesRoundTrip(string $engine): void
    {
        $this->withTables(
            $engine,
            ['qbt_f' => 'a BIGINT, b DOUBLE PRECISION, c DOUBLE PRECISION, d INT'],
            function (\PDO $pdo, string $driver): void {
                $pdo->exec(
                    (new QueryBuilder($driver))->insert('qbt_f')
                        ->insertRow(['a' => PHP_INT_MAX, 'b' => 1.0E+25, 'c' => -0.0, 'd' => -42])
                        ->build()
                );

                $row = $this->rows($pdo, 'SELECT a, b, c, d FROM qbt_f')[0];

                Assert::same($row[0], (string) PHP_INT_MAX);
                Assert::same((float) $row[1], 1.0E+25);
                Assert::same((float) $row[2], 0.0);
                Assert::same($row[3], '-42');
            }
        );
    }

    #[DataProvider('provideAllEngines')]
    public function testCompactModeKeepsCommentLikeLiteral(string $engine): void
    {
        $this->withTables($engine, ['qbt_ck' => 's VARCHAR(30)'], function (\PDO $pdo, string $driver): void {
            $value = "a  -- b\n  /* c */";
            $pdo->exec((new QueryBuilder($driver))->insert('qbt_ck')->insertRow(['s' => $value])->build(true));

            $sql = (new QueryBuilder($driver))->select('s')->from('qbt_ck')
                ->where()->eq('s', $value)->end()
                ->build(true)
            ;

            Assert::same($this->rows($pdo, $sql), [[$value]]);
        });
    }

    #[DataProvider('provideAllEngines')]
    public function testPreparedStatementIgnoresPlaceholderInsideLiteral(string $engine): void
    {
        $this->withTables($engine, ['qbt_pq' => 's VARCHAR(20)'], static function (\PDO $pdo, string $driver): void {
            $value = "a\\'? :b";
            $pdo->exec((new QueryBuilder($driver))->insert('qbt_pq')->insertRow(['s' => $value])->build());

            $statement = $pdo->prepare(
                (new QueryBuilder($driver))->select('s')->from('qbt_pq')->where()->eq('s', $value)->end()->build()
            );
            $statement->execute();

            Assert::same($statement->fetchAll(\PDO::FETCH_COLUMN), [$value]);
        });
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideAllEngines(): iterable
    {
        yield 'MySQL' => [self::MYSQL];
        yield 'PostgreSQL' => [self::PGSQL];
        yield 'SQLite' => [self::SQLITE];
    }

    /**
     * MySQL upsert has its own server-version cases in MysqlLiveTest.
     *
     * @return iterable<string, array{string}>
     */
    public static function provideConflictEngines(): iterable
    {
        yield 'PostgreSQL' => [self::PGSQL];
        yield 'SQLite' => [self::SQLITE];
    }

    /**
     * MySQL has no FULL JOIN; the builder rejects it before execution.
     *
     * @return iterable<string, array{string}>
     */
    public static function provideFullJoinEngines(): iterable
    {
        yield 'PostgreSQL' => [self::PGSQL];
        yield 'SQLite' => [self::SQLITE];
    }

    /**
     * Creates the tables, runs the scenario and drops the tables even when it fails.
     *
     * @param array<string, string>        $tables   Table name (quoted if needed) => column definitions
     * @param \Closure(\PDO, string): void $scenario Receives the connection and the builder driver
     */
    private function withTables(string $engine, array $tables, \Closure $scenario): void
    {
        [$pdo, $driver] = $this->connect($engine);

        foreach (array_keys($tables) as $table) {
            $pdo->exec("DROP TABLE IF EXISTS {$table}");
        }

        try {
            foreach ($tables as $table => $columns) {
                $pdo->exec("CREATE TABLE {$table} ({$columns})");
            }

            $scenario($pdo, $driver);
        } finally {
            foreach (array_keys($tables) as $table) {
                $pdo->exec("DROP TABLE IF EXISTS {$table}");
            }
        }
    }

    /**
     * @return array{\PDO, string}
     */
    private function connect(string $engine): array
    {
        if (self::SQLITE === $engine) {
            if (! \extension_loaded('pdo_sqlite')) {
                throw new SkipTest('pdo_sqlite extension is not loaded');
            }

            return [
                new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]),
                QbConsts::DRIVER_SQLITE,
            ];
        }

        $prefix = self::MYSQL === $engine ? 'QBUILDER_TEST_MYSQL' : 'QBUILDER_TEST_PGSQL';
        $extension = self::MYSQL === $engine ? 'pdo_mysql' : 'pdo_pgsql';

        if (! \extension_loaded($extension)) {
            throw new SkipTest("{$extension} extension is not loaded");
        }

        $dsn = getenv($prefix . '_DSN');

        if (false === $dsn || '' === $dsn) {
            throw new SkipTest("{$prefix}_DSN is not set");
        }

        $user = getenv($prefix . '_USER');
        $password = getenv($prefix . '_PASSWORD');

        $pdo = new \PDO(
            $dsn,
            false === $user ? null : $user,
            false === $password ? null : $password,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );

        return [$pdo, self::MYSQL === $engine ? QbConsts::DRIVER_PDO_MYSQL : QbConsts::DRIVER_PGSQL];
    }

    /**
     * Rows with every value as a string, so the drivers' native types do not matter.
     *
     * @return list<list<?string>>
     */
    private function rows(\PDO $pdo, string $sql): array
    {
        $statement = $pdo->query($sql);
        Assert::instanceOf($statement, \PDOStatement::class);

        $rows = [];

        foreach ($statement->fetchAll(\PDO::FETCH_NUM) as $row) {
            Assert::array($row);
            $rows[] = array_values(array_map(
                static fn (mixed $value): ?string => match (true) {
                    null === $value => null,
                    \is_scalar($value) => (string) $value,
                    default => '',
                },
                $row
            ));
        }

        return $rows;
    }
}
