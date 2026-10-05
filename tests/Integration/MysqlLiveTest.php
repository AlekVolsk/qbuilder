<?php

declare(strict_types=1);

namespace QBuilder\Tests\Integration;

use QBuilder\QbConsts;
use QBuilder\QueryBuilder;
use Testo\Assert;
use Testo\Core\Exception\SkipTest;
use Testo\Data\DataProvider;
use Testo\Test;

/**
 * Executes generated SQL on a live MySQL/MariaDB server.
 *
 * Runs only when QBUILDER_TEST_MYSQL_DSN is set; QBUILDER_TEST_MYSQL_USER and
 * QBUILDER_TEST_MYSQL_PASSWORD are optional. Uses temporary tables only.
 *
 * @internal
 */
#[Test]
final class MysqlLiveTest
{
    private const string MODE_DEFAULT = 'SET SESSION sql_mode = DEFAULT';

    private const string MODE_NO_BACKSLASH_ESCAPES
        = "SET SESSION sql_mode = CONCAT(@@GLOBAL.sql_mode, ',NO_BACKSLASH_ESCAPES')";

    #[DataProvider('provideSqlModes')]
    public function testQuotePayloadDoesNotBreakOutOfLiteral(string $sqlMode): void
    {
        $pdo = $this->connection($sqlMode);
        $sql = $this->builder()->select('name')->from('t')
            ->where()->eq('name', "a\\') OR 1=1 -- ")->end()
            ->build()
        ;

        Assert::same($this->column($pdo, $sql), []);
    }

