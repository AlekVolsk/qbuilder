<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QBuilder\Services\SqlCompactor;

/**
 * @internal
 *
 * @coversNothing
 */
final class SqlCompactorTest extends TestCase
{
    public function testCompactRemovesExtraWhitespace(): void
    {
        $compactor = new SqlCompactor();
        $sql = 'SELECT   *   FROM    users   WHERE   id   =   1';
        $result = $compactor->compact($sql);

        self::assertSame('SELECT * FROM users WHERE id = 1', $result);
    }

    public function testCompactPreservesSpacesInSingleQuotedStrings(): void
    {
        $compactor = new SqlCompactor();
        $sql = "SELECT * FROM users WHERE name = 'John   Doe'";
        $result = $compactor->compact($sql);

        self::assertSame("SELECT * FROM users WHERE name = 'John   Doe'", $result);
    }

    public function testCompactPreservesSpacesInDoubleQuotedStrings(): void
    {
        $compactor = new SqlCompactor();
        $sql = 'SELECT * FROM users WHERE name = "John   Doe"';
        $result = $compactor->compact($sql);

        self::assertSame('SELECT * FROM users WHERE name = "John   Doe"', $result);
    }

    public function testCompactHandlesEscapedSingleQuotes(): void
    {
        $compactor = new SqlCompactor();
        $sql = "SELECT * FROM users WHERE name = 'John''s Name'";
        $result = $compactor->compact($sql);

        self::assertSame("SELECT * FROM users WHERE name = 'John''s Name'", $result);
    }

    public function testCompactHandlesEscapedDoubleQuotes(): void
    {
        $compactor = new SqlCompactor();
        $sql = 'SELECT * FROM users WHERE name = "John""s Name"';
        $result = $compactor->compact($sql);

        self::assertSame('SELECT * FROM users WHERE name = "John""s Name"', $result);
    }

    public function testCompactHandlesNewlinesInStrings(): void
    {
        $compactor = new SqlCompactor();
        $sql = "SELECT * FROM users WHERE description = 'Line 1\nLine 2'";
        $result = $compactor->compact($sql);

        self::assertSame("SELECT * FROM users WHERE description = 'Line 1\nLine 2'", $result);
    }

    public function testCompactHandlesMultipleStringLiterals(): void
    {
        $compactor = new SqlCompactor();
        $sql = "SELECT * FROM users WHERE name = 'John' AND city = 'New York'";
        $result = $compactor->compact($sql);

        self::assertSame("SELECT * FROM users WHERE name = 'John' AND city = 'New York'", $result);
    }

    public function testCompactHandlesMixedQuotes(): void
    {
        $compactor = new SqlCompactor();
        $sql = 'SELECT * FROM users WHERE name = "John" AND city = \'New York\'';
        $result = $compactor->compact($sql);

        self::assertSame('SELECT * FROM users WHERE name = "John" AND city = \'New York\'', $result);
    }

    public function testCompactHandlesEmptyString(): void
    {
        $compactor = new SqlCompactor();
        $result = $compactor->compact('');

        self::assertSame('', $result);
    }

    public function testCompactHandlesOnlyWhitespace(): void
    {
        $compactor = new SqlCompactor();
        $result = $compactor->compact('   ');

        self::assertSame('', $result);
    }

    public function testCompactHandlesTabsAndNewlines(): void
    {
        $compactor = new SqlCompactor();
        $sql = "SELECT\t*\nFROM\n\tusers\nWHERE\n\tid = 1";
        $result = $compactor->compact($sql);

        self::assertSame('SELECT * FROM users WHERE id = 1', $result);
    }

    public function testCompactHandlesComplexQuery(): void
    {
        $compactor = new SqlCompactor();
        $sql = "SELECT   u.id,   u.name   FROM   users   u   WHERE   u.name   =   'John   Doe'   "
            .'AND   u.city   =   "New   York"';
        $result = $compactor->compact($sql);

        self::assertSame(
            "SELECT u.id, u.name FROM users u WHERE u.name = 'John   Doe' AND u.city = \"New   York\"",
            $result
        );
    }

    public function testCompactHandlesStringAtBeginning(): void
    {
        $compactor = new SqlCompactor();
        $sql = "'test'   SELECT   *   FROM   users";
        $result = $compactor->compact($sql);

        self::assertSame("'test' SELECT * FROM users", $result);
    }

    public function testCompactHandlesStringAtEnd(): void
    {
        $compactor = new SqlCompactor();
        $sql = "SELECT   *   FROM   users   WHERE   name   =   'test'";
        $result = $compactor->compact($sql);

        self::assertSame("SELECT * FROM users WHERE name = 'test'", $result);
    }

    public function testCompactHandlesConsecutiveEscapedQuotes(): void
    {
        $compactor = new SqlCompactor();
        $sql = "SELECT * FROM users WHERE name = 'John''''s Name'";
        $result = $compactor->compact($sql);

        self::assertSame("SELECT * FROM users WHERE name = 'John''''s Name'", $result);
    }

    #[DataProvider('provideCompactHandlesMultilineStringsCases')]
    public function testCompactHandlesMultilineStrings(string $sql, string $expected): void
    {
        $compactor = new SqlCompactor();
        $result = $compactor->compact($sql);

        self::assertSame($expected, $result);
    }

    /**
     * @return iterable<string, array<string>>
     */
    public static function provideCompactHandlesMultilineStringsCases(): iterable
    {
        yield 'single quote multiline' => [
            "SELECT * FROM users WHERE description = 'Line 1\nLine 2\nLine 3'",
            "SELECT * FROM users WHERE description = 'Line 1\nLine 2\nLine 3'",
        ];
        yield 'double quote multiline' => [
            'SELECT * FROM users WHERE description = "Line 1\nLine 2\nLine 3"',
            'SELECT * FROM users WHERE description = "Line 1\nLine 2\nLine 3"',
        ];
    }

    public function testCompactTrimsResult(): void
    {
        $compactor = new SqlCompactor();
        $sql = '   SELECT   *   FROM   users   ';
        $result = $compactor->compact($sql);

        self::assertSame('SELECT * FROM users', $result);
        self::assertStringStartsNotWith(' ', $result);
        self::assertStringEndsNotWith(' ', $result);
    }

    public function testCompactHandlesUnclosedString(): void
    {
        $compactor = new SqlCompactor();
        $sql = "SELECT * FROM users WHERE name = 'John";
        $result = $compactor->compact($sql);

        self::assertStringContainsString("'John", $result);
    }

    public function testCompactHandlesQuoteAtEndOfString(): void
    {
        $compactor = new SqlCompactor();
        $sql = "SELECT * FROM users WHERE name = 'John'";
        $result = $compactor->compact($sql);

        self::assertSame("SELECT * FROM users WHERE name = 'John'", $result);
    }
}
