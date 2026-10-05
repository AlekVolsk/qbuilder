<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use QBuilder\QbConsts;
use QBuilder\QueryBuilder;
use QBuilder\Services\SqlCompactor;
use Testo\Assert;
use Testo\Data\DataProvider;
use Testo\Test;

/**
 * @internal
 */
#[Test]
final class SqlCompactorTest
{
    public function testCompactRemovesExtraWhitespace(): void
    {
        $compactor = new SqlCompactor();
        $sql = 'SELECT   *   FROM    users   WHERE   id   =   1';
        $result = $compactor->compact($sql);

        Assert::same($result, 'SELECT * FROM users WHERE id = 1');
    }

    public function testCompactPreservesSpacesInSingleQuotedStrings(): void
    {
        $compactor = new SqlCompactor();
        $sql = "SELECT * FROM users WHERE name = 'John   Doe'";
        $result = $compactor->compact($sql);

        Assert::same($result, "SELECT * FROM users WHERE name = 'John   Doe'");
    }

    public function testCompactPreservesSpacesInDoubleQuotedStrings(): void
    {
        $compactor = new SqlCompactor();
        $sql = 'SELECT * FROM users WHERE name = "John   Doe"';
        $result = $compactor->compact($sql);

        Assert::same($result, 'SELECT * FROM users WHERE name = "John   Doe"');
    }

    public function testCompactHandlesEscapedSingleQuotes(): void
    {
        $compactor = new SqlCompactor();
        $sql = "SELECT * FROM users WHERE name = 'John''s Name'";
        $result = $compactor->compact($sql);

        Assert::same($result, "SELECT * FROM users WHERE name = 'John''s Name'");
    }

    public function testCompactHandlesEscapedDoubleQuotes(): void
    {
        $compactor = new SqlCompactor();
        $sql = 'SELECT * FROM users WHERE name = "John""s Name"';
        $result = $compactor->compact($sql);

        Assert::same($result, 'SELECT * FROM users WHERE name = "John""s Name"');
    }

    public function testCompactHandlesNewlinesInStrings(): void
    {
        $compactor = new SqlCompactor();
        $sql = "SELECT * FROM users WHERE description = 'Line 1\nLine 2'";
        $result = $compactor->compact($sql);

        Assert::same($result, "SELECT * FROM users WHERE description = 'Line 1\nLine 2'");
    }

    public function testCompactHandlesMultipleStringLiterals(): void
    {
        $compactor = new SqlCompactor();
        $sql = "SELECT * FROM users WHERE name = 'John' AND city = 'New York'";
        $result = $compactor->compact($sql);

        Assert::same($result, "SELECT * FROM users WHERE name = 'John' AND city = 'New York'");
    }

    public function testCompactHandlesMixedQuotes(): void
    {
        $compactor = new SqlCompactor();
        $sql = 'SELECT * FROM users WHERE name = "John" AND city = \'New York\'';
        $result = $compactor->compact($sql);

        Assert::same($result, 'SELECT * FROM users WHERE name = "John" AND city = \'New York\'');
    }

    public function testCompactHandlesEmptyString(): void
    {
        $compactor = new SqlCompactor();
        $result = $compactor->compact('');

        Assert::same($result, '');
    }

    public function testCompactHandlesOnlyWhitespace(): void
    {
        $compactor = new SqlCompactor();
        $result = $compactor->compact('   ');

        Assert::same($result, '');
    }

    public function testCompactHandlesTabsAndNewlines(): void
    {
        $compactor = new SqlCompactor();
        $sql = "SELECT\t*\nFROM\n\tusers\nWHERE\n\tid = 1";
        $result = $compactor->compact($sql);

        Assert::same($result, 'SELECT * FROM users WHERE id = 1');
    }

    public function testCompactHandlesComplexQuery(): void
    {
        $compactor = new SqlCompactor();
        $sql = "SELECT   u.id,   u.name   FROM   users   u   WHERE   u.name   =   'John   Doe'   "
            . 'AND   u.city   =   "New   York"';
        $result = $compactor->compact($sql);

        Assert::same(
            $result,
            "SELECT u.id, u.name FROM users u WHERE u.name = 'John   Doe' AND u.city = \"New   York\""
        );
    }

