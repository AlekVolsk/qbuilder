<?php

declare(strict_types=1);

namespace QBuilder\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use QBuilder\Condition\ConditionBy;
use QBuilder\Condition\ConditionJoin;
use QBuilder\Condition\Field;
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;

/**
 * Executes generated SQL on an in-memory SQLite database.
 *
 * @internal
 *
 * @coversNothing
 */
#[RequiresPhpExtension('pdo_sqlite')]
final class SqliteLiveTest extends TestCase
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

        self::assertSame($expected, $this->column($sql));
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

        self::assertNotContains('50%', $this->column($sql));
        self::assertContains('5000', $this->column($sql));
    }

    public function testStringValuesRoundTrip(): void
    {
        $sql = $this->builder()->select('name')->from('t')
            ->where()->in('name', ["O'Neil", 'C:\dir'])->end()
            ->build()
        ;

        self::assertEqualsCanonicalizing(["O'Neil", 'C:\dir'], $this->column($sql));
    }

    public function testExistsWithSelectOne(): void
    {
        $qb = $this->builder();
        $exists = $qb->subQuery()->select('1')->from('t')->where()->eq('name', 'a_b')->end();
        $sql = $qb->select('name')->from('t')
            ->where()->exists($exists)->and()->eq('name', 'axb')->end()
            ->build()
        ;

        self::assertSame(['axb'], $this->column($sql));
    }

    public function testJoinOnTablesWithSameColumnName(): void
    {
        $pdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
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
        self::assertNotFalse($statement);

        self::assertSame(['Bob'], $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    private function seededConnection(): \PDO
    {
        $pdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
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
        self::assertNotFalse($statement);

        $values = [];

        foreach ($statement->fetchAll(\PDO::FETCH_COLUMN) as $value) {
            self::assertIsString($value);
            $values[] = $value;
        }

        return $values;
    }
}
