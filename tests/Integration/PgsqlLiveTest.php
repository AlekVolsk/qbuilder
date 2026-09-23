<?php

declare(strict_types=1);

namespace QBuilder\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;

/**
 * Executes generated SQL on a live PostgreSQL server.
 *
 * Runs only when QBUILDER_TEST_PGSQL_DSN is set; QBUILDER_TEST_PGSQL_USER and
 * QBUILDER_TEST_PGSQL_PASSWORD are optional. Uses temporary tables only.
 *
 * @internal
 *
 * @coversNothing
 */
#[RequiresPhpExtension('pdo_pgsql')]
final class PgsqlLiveTest extends TestCase
{
    private const string MODE_DEFAULT = 'SET standard_conforming_strings = on';

    private const string MODE_LEGACY_STRINGS = 'SET standard_conforming_strings = off';

    #[DataProvider('provideStringModes')]
    public function testQuotePayloadDoesNotBreakOutOfLiteral(string $stringMode): void
    {
        $pdo = $this->connection($stringMode);
        $sql = $this->builder()->select('name')->from('t')
            ->where()->eq('name', "a\\') OR 1=1 -- ")->end()
            ->build()
        ;

        self::assertSame([], $this->column($pdo, $sql));
    }

    #[DataProvider('provideStringModes')]
    public function testLikeFindsLiteralSearchString(string $stringMode): void
    {
        $pdo = $this->connection($stringMode);

        $percent = $this->builder()->select('name')->from('t')
            ->where()->like('name', '50%', QbConsts::LIKE_RIGHT)->end()
            ->build()
        ;
        $underscore = $this->builder()->select('name')->from('t')
            ->where()->like('name', 'a_b')->end()
            ->build()
        ;

        self::assertSame(['50%'], $this->column($pdo, $percent));
        self::assertSame(['a_b'], $this->column($pdo, $underscore));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideStringModes(): iterable
    {
        yield 'standard_conforming_strings on' => [self::MODE_DEFAULT];

        yield 'standard_conforming_strings off' => [self::MODE_LEGACY_STRINGS];
    }

    /**
     * @param non-empty-string $value
     */
    #[DataProvider('provideStringValueRoundTripInBothStringModesCases')]
    public function testStringValueRoundTripInBothStringModes(string $value): void
    {
        foreach ([self::MODE_DEFAULT, self::MODE_LEGACY_STRINGS] as $stringMode) {
            $pdo = $this->connection($stringMode);
            $pdo->exec($this->builder()->insert('t')->insertRow(['name' => $value])->build());

            $sql = $this->builder()->select('name')->from('t')->where()->eq('name', $value)->end()->build();

            self::assertSame([$value], $this->column($pdo, $sql), $stringMode);
        }
    }

    /**
     * @return iterable<string, array{non-empty-string}>
     */
    public static function provideStringValueRoundTripInBothStringModesCases(): iterable
    {
        yield 'quote' => ["O'Neil"];

        yield 'backslash' => ['C:\dir\\'];

        yield 'backslash before quote' => ["a\\'b"];

        yield 'doubled quotes' => ["x''y"];

        yield 'control characters' => ["a\nb\rc\td\x1ae"];

        yield 'unicode' => ['Привет, 世界'];
    }

    public function testNumericLookingStringsAndBoolAreStoredAsIs(): void
    {
        $pdo = $this->connection(self::MODE_DEFAULT);
        $pdo->exec('CREATE TEMPORARY TABLE org (inn VARCHAR(20), code VARCHAR(20), active BOOLEAN, qty INT)');
        $pdo->exec(
            $this->builder()->insert('org')
                ->insertRow(['inn' => '0123456789', 'code' => '1e3', 'active' => false, 'qty' => 5])
                ->build()
        );

        $statement = $pdo->query('SELECT inn, code, active, qty FROM org');
        self::assertNotFalse($statement);

        self::assertSame(
            ['inn' => '0123456789', 'code' => '1e3', 'active' => false, 'qty' => 5],
            $statement->fetch(\PDO::FETCH_ASSOC)
        );
    }

    private function connection(string $stringMode): \PDO
    {
        $dsn = getenv('QBUILDER_TEST_PGSQL_DSN');

        if (false === $dsn || '' === $dsn) {
            self::markTestSkipped('QBUILDER_TEST_PGSQL_DSN is not set');
        }

        $user = getenv('QBUILDER_TEST_PGSQL_USER');
        $password = getenv('QBUILDER_TEST_PGSQL_PASSWORD');

        $pdo = new \PDO(
            $dsn,
            false === $user ? null : $user,
            false === $password ? null : $password,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        $pdo->exec($stringMode);
        $pdo->exec('CREATE TEMPORARY TABLE t (name VARCHAR(100))');

        foreach (['zzz', '50%', '5000', 'a_b', 'axb'] as $name) {
            $pdo->exec($this->builder()->insert('t')->insertRow(['name' => $name])->build());
        }

        return $pdo;
    }

    private function builder(): QueryBuilder
    {
        return new QueryBuilder(QbConsts::DRIVER_PGSQL);
    }

    /**
     * @return list<string>
     */
    private function column(\PDO $pdo, string $sql): array
    {
        $statement = $pdo->query($sql);
        self::assertNotFalse($statement);

        $values = [];

        foreach ($statement->fetchAll(\PDO::FETCH_COLUMN) as $value) {
            self::assertIsString($value);
            $values[] = $value;
        }

        return $values;
    }
}
