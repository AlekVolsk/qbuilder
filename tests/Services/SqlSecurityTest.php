<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use QBuilder\Exceptions\InvalidIdentifierException;
use QBuilder\Exceptions\UnsupportedFeatureException;
use QBuilder\QbConsts;
use QBuilder\Services\SqlSecurity;
use Testo\Assert;
use Testo\Assert\ExpectException;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

/**
 * @internal
 */
#[Test]
final class SqlSecurityTest
{
    public function testValidateIdentifierValid(): void
    {
        Assert::same(SqlSecurity::validateIdentifier('valid_name'), 'valid_name');
        Assert::same(SqlSecurity::validateIdentifier('table123'), 'table123');
        Assert::same(SqlSecurity::validateIdentifier('_underscore'), '_underscore');
        Assert::same(SqlSecurity::validateIdentifier('CamelCase'), 'CamelCase');
    }

    #[DataProvider('provideValidateIdentifierInvalidThrowsExceptionCases')]
    public function testValidateIdentifierInvalidThrowsException(string $invalidIdentifier): void
    {
        Expect::exception(InvalidIdentifierException::class)->withMessageContaining('Invalid identifier');

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
        Expect::exception(InvalidIdentifierException::class)->withMessageContaining('Empty identifier is not allowed');

        SqlSecurity::validateIdentifier('');
    }

    public function testValidateFieldNameValid(): void
    {
        Assert::same(SqlSecurity::validateFieldName('field_name'), 'field_name');
        Assert::same(SqlSecurity::validateFieldName('*'), '*');
        Assert::same(SqlSecurity::validateFieldName('table.field'), 'table.field');
        Assert::same(SqlSecurity::validateFieldName('field123'), 'field123');
    }

    #[DataProvider('provideValidateFieldNameInvalidThrowsExceptionCases')]
    public function testValidateFieldNameInvalidThrowsException(string $invalidField): void
    {
        Expect::exception(InvalidIdentifierException::class)->withMessageContaining('Invalid field name');

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
        Assert::same(SqlSecurity::validateAliasName('alias_name'), 'alias_name');
        Assert::same(SqlSecurity::validateAliasName('a'), 'a');
        Assert::same(SqlSecurity::validateAliasName('alias123'), 'alias123');
        Assert::same(SqlSecurity::validateAliasName('CamelCase'), 'CamelCase');
    }

    #[DataProvider('provideValidateAliasNameInvalidThrowsExceptionCases')]
    public function testValidateAliasNameInvalidThrowsException(string $invalidAlias): void
    {
        Expect::exception(InvalidIdentifierException::class)->withMessageContaining('Invalid alias name');

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

    #[DataProvider('provideValidateOperatorInvalidThrowsExceptionCases')]
    public function testValidateOperatorInvalidThrowsException(string $invalidOperator): void
    {
        Expect::exception(InvalidIdentifierException::class)->withMessageContaining('Invalid comparison operator');

        SqlSecurity::validateComparisonOperator($invalidOperator);
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
        Assert::same(SqlSecurity::validateOrderDirection('ASC'), 'ASC');
        Assert::same(SqlSecurity::validateOrderDirection('DESC'), 'DESC');
        Assert::same(SqlSecurity::validateOrderDirection('asc'), 'ASC');
        Assert::same(SqlSecurity::validateOrderDirection('desc'), 'DESC');
    }

    #[DataProvider('provideValidateOrderDirectionInvalidThrowsExceptionCases')]
    public function testValidateOrderDirectionInvalidThrowsException(string $invalidDirection): void
    {
        Expect::exception(InvalidIdentifierException::class)->withMessageContaining('Invalid order direction');

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
    #[ExpectException(InvalidIdentifierException::class)]
    public function testValidateIdentifierSqlInjectionAttempts(string $injection): void
    {
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
        Expect::exception(InvalidIdentifierException::class)
            ->withMessageContaining("SQL keyword detected for driver '{$driver}'")
        ;

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
        Expect::exception(InvalidIdentifierException::class)->withMessageContaining('SQL keyword detected');

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

        Assert::same($result, $keyword);
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

        Assert::same(SqlSecurity::validateIdentifier($validName, 'table', QbConsts::DRIVER_MYSQL), $validName);
        Assert::same(SqlSecurity::validateIdentifier($validName, 'table', QbConsts::DRIVER_PDO_MYSQL), $validName);
        Assert::same(SqlSecurity::validateIdentifier($validName, 'table', QbConsts::DRIVER_PGSQL), $validName);
        Assert::same(SqlSecurity::validateIdentifier($validName, 'table', QbConsts::DRIVER_CLICKHOUSE), $validName);
        Assert::same(SqlSecurity::validateIdentifier($validName, 'table', QbConsts::DRIVER_MSSQL), $validName);
        Assert::same(SqlSecurity::validateIdentifier($validName, 'table', QbConsts::DRIVER_ORACLE), $validName);
        Assert::same(SqlSecurity::validateIdentifier($validName, 'table', QbConsts::DRIVER_SQLITE), $validName);
    }

    /**
     * @param non-empty-string $operator
     */
    #[DataProvider('provideDialectSpecificOperatorCases')]
    public function testDialectSpecificComparisonOperatorIsAccepted(string $driver, string $operator): void
    {
        Assert::same(SqlSecurity::validateComparisonOperator(strtolower($operator), $driver), $operator);
    }

    /**
     * @return iterable<string, array{string, non-empty-string}>
     */
    public static function provideDialectSpecificOperatorCases(): iterable
    {
        yield 'PostgreSQL ILIKE' => [QbConsts::DRIVER_PGSQL, 'ILIKE'];
        yield 'ClickHouse GLOBAL IN' => [QbConsts::DRIVER_CLICKHOUSE, 'GLOBAL IN'];
        yield 'MS SQL !<' => [QbConsts::DRIVER_MSSQL, '!<'];
        yield 'Oracle IS DISTINCT FROM' => [QbConsts::DRIVER_ORACLE, 'IS DISTINCT FROM'];
        yield 'SQLite GLOB' => [QbConsts::DRIVER_SQLITE, 'GLOB'];
    }

    /**
     * @param \Closure(): mixed $call
     */
    #[DataProvider('provideUnknownDriverCases')]
    public function testUnknownDriverIsRejected(\Closure $call): void
    {
        Expect::exception(UnsupportedFeatureException::class)
            ->withMessageContaining("Unsupported database driver: 'nosql'")
        ;

        $call();
    }

    /**
     * @return iterable<string, array{\Closure(): mixed}>
     */
    public static function provideUnknownDriverCases(): iterable
    {
        yield 'SQL functions' => [static fn (): array => SqlSecurity::getSqlFunctions('nosql')];

        yield 'dangerous keywords' => [
            static fn (): string => SqlSecurity::escapeIdentifiersInExpression('a.b', '`', 'nosql'),
        ];

        yield 'comparison operators' => [static fn (): string => SqlSecurity::validateComparisonOperator('=', 'nosql')];
    }

    public function testEscapeIdentifiersInExpressionKeepsKeywordsAndNumbers(): void
    {
        Assert::same(
            SqlSecurity::escapeIdentifiersInExpression('SELECT.id + u.id + COUNT(DISTINCT) + MAX(price)', '`'),
            'SELECT.id + `u`.`id` + COUNT(DISTINCT) + MAX(`price`)'
        );
    }
}
