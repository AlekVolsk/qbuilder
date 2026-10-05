<?php

declare(strict_types=1);

namespace QBuilder\Tests\Integration;

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
 * Executes generated SQL on an in-memory SQLite database.
 *
 * @internal
 */
#[Test]
final class SqliteLiveTest
{
    /**
     * @param list<string> $expected
     */
    #[DataProvider('provideLikeFindsLiteralSearchStringCases')]
    public function testLikeFindsLiteralSearchString(string $search, string $boundary, array $expected): void
    {
        $sql = $this->builder()->select('name')->from('t')
            ->where()->like('name', $search, $boundary)->end()
            ->orderBy(ConditionBy::orderBy()->asc('name'))
            ->build()
        ;

        Assert::same($this->column($sql), $expected);
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function provideLikeFindsLiteralSearchStringCases(): iterable
    {
        yield 'percent, starts with' => ['50%', QbConsts::LIKE_RIGHT, ['50%']];

        yield 'underscore, contains' => ['a_b', QbConsts::LIKE_FULL, ['a_b']];

        yield 'escape char, contains' => ['x!y', QbConsts::LIKE_FULL, ['x!y']];

        yield 'plain, starts with' => ['50', QbConsts::LIKE_RIGHT, ['50 percent', '50%', '5000']];
    }

    public function testNotLikeExcludesLiteralSearchString(): void
    {
        $sql = $this->builder()->select('name')->from('t')
            ->where()->notLike('name', '%', QbConsts::LIKE_FULL)->end()
            ->build()
        ;

        Assert::iterable($this->column($sql))->notContains('50%');
        Assert::contains($this->column($sql), '5000');
    }

    public function testStringValuesRoundTrip(): void
    {
        $sql = $this->builder()->select('name')->from('t')
            ->where()->in('name', ["O'Neil", 'C:\dir'])->end()
            ->build()
        ;

        Assert::array($this->column($sql))->sameElementsAs(["O'Neil", 'C:\dir']);
    }

    public function testExistsWithSelectOne(): void
    {
        $qb = $this->builder();
        $exists = $qb->subQuery()->select('1')->from('t')->where()->eq('name', 'a_b')->end();
        $sql = $qb->select('name')->from('t')
            ->where()->exists($exists)->and()->eq('name', 'axb')->end()
            ->build()
        ;

        Assert::same($this->column($sql), ['axb']);
    }

    public function testJoinOnTablesWithSameColumnName(): void
    {
        $pdo = $this->connection();
        $pdo->exec('CREATE TABLE orders (id INTEGER, user_id INTEGER)');
        $pdo->exec('CREATE TABLE users (id INTEGER, name TEXT)');
        $pdo->exec("INSERT INTO orders VALUES (1, 10), (2, 20); INSERT INTO users VALUES (10, 'Ann'), (20, 'Bob')");

        $qb = $this->builder();
        $sql = $qb->select(Field::set('name', 'u'))->from('orders', 'o')
            ->innerJoin('users', 'u', ConditionJoin::create($qb, 'id', 'user_id', 'o'))
            ->where()->eq('o.id', 2)->end()
            ->build()
        ;

        $statement = $pdo->query($sql);
        Assert::instanceOf($statement, \PDOStatement::class);

        Assert::same($statement->fetchAll(\PDO::FETCH_COLUMN), ['Bob']);
    }

    private function connection(): \PDO
    {
        if (! \extension_loaded('pdo_sqlite')) {
            throw new SkipTest('pdo_sqlite extension is not loaded');
        }

        return new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    }

    private function seededConnection(): \PDO
    {
        $pdo = $this->connection();
        $pdo->exec('CREATE TABLE t (name TEXT)');

        foreach (['50%', '5000', '50 percent', 'a_b', 'axb', 'x!y', 'xzy', "O'Neil", 'C:\dir'] as $name) {
            $pdo->exec($this->builder()->insert('t')->insertRow(['name' => $name])->build());
        }

        return $pdo;
    }

    private function builder(): QueryBuilder
    {
        return new QueryBuilder(QbConsts::DRIVER_SQLITE);
    }

    /**
     * @return list<string>
     */
    private function column(string $sql): array
    {
        $statement = $this->seededConnection()->query($sql);
        Assert::instanceOf($statement, \PDOStatement::class);

        $values = [];

        foreach ($statement->fetchAll(\PDO::FETCH_COLUMN) as $value) {
            Assert::string($value);
            $values[] = $value;
        }

        return $values;
    }
}
