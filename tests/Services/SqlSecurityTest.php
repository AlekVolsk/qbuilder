<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QBuilder\Exceptions\InvalidIdentifierException;
use QBuilder\QbConsts;
use QBuilder\Services\SqlSecurity;

/**
 * @internal
 *
 * @coversNothing
 */
final class SqlSecurityTest extends TestCase
{
    public function testValidateIdentifierValid(): void
    {
        self::assertSame('valid_name', SqlSecurity::validateIdentifier('valid_name'));
        self::assertSame('table123', SqlSecurity::validateIdentifier('table123'));
        self::assertSame('_underscore', SqlSecurity::validateIdentifier('_underscore'));
        self::assertSame('CamelCase', SqlSecurity::validateIdentifier('CamelCase'));
    }

    #[DataProvider('provideValidateIdentifierInvalidThrowsExceptionCases')]
    public function testValidateIdentifierInvalidThrowsException(string $invalidIdentifier): void
    {
        $this->expectException(InvalidIdentifierException::class);
        $this->expectExceptionMessage('Invalid identifier');

        SqlSecurity::validateIdentifier($invalidIdentifier);
    }

    /**
     * @return iterable<string, array<string>>
     */
    public static function provideValidateIdentifierInvalidThrowsExceptionCases(): iterable
    {
        yield 'hyphen' => ['invalid-name'];
        yield 'space' => ['name with space'];
        yield 'semicolon' => ['name;drop'];
        yield 'comment block' => ['name/*comment*/'];
        yield 'comment line' => ['name--comment'];
        yield 'single quote' => ["name'quote"];
        yield 'double quote' => ['name"quote'];
        yield 'backtick' => ['name`quote'];
        yield 'parentheses' => ['name()'];
        yield 'brackets' => ['name[]'];
    }

    public function testValidateIdentifierEmpty(): void
    {
        $this->expectException(InvalidIdentifierException::class);
        $this->expectExceptionMessage('Empty identifier is not allowed');

        SqlSecurity::validateIdentifier('');
    }

    public function testValidateFieldNameValid(): void
    {
        self::assertSame('field_name', SqlSecurity::validateFieldName('field_name'));
        self::assertSame('*', SqlSecurity::validateFieldName('*'));
        self::assertSame('table.field', SqlSecurity::validateFieldName('table.field'));
        self::assertSame('field123', SqlSecurity::validateFieldName('field123'));
    }

    #[DataProvider('provideValidateFieldNameInvalidThrowsExceptionCases')]
    public function testValidateFieldNameInvalidThrowsException(string $invalidField): void
    {
        $this->expectException(InvalidIdentifierException::class);
        $this->expectExceptionMessage('Invalid field name');

        SqlSecurity::validateFieldName($invalidField);
    }

    /**
     * @return iterable<string, array<string>>
     */
    public static function provideValidateFieldNameInvalidThrowsExceptionCases(): iterable
    {
        yield 'semicolon' => ['field;drop'];
        yield 'comment block' => ['field/*comment*/'];
        yield 'comment line' => ['field--comment'];
        yield 'single quote' => ["field'quote"];
        yield 'space' => ['field with space'];
    }

    public function testValidateAliasNameValid(): void
    {
        self::assertSame('alias_name', SqlSecurity::validateAliasName('alias_name'));
        self::assertSame('a', SqlSecurity::validateAliasName('a'));
        self::assertSame('alias123', SqlSecurity::validateAliasName('alias123'));
        self::assertSame('CamelCase', SqlSecurity::validateAliasName('CamelCase'));
    }

    #[DataProvider('provideValidateAliasNameInvalidThrowsExceptionCases')]
    public function testValidateAliasNameInvalidThrowsException(string $invalidAlias): void
    {
        $this->expectException(InvalidIdentifierException::class);
        $this->expectExceptionMessage('Invalid alias name');

        SqlSecurity::validateAliasName($invalidAlias);
    }

    /**
     * @return iterable<string, array<string>>
     */
    public static function provideValidateAliasNameInvalidThrowsExceptionCases(): iterable
    {
        yield 'hyphen' => ['alias-name'];
        yield 'space' => ['alias with space'];
        yield 'semicolon' => ['alias;drop'];
        yield 'single quote' => ["alias'quote"];
    }