    public function testCompactHandlesStringAtBeginning(): void
    {
        $compactor = new SqlCompactor();
        $sql = "'test'   SELECT   *   FROM   users";
        $result = $compactor->compact($sql);

        Assert::same($result, "'test' SELECT * FROM users");
    }

    public function testCompactHandlesStringAtEnd(): void
    {
        $compactor = new SqlCompactor();
        $sql = "SELECT   *   FROM   users   WHERE   name   =   'test'";
        $result = $compactor->compact($sql);

        Assert::same($result, "SELECT * FROM users WHERE name = 'test'");
    }

    public function testCompactHandlesConsecutiveEscapedQuotes(): void
    {
        $compactor = new SqlCompactor();
        $sql = "SELECT * FROM users WHERE name = 'John''''s Name'";
        $result = $compactor->compact($sql);

        Assert::same($result, "SELECT * FROM users WHERE name = 'John''''s Name'");
    }

    #[DataProvider('provideCompactHandlesMultilineStringsCases')]
    public function testCompactHandlesMultilineStrings(string $sql, string $expected): void
    {
        $compactor = new SqlCompactor();
        $result = $compactor->compact($sql);

        Assert::same($result, $expected);
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

        Assert::same($result, 'SELECT * FROM users');
    }

    public function testCompactHandlesUnclosedString(): void
    {
        $compactor = new SqlCompactor();
        $sql = "SELECT * FROM users WHERE name = 'John";
        $result = $compactor->compact($sql);

        Assert::same($result, 'SELECT * FROM users WHERE name = \'John');
    }

    public function testCompactHandlesQuoteAtEndOfString(): void
    {
        $compactor = new SqlCompactor();
        $sql = "SELECT * FROM users WHERE name = 'John'";
        $result = $compactor->compact($sql);

        Assert::same($result, "SELECT * FROM users WHERE name = 'John'");
    }

    public function testCompactKeepsLiteralWithBackslashEscapedQuote(): void
    {
        $sql = "SELECT *\nFROM t\nWHERE name = 'O\\'Neil  x'  AND  id = 1";

        Assert::same((new SqlCompactor(true))->compact($sql), "SELECT * FROM t WHERE name = 'O\\'Neil  x' AND id = 1");
    }

    public function testCompactKeepsLiteralEndingWithEscapedBackslash(): void
    {
        $sql = "SELECT 'C:\\\\dir\\\\'  ,  'a  b'";

        Assert::same((new SqlCompactor(true))->compact($sql), "SELECT 'C:\\\\dir\\\\' , 'a  b'");
    }

    public function testCompactTreatsBackslashAsOrdinaryWithoutBackslashEscapes(): void
    {
        $sql = "SELECT 'a\\'  ,  'b  c'";

        Assert::same((new SqlCompactor())->compact($sql), "SELECT 'a\\' , 'b  c'");
    }

    public function testCompactKeepsNewlineAfterLineComment(): void
    {
        $sql = "SELECT id -- primary key\n    FROM   t\nWHERE id = 1";

        Assert::same((new SqlCompactor())->compact($sql), "SELECT id -- primary key\nFROM t WHERE id = 1");
    }

    public function testCompactKeepsBlockCommentAsIs(): void
    {
        $sql = "SELECT /*+ NO_INDEX(t  idx) it's */  id\nFROM t";

        Assert::same((new SqlCompactor())->compact($sql), "SELECT /*+ NO_INDEX(t  idx) it's */ id FROM t");
    }

    public function testCompactKeepsQuotedIdentifiers(): void
    {
        $sql = "SELECT `my  col`,  [other  col],  \"third  col\"\nFROM t";

        Assert::same((new SqlCompactor())->compact($sql), 'SELECT `my  col`, [other  col], "third  col" FROM t');
    }

    public function testBuilderCompactKeepsRawMysqlLiteral(): void
    {
        $sql = (new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL))->select('*')->from('t')
            ->where()->raw("`name` = 'O\\'Neil  x'")->end()
            ->build(true)
        ;

        Assert::same($sql, "SELECT * FROM `t` WHERE (`name` = 'O\\'Neil  x')");
    }
}