    #[DataProvider('provideSqlModes')]
    public function testLikeFindsLiteralSearchString(string $sqlMode): void
    {
        $pdo = $this->connection($sqlMode);

        $percent = $this->builder()->select('name')->from('t')
            ->where()->like('name', '50%', QbConsts::LIKE_RIGHT)->end()
            ->build()
        ;
        $underscore = $this->builder()->select('name')->from('t')
            ->where()->like('name', 'a_b')->end()
            ->build()
        ;

        Assert::same($this->column($pdo, $percent), ['50%']);
        Assert::same($this->column($pdo, $underscore), ['a_b']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideSqlModes(): iterable
    {
        yield 'default' => [self::MODE_DEFAULT];

        yield 'NO_BACKSLASH_ESCAPES' => [self::MODE_NO_BACKSLASH_ESCAPES];
    }

    /**
     * @param non-empty-string $value
     */
    #[DataProvider('provideStringValueRoundTripInDefaultModeCases')]
    public function testStringValueRoundTripInDefaultMode(string $value): void
    {
        $pdo = $this->connection(self::MODE_DEFAULT);
        $pdo->exec($this->builder()->insert('t')->insertRow(['name' => $value])->build());

        $sql = $this->builder()->select('name')->from('t')->where()->eq('name', $value)->end()->build();

        Assert::same($this->column($pdo, $sql), [$value]);
    }

    /**
     * @return iterable<string, array{non-empty-string}>
     */
    public static function provideStringValueRoundTripInDefaultModeCases(): iterable
    {
        yield 'quote' => ["O'Neil"];

        yield 'backslash' => ['C:\dir\\'];

        yield 'backslash before quote' => ["a\\'b"];

        yield 'doubled quotes' => ["x''y"];

        yield 'control characters' => ["a\nb\rc\td\x1ae"];

        yield 'unicode' => ['Привет, 世界'];

        yield '4-byte emoji' => ['ok 😀'];

        yield 'NUL byte' => ["a\0b"];
    }

    public function testNumericLookingStringsAndBoolAreStoredAsIs(): void
    {
        $pdo = $this->connection(self::MODE_DEFAULT);
        $pdo->exec('CREATE TEMPORARY TABLE org (inn VARCHAR(20), code VARCHAR(20), active TINYINT(1), qty INT)');
        $pdo->exec(
            $this->builder()->insert('org')
                ->insertRow(['inn' => '0123456789', 'code' => '1e3', 'active' => false, 'qty' => 5])
                ->build()
        );

        $statement = $pdo->query('SELECT inn, code, active, qty FROM org');
        Assert::instanceOf($statement, \PDOStatement::class);

        Assert::same(
            $statement->fetch(\PDO::FETCH_ASSOC),
            ['inn' => '0123456789', 'code' => '1e3', 'active' => 0, 'qty' => 5]
        );
    }

    public function testUpsertWithServerVersionRunsWithoutWarnings(): void
    {
        $pdo = $this->connection(self::MODE_DEFAULT);
        $version = $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);
        Assert::string($version);

        Assert::same($this->upsert($pdo, $version), ['b', 1]);
        Assert::same($this->warnings($pdo), []);
    }

    public function testUpsertWithUnknownVersionUsesValuesFunction(): void
    {
        $pdo = $this->connection(self::MODE_DEFAULT);

        Assert::same($this->upsert($pdo, ''), ['b', 1]);
    }

    public function testIndexHintsAreAppliedByOptimizer(): void
    {
        $pdo = $this->connection(self::MODE_DEFAULT);
        $pdo->exec('CREATE TEMPORARY TABLE h (id INT PRIMARY KEY, a INT, b INT, KEY idx_a (a), KEY idx_b (b))');
        $pdo->exec('INSERT INTO h VALUES (1, 1, 1), (2, 2, 2), (3, 3, 3)');

        $forced = $this->builder()->select('id')->from('h')->forceIndex('idx_b')
            ->where()->eq('a', 1)->and()->eq('b', 1)->end()
            ->build()
        ;
        $primary = $this->builder()->select('id')->from('h')->useIndex('PRIMARY')
            ->where()->eq('id', 1)->end()
            ->build()
        ;
        $ignored = $this->builder()->select('id')->from('h')->ignoreIndex(['idx_a', 'idx_b'])
            ->where()->eq('a', 1)->end()
            ->build()
        ;

        Assert::same($this->explainKey($pdo, $forced), 'idx_b');
        Assert::same($this->explainKey($pdo, $primary), 'PRIMARY');
        Assert::null($this->explainKey($pdo, $ignored));
    }

    private function explainKey(\PDO $pdo, string $sql): ?string
    {
        $statement = $pdo->query('EXPLAIN ' . $sql);
        Assert::instanceOf($statement, \PDOStatement::class);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        Assert::array($row);
        $key = $row['key'] ?? null;
        Assert::true(null === $key || \is_string($key));

        return $key;
    }

    /**
     * @return array{string, int}
     */
    private function upsert(\PDO $pdo, string $serverVersion): array
    {
        $pdo->exec('CREATE TEMPORARY TABLE u (id INT PRIMARY KEY, email VARCHAR(20), n INT NOT NULL DEFAULT 0)');
        $pdo->exec("INSERT INTO u (id, email) VALUES (1, 'a')");

        $qb = $this->builder()->setServerVersion($serverVersion);
        $conflict = $qb->conflictBuilder()->excluded('email')->increment('n');
        $pdo->exec($qb->insert('u')->insertRow(['id' => 1, 'email' => 'b'])->insertConflictHandler($conflict)->build());

        $statement = $pdo->query('SELECT email, n FROM u WHERE id = 1');
        Assert::instanceOf($statement, \PDOStatement::class);
        $row = $statement->fetch(\PDO::FETCH_NUM);
        Assert::array($row);

        [$email, $counter] = $row;
        Assert::string($email);
        Assert::int($counter);

        return [$email, $counter];
    }

    /**
     * @return list<mixed>
     */
    private function warnings(\PDO $pdo): array
    {
        $statement = $pdo->query('SHOW WARNINGS');
        Assert::instanceOf($statement, \PDOStatement::class);

        return array_values($statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function connection(string $sqlMode): \PDO
    {
        if (! \extension_loaded('pdo_mysql')) {
            throw new SkipTest('pdo_mysql extension is not loaded');
        }

        $dsn = getenv('QBUILDER_TEST_MYSQL_DSN');

        if (false === $dsn || '' === $dsn) {
            throw new SkipTest('QBUILDER_TEST_MYSQL_DSN is not set');
        }

        $user = getenv('QBUILDER_TEST_MYSQL_USER');
        $password = getenv('QBUILDER_TEST_MYSQL_PASSWORD');

        $pdo = new \PDO(
            $dsn,
            false === $user ? null : $user,
            false === $password ? null : $password,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        $pdo->exec($sqlMode);
        $pdo->exec('CREATE TEMPORARY TABLE t (name VARCHAR(100)) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin');

        foreach (['zzz', '50%', '5000', 'a_b', 'axb'] as $name) {
            $pdo->exec($this->builder()->insert('t')->insertRow(['name' => $name])->build());
        }

        return $pdo;
    }

    private function builder(): QueryBuilder
    {
        return new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
    }

    /**
     * @return list<string>
     */
    private function column(\PDO $pdo, string $sql): array
    {
        $statement = $pdo->query($sql);
        Assert::instanceOf($statement, \PDOStatement::class);

        $values = [];

        foreach ($statement->fetchAll(\PDO::FETCH_COLUMN) as $value) {
            Assert::string($value);
            $values[] = $value;
        }

        return $values;
    }
}