    /**
     * @return iterable<string, array<string>>
     */
    public static function provideValidateOperatorInvalidThrowsExceptionCases(): iterable
    {
        return [
            'double equals' => ['=='],
            'AND keyword' => ['AND'],
            'OR keyword' => ['OR'],
            'semicolon' => [';'],
            'comment line' => ['--'],
            'comment block' => ['/*'],
        ];
    }

    public function testValidateOrderDirectionValid(): void
    {
        self::assertSame('ASC', SqlSecurity::validateOrderDirection('ASC'));
        self::assertSame('DESC', SqlSecurity::validateOrderDirection('DESC'));
        self::assertSame('ASC', SqlSecurity::validateOrderDirection('asc'));
        self::assertSame('DESC', SqlSecurity::validateOrderDirection('desc'));
    }

    #[DataProvider('provideValidateOrderDirectionInvalidThrowsExceptionCases')]
    public function testValidateOrderDirectionInvalidThrowsException(string $invalidDirection): void
    {
        $this->expectException(InvalidIdentifierException::class);
        $this->expectExceptionMessage('Invalid order direction');

        SqlSecurity::validateOrderDirection($invalidDirection);
    }

    /**
     * @return iterable<string, array<string>>
     */
    public static function provideValidateOrderDirectionInvalidThrowsExceptionCases(): iterable
    {
        yield 'ascending' => ['ascending'];
        yield 'descending' => ['descending'];
        yield 'up' => ['up'];
        yield 'down' => ['down'];
        yield 'sql injection' => ['; DROP TABLE'];
    }

    #[DataProvider('provideValidateIdentifierSqlInjectionAttemptsCases')]
    public function testValidateIdentifierSqlInjectionAttempts(string $injection): void
    {
        $this->expectException(InvalidIdentifierException::class);

        SqlSecurity::validateIdentifier($injection);
    }

    /**
     * @return iterable<string, array<string>>
     */
    public static function provideValidateIdentifierSqlInjectionAttemptsCases(): iterable
    {
        yield 'DROP TABLE' => ["'; DROP TABLE users; --"];
        yield 'OR injection' => ["1' OR '1'='1"];
        yield 'admin comment' => ["admin'--"];
        yield 'UNION injection' => ["1' UNION SELECT NULL--"];
        yield 'EXEC injection' => ["'; EXEC xp_cmdshell('dir'); --"];
    }

    #[DataProvider('provideDriverSpecificKeywordsBlockedCases')]
    public function testDriverSpecificKeywordsBlocked(string $driver, string $keyword): void
    {
        $this->expectException(InvalidIdentifierException::class);
        $this->expectExceptionMessage("SQL keyword detected for driver '{$driver}'");

        SqlSecurity::validateIdentifier($keyword, 'identifier', $driver, false);
    }

    /**
     * @return iterable<array-key, array<string>>
     */
    public static function provideDriverSpecificKeywordsBlockedCases(): iterable
    {
        yield [QbConsts::DRIVER_MYSQL, 'LOAD_FILE'];
        yield [QbConsts::DRIVER_PDO_MYSQL, 'OUTFILE'];
        yield [QbConsts::DRIVER_MYSQL, 'DUMPFILE'];
        yield [QbConsts::DRIVER_PDO_MYSQL, 'INFORMATION_SCHEMA'];

        yield [QbConsts::DRIVER_PGSQL, 'COPY'];
        yield [QbConsts::DRIVER_POSTGRESQL, 'PROGRAM'];
        yield [QbConsts::DRIVER_PGSQL, 'PG_READ_FILE'];
        yield [QbConsts::DRIVER_POSTGRESQL, 'RETURNING'];

        yield [QbConsts::DRIVER_CLICKHOUSE, 'FINAL'];
        yield [QbConsts::DRIVER_CLICKHOUSE, 'SAMPLE'];
        yield [QbConsts::DRIVER_CLICKHOUSE, 'PREWHERE'];
        yield [QbConsts::DRIVER_CLICKHOUSE, 'SETTINGS'];

        yield [QbConsts::DRIVER_MSSQL, 'BULK'];
        yield [QbConsts::DRIVER_MSSQL, 'OPENROWSET'];
        yield [QbConsts::DRIVER_MSSQL, 'XP_CMDSHELL'];
        yield [QbConsts::DRIVER_MSSQL, 'SP_EXECUTESQL'];

        yield [QbConsts::DRIVER_ORACLE, 'DBMS_PIPE'];
        yield [QbConsts::DRIVER_ORACLE, 'UTL_FILE'];
        yield [QbConsts::DRIVER_ORACLE, 'SYSDBA'];
        yield [QbConsts::DRIVER_ORACLE, 'DUAL'];

        yield [QbConsts::DRIVER_SQLITE, 'PRAGMA'];
        yield [QbConsts::DRIVER_SQLITE, 'ATTACH'];
        yield [QbConsts::DRIVER_SQLITE, 'VACUUM'];
    }

    #[DataProvider('provideCommonKeywordsBlockedWhenNotEscapedCases')]
    public function testCommonKeywordsBlockedWhenNotEscaped(string $driver, string $keyword): void
    {
        $this->expectException(InvalidIdentifierException::class);
        $this->expectExceptionMessage('SQL keyword detected');

        SqlSecurity::validateIdentifier($keyword, 'identifier', $driver, false);
    }

    /**
     * @return iterable<string, array<string>>
     */
    public static function provideCommonKeywordsBlockedWhenNotEscapedCases(): iterable
    {
        $drivers = [
            QbConsts::DRIVER_MYSQL,
            QbConsts::DRIVER_PDO_MYSQL,
            QbConsts::DRIVER_PGSQL,
            QbConsts::DRIVER_CLICKHOUSE,
            QbConsts::DRIVER_MSSQL,
            QbConsts::DRIVER_ORACLE,
            QbConsts::DRIVER_SQLITE,
        ];
        $commonKeywords = ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'DROP', 'UNION', 'FROM', 'WHERE'];

        foreach ($drivers as $driver) {
            foreach ($commonKeywords as $keyword) {
                yield "{$driver} - {$keyword}" => [$driver, $keyword];
            }
        }
    }

    #[DataProvider('provideCommonKeywordsAllowedWhenEscapedCases')]
    public function testCommonKeywordsAllowedWhenEscaped(string $driver, string $keyword): void
    {
        $result = SqlSecurity::validateIdentifier($keyword, 'identifier', $driver, true);

        self::assertSame($keyword, $result);
    }

    /**
     * @return iterable<string, array<string>>
     */
    public static function provideCommonKeywordsAllowedWhenEscapedCases(): iterable
    {
        $drivers = [
            QbConsts::DRIVER_MYSQL,
            QbConsts::DRIVER_PDO_MYSQL,
            QbConsts::DRIVER_PGSQL,
            QbConsts::DRIVER_CLICKHOUSE,
            QbConsts::DRIVER_MSSQL,
            QbConsts::DRIVER_ORACLE,
            QbConsts::DRIVER_SQLITE,
        ];
        $commonKeywords = ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'DROP', 'UNION', 'FROM', 'WHERE', 'FIELDS'];

        foreach ($drivers as $driver) {
            foreach ($commonKeywords as $keyword) {
                yield "{$driver} - {$keyword}" => [$driver, $keyword];
            }
        }
    }

    public function testValidIdentifierNotBlockedByDriverSpecificKeywords(): void
    {
        $validName = 'my_table_name';

        self::assertSame($validName, SqlSecurity::validateIdentifier($validName, 'table', QbConsts::DRIVER_MYSQL));
        self::assertSame($validName, SqlSecurity::validateIdentifier($validName, 'table', QbConsts::DRIVER_PDO_MYSQL));
        self::assertSame($validName, SqlSecurity::validateIdentifier($validName, 'table', QbConsts::DRIVER_PGSQL));
        self::assertSame($validName, SqlSecurity::validateIdentifier($validName, 'table', QbConsts::DRIVER_CLICKHOUSE));
        self::assertSame($validName, SqlSecurity::validateIdentifier($validName, 'table', QbConsts::DRIVER_MSSQL));
        self::assertSame($validName, SqlSecurity::validateIdentifier($validName, 'table', QbConsts::DRIVER_ORACLE));
        self::assertSame($validName, SqlSecurity::validateIdentifier($validName, 'table', QbConsts::DRIVER_SQLITE));
    }
}
