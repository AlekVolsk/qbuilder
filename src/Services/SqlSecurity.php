<?php

namespace QBuilder\Services;

use QBuilder\Exceptions\InvalidIdentifierException;
use QBuilder\QbConsts;

/**
 * Service for safe SQL operations.
 * Protection against SQL injections for identifiers (tables, fields, aliases).
 */
class SqlSecurity
{
    /**
     * Validate identifier name (table, field, alias).
     * Only letters, numbers, underscores and dots are allowed.
     *
     * @param string $identifier    Name to validate
     * @param string $type          Identifier type (for error message)
     * @param string $driver        Database driver (mysql, pgsql, etc.)
     * @param bool   $willBeEscaped Whether identifier will be escaped with quotes (backticks, etc.)
     *
     * @return string Validated identifier
     *
     * @throws InvalidIdentifierException If identifier contains invalid characters
     */
    public static function validateIdentifier(
        string $identifier,
        string $type = 'identifier',
        string $driver = QbConsts::DRIVER_PDO_MYSQL,
        bool $willBeEscaped = true
    ): string {
        if ('' === $identifier || '0' === $identifier) {
            throw new InvalidIdentifierException("Empty {$type} is not allowed");
        }

        if (! $willBeEscaped) {
            $dangerousKeywords = self::getDangerousKeywords($driver);
            $upperIdentifier = strtoupper($identifier);

            foreach ($dangerousKeywords as $keyword) {
                if ($upperIdentifier === $keyword || str_starts_with($upperIdentifier, $keyword.' ')) {
                    throw new InvalidIdentifierException(
                        "Invalid {$type}: '{$identifier}'. SQL keyword detected for driver '{$driver}'"
                    );
                }
            }
        }

        if (! preg_match('/^[a-zA-Z0-9_.]+$/', $identifier)) {
            throw new InvalidIdentifierException(
                "Invalid {$type}: '{$identifier}'. Only alphanumeric characters, underscores, and dots are allowed"
            );
        }

        return $identifier;
    }

    /**
     * Validate table name.
     *
     * @param string $tableName     Table name
     * @param string $driver        Database driver
     * @param bool   $willBeEscaped Whether table name will be escaped with quotes
     *
     * @return string Validated table name
     *
     * @throws InvalidIdentifierException If table name contains invalid characters
     */
    public static function validateTableName(
        string $tableName,
        string $driver = QbConsts::DRIVER_PDO_MYSQL,
        bool $willBeEscaped = true
    ): string {
        return self::validateIdentifier($tableName, 'table name', $driver, $willBeEscaped);
    }

    /**
     * Validate field name.
     *
     * @param string $fieldName     Field name
     * @param string $driver        Database driver
     * @param bool   $willBeEscaped Whether field name will be escaped with quotes
     *
     * @return string Validated field name
     *
     * @throws InvalidIdentifierException If field name contains invalid characters
     */
    public static function validateFieldName(
        string $fieldName,
        string $driver = QbConsts::DRIVER_PDO_MYSQL,
        bool $willBeEscaped = true
    ): string {
        if ('*' === $fieldName) {
            return $fieldName;
        }

        return self::validateIdentifier($fieldName, 'field name', $driver, $willBeEscaped);
    }

    /**
     * Validate alias name.
     *
     * @param string $aliasName     Alias name
     * @param string $driver        Database driver
     * @param bool   $willBeEscaped Whether alias name will be escaped with quotes
     *
     * @return string Validated alias name
     *
     * @throws InvalidIdentifierException If alias name contains invalid characters
     */
    public static function validateAliasName(
        string $aliasName,
        string $driver = QbConsts::DRIVER_PDO_MYSQL,
        bool $willBeEscaped = true
    ): string {
        return self::validateIdentifier($aliasName, 'alias name', $driver, $willBeEscaped);
    }

    /**
     * Validate sort direction.
     *
     * @param string $direction Direction (ASC or DESC)
     *
     * @return string Validated direction
     *
     * @throws InvalidIdentifierException If direction is invalid
     */
    public static function validateOrderDirection(string $direction): string
    {
        $upper = strtoupper($direction);

        if ('ASC' !== $upper && 'DESC' !== $upper) {
            throw new InvalidIdentifierException(
                "Invalid order direction: '{$direction}'. Only ASC or DESC are allowed"
            );
        }

        return $upper;
    }

    /**
     * Validate comparison operator for specific driver.
     *
     * @param string $operator Comparison operator
     * @param string $driver   Database driver
     *
     * @return string Validated and normalized operator (uppercase)
     *
     * @throws InvalidIdentifierException If operator is invalid for the driver
     */
    public static function validateComparisonOperator(
        string $operator,
        string $driver = QbConsts::DRIVER_PDO_MYSQL
    ): string {
        $operatorUpper = strtoupper(trim($operator));
        $validOperators = self::getValidComparisonOperators($driver);

        if (! \in_array($operatorUpper, $validOperators, true)) {
            throw new InvalidIdentifierException(
                "Invalid comparison operator: '{$operator}' for driver '{$driver}'. "
                    .'Common operators: =, !=, <>, >, <, >=, <=, LIKE, NOT LIKE, IS, IS NOT, IN, NOT IN, BETWEEN'
            );
        }

        return $operatorUpper;
    }

    /**
     * Safely escape identifier for use in backticks.
     * Removes backticks from identifier.
     *
     * @param string $identifier Identifier
     * @param string $driver     Database driver
     *
     * @return string Escaped identifier
     */
    public static function escapeIdentifier(string $identifier, string $driver = QbConsts::DRIVER_PDO_MYSQL): string
    {
        $clean = str_replace('`', '', $identifier);

        return self::validateIdentifier($clean, 'identifier', $driver);
    }

    /**
     * Get list of SQL functions for expression detection.
     *
     * @param string $driver Database driver
     *
     * @return array<string> List of SQL function names
     */
    public static function getSqlFunctions(string $driver = QbConsts::DRIVER_PDO_MYSQL): array
    {
        $common = [
            'COUNT',
            'SUM',
            'AVG',
            'MIN',
            'MAX',
            'COALESCE',
            'NULLIF',
            'CASE',
            'CAST',
            'UPPER',
            'LOWER',
            'LENGTH',
            'SUBSTRING',
            'TRIM',
            'LTRIM',
            'RTRIM',
            'REPLACE',
        ];

        $specific = match ($driver) {
            QbConsts::DRIVER_MYSQL, QbConsts::DRIVER_PDO_MYSQL => [
                'GROUP_CONCAT',
                'CONCAT',
                'CONCAT_WS',
                'IFNULL',
                'IF',
                'CONVERT',
                'DATE',
                'DATETIME',
                'TIMESTAMP',
                'NOW',
                'CURDATE',
                'CURTIME',
                'CURRENT_DATE',
                'CURRENT_TIME',
                'CURRENT_TIMESTAMP',
                'YEAR',
                'MONTH',
                'DAY',
                'HOUR',
                'MINUTE',
                'SECOND',
                'DATE_FORMAT',
                'STR_TO_DATE',
                'UNIX_TIMESTAMP',
                'FROM_UNIXTIME',
                'TIMESTAMPDIFF',
                'DATEDIFF',
                'DATE_ADD',
                'DATE_SUB',
                'ADDDATE',
                'SUBDATE',
                'REGEXP',
                'LIKE',
                'FIND_IN_SET',
                'FIELD',
                'ELT',
                'CHAR_LENGTH',
                'INSTR',
                'LOCATE',
                'POSITION',
                'LEFT',
                'RIGHT',
                'LPAD',
                'RPAD',
                'REPEAT',
                'REVERSE',
                'SPACE',
                'STRCMP',
                'SUBSTR',
                'SUBSTRING_INDEX',
                'ABS',
                'CEIL',
                'CEILING',
                'FLOOR',
                'ROUND',
                'TRUNCATE',
                'MOD',
                'POW',
                'POWER',
                'SQRT',
                'RAND',
                'SIGN',
                'GREATEST',
                'LEAST',
                'MD5',
                'SHA1',
                'SHA2',
            ],
            QbConsts::DRIVER_POSTGRESQL, QbConsts::DRIVER_PGSQL => [
                'STRING_AGG',
                'ARRAY_AGG',
                'CONCAT',
                'CONCAT_WS',
                'COALESCE',
                'NULLIF',
                'GREATEST',
                'LEAST',
                'NOW',
                'CURRENT_DATE',
                'CURRENT_TIME',
                'CURRENT_TIMESTAMP',
                'DATE_PART',
                'DATE_TRUNC',
                'EXTRACT',
                'AGE',
                'TO_CHAR',
                'TO_DATE',
                'TO_TIMESTAMP',
                'INTERVAL',
                'POSITION',
                'STRPOS',
                'SPLIT_PART',
                'REGEXP_MATCH',
                'REGEXP_MATCHES',
                'REGEXP_REPLACE',
                'REGEXP_SPLIT_TO_ARRAY',
                'REGEXP_SPLIT_TO_TABLE',
                'SIMILAR',
                'LIKE',
                'ILIKE',
                'CHAR_LENGTH',
                'CHARACTER_LENGTH',
                'OCTET_LENGTH',
                'BIT_LENGTH',
                'OVERLAY',
                'LPAD',
                'RPAD',
                'REPEAT',
                'REVERSE',
                'TRANSLATE',
                'INITCAP',
                'ASCII',
                'CHR',
                'MD5',
                'ENCODE',
                'DECODE',
                'GEN_RANDOM_UUID',
                'ABS',
                'CEIL',
                'CEILING',
                'FLOOR',
                'ROUND',
                'TRUNC',
                'MOD',
                'POWER',
                'SQRT',
                'RANDOM',
                'SIGN',
                'JSON_BUILD_OBJECT',
                'JSON_BUILD_ARRAY',
                'JSONB_BUILD_OBJECT',
                'JSONB_BUILD_ARRAY',
                'JSON_EXTRACT_PATH',
                'JSONB_EXTRACT_PATH',
                'JSON_ARRAY_LENGTH',
                'JSONB_ARRAY_LENGTH',
                'ROW_NUMBER',
                'RANK',
                'DENSE_RANK',
                'LAG',
                'LEAD',
            ],
            QbConsts::DRIVER_CLICKHOUSE => [
                'CONCAT',
                'SUBSTRING',
                'LENGTH',
                'LOWER',
                'UPPER',
                'NOW',
                'TODAY',
                'YESTERDAY',
                'TODATE',
                'TODATETIME',
                'TOTIMESTAMP',
                'FORMATDATETIME',
                'PARSEDATETIME',
                'TOYYYYMM',
                'TOYYYYMMDD',
                'TOMONDAY',
                'TOSTARTOFMONTH',
                'TOSTARTOFQUARTER',
                'TOSTARTOFYEAR',
                'TOYEAR',
                'TOMONTH',
                'TODAYOFMONTH',
                'TODAYOFWEEK',
                'TOHOUR',
                'TOMINUTE',
                'TOSECOND',
                'ARRAYCONCAT',
                'ARRAYCOUNT',
                'ARRAYFILTER',
                'ARRAYMAP',
                'ARRAYREDUCE',
                'ARRAYREVERSE',
                'ARRAYSORT',
                'ARRAYUNIQ',
                'GROUPARRAY',
                'GROUPUNIQARRAY',
                'GROUPBITAND',
                'GROUPBITOR',
                'GROUPBITXOR',
                'IF',
                'MULTIIF',
                'MATCH',
                'EXTRACT',
                'EXTRACTALL',
                'LIKE',
                'NOTLIKE',
                'ILIKE',
                'POSITION',
                'POSITIONUTF8',
            ],
            QbConsts::DRIVER_MSSQL => [
                'STRING_AGG',
                'CONCAT',
                'CONCAT_WS',
                'ISNULL',
                'IIF',
                'CHOOSE',
                'CONVERT',
                'TRY_CONVERT',
                'GETDATE',
                'GETUTCDATE',
                'SYSDATETIME',
                'SYSUTCDATETIME',
                'CURRENT_TIMESTAMP',
                'DATEPART',
                'DATENAME',
                'DATEADD',
                'DATEDIFF',
                'DATEDIFF_BIG',
                'DATEFROMPARTS',
                'DATETIMEFROMPARTS',
                'EOMONTH',
                'YEAR',
                'MONTH',
                'DAY',
                'FORMAT',
                'CHARINDEX',
                'PATINDEX',
                'LEN',
                'DATALENGTH',
                'LEFT',
                'RIGHT',
                'STUFF',
                'REPLICATE',
                'REVERSE',
                'SPACE',
                'STR',
                'QUOTENAME',
                'SOUNDEX',
                'DIFFERENCE',
                'ABS',
                'CEILING',
                'FLOOR',
                'ROUND',
                'POWER',
                'SQRT',
                'SQUARE',
                'SIGN',
                'RAND',
                'CHECKSUM',
                'HASHBYTES',
                'NEWID',
                'NEWSEQUENTIALID',
                'ROW_NUMBER',
                'RANK',
                'DENSE_RANK',
                'NTILE',
                'LAG',
                'LEAD',
                'FIRST_VALUE',
                'LAST_VALUE',
            ],
            QbConsts::DRIVER_ORACLE => [
                'LISTAGG',
                'CONCAT',
                'NVL',
                'NVL2',
                'DECODE',
                'TO_CHAR',
                'TO_DATE',
                'TO_TIMESTAMP',
                'TO_NUMBER',
                'SYSDATE',
                'SYSTIMESTAMP',
                'CURRENT_DATE',
                'CURRENT_TIMESTAMP',
                'EXTRACT',
                'ADD_MONTHS',
                'MONTHS_BETWEEN',
                'NEXT_DAY',
                'LAST_DAY',
                'TRUNC',
                'ROUND',
                'INSTR',
                'LENGTH',
                'LENGTHB',
                'SUBSTR',
                'SUBSTRB',
                'LPAD',
                'RPAD',
                'TRANSLATE',
                'INITCAP',
                'ASCII',
                'CHR',
                'SOUNDEX',
                'ABS',
                'CEIL',
                'FLOOR',
                'MOD',
                'POWER',
                'SQRT',
                'SIGN',
                'GREATEST',
                'LEAST',
                'DBMS_RANDOM',
                'SYS_GUID',
                'ROW_NUMBER',
                'RANK',
                'DENSE_RANK',
                'LAG',
                'LEAD',
                'FIRST_VALUE',
                'LAST_VALUE',
            ],
            QbConsts::DRIVER_SQLITE => [
                'GROUP_CONCAT',
                'CONCAT',
                'IFNULL',
                'NULLIF',
                'DATE',
                'TIME',
                'DATETIME',
                'JULIANDAY',
                'STRFTIME',
                'SUBSTR',
                'INSTR',
                'LIKE',
                'GLOB',
                'REGEXP',
                'QUOTE',
                'UNICODE',
                'ZEROBLOB',
                'HEX',
                'UNHEX',
                'RANDOMBLOB',
                'ABS',
                'ROUND',
                'RANDOM',
                'SIGN',
                'TYPEOF',
                'LAST_INSERT_ROWID',
                'CHANGES',
                'TOTAL_CHANGES',
            ],
            default => [],
        };

        return array_merge($common, $specific);
    }

    /**
     * Escape identifiers in SQL expression.
     * Converts: DATE_FORMAT(table.field, "%Y") AS alias
     * To: DATE_FORMAT(`table`.`field`, "%Y") AS `alias`.
     *
     * @param string $expression SQL expression
     * @param string $quote      Quote character (backtick, double quote, etc.)
     * @param string $driver     Database driver
     *
     * @return string Expression with escaped identifiers
     */
    public static function escapeIdentifiersInExpression(
        string $expression,
        string $quote = '`',
        string $driver = QbConsts::DRIVER_PDO_MYSQL
    ): string {
        $sqlKeywords = self::getDangerousKeywords($driver, true);
        $quotedPattern = preg_quote($quote, '/');

        $pattern = '/(?<!'.$quotedPattern.')\b([a-zA-Z_][a-zA-Z0-9_]*)\.([a-zA-Z_][a-zA-Z0-9_]*)\b(?!'
            .$quotedPattern.')/';

        $expression = (string) preg_replace_callback($pattern, static function ($matches) use ($quote, $sqlKeywords) {
            $table = $matches[1];
            $field = $matches[2];

            if (\in_array(strtoupper($table), $sqlKeywords, true)) {
                return $matches[0];
            }

            return $quote.$table.$quote.'.'.$quote.$field.$quote;
        }, $expression);

        $singleFieldPattern = '/\b([A-Z_]+)\s*\(\s*(?<!'.$quotedPattern.')([a-zA-Z_][a-zA-Z0-9_]*)(?!'
            .$quotedPattern.')\s*\)/';

        return (string) preg_replace_callback(
            $singleFieldPattern,
            static function ($matches) use ($quote, $sqlKeywords) {
                $func = $matches[1];
                $field = $matches[2];

                if (\in_array(strtoupper($field), $sqlKeywords, true) || is_numeric($field)) {
                    return $matches[0];
                }

                return $func.'('.$quote.$field.$quote.')';
            },
            $expression
        );
    }

    /**
     * Get dangerous keywords and SQL functions for specific driver.
     *
     * @param string $driver           Driver name (mysql, pgsql, clickhouse, etc.)
     * @param bool   $includeFunctions Include SQL functions in the list
     *
     * @return array<string> List of dangerous keywords and optionally functions
     */
    private static function getDangerousKeywords(string $driver, bool $includeFunctions = false): array
    {
        $common = [
            'SELECT',
            'INSERT',
            'UPDATE',
            'DELETE',
            'DROP',
            'CREATE',
            'ALTER',
            'TRUNCATE',
            'EXEC',
            'EXECUTE',
            'UNION',
            'INTO',
            'FROM',
            'WHERE',
            'HAVING',
            'GROUP',
            'ORDER',
            'LIMIT',
            'OFFSET',
            'JOIN',
            'LEFT',
            'RIGHT',
            'INNER',
            'OUTER',
            'CROSS',
            'FULL',
            'ON',
            'USING',
            'AS',
            'DISTINCT',
            'ALL',
            'AND',
            'OR',
            'NOT',
            'NULL',
            'IS',
            'IN',
            'BETWEEN',
            'LIKE',
            'EXISTS',
            'CASE',
            'WHEN',
            'THEN',
            'ELSE',
            'END',
        ];

        $specific = match ($driver) {
            QbConsts::DRIVER_MYSQL, QbConsts::DRIVER_PDO_MYSQL => [
                'LOAD_FILE',
                'OUTFILE',
                'DUMPFILE',
                'INFORMATION_SCHEMA',
                'LOAD',
                'DATA',
                'INFILE',
                'FIELDS',
                'LINES',
                'TERMINATED',
                'ENCLOSED',
                'ESCAPED',
                'STARTING',
                'IGNORE',
                'REPLACE',
            ],
            QbConsts::DRIVER_POSTGRESQL, QbConsts::DRIVER_PGSQL => [
                'COPY',
                'PROGRAM',
                'STDIN',
                'STDOUT',
                'PG_READ_FILE',
                'PG_LS_DIR',
                'PG_STAT_FILE',
                'XMLPARSE',
                'XMLEXISTS',
                'RETURNING',
                'CONFLICT',
                'NOTHING',
                'EXCLUDED',
            ],
            QbConsts::DRIVER_CLICKHOUSE => [
                'FINAL',
                'SAMPLE',
                'PREWHERE',
                'ARRAY',
                'GLOBAL',
                'LOCAL',
                'SETTINGS',
                'FORMAT',
                'ENGINE',
                'PARTITION',
                'OPTIMIZE',
                'SYSTEM',
                'KILL',
                'ATTACH',
                'DETACH',
                'LIVE',
                'MATERIALIZED',
            ],
            QbConsts::DRIVER_MSSQL => [
                'BULK',
                'OPENROWSET',
                'OPENDATASOURCE',
                'OPENQUERY',
                'OPENXML',
                'XP_CMDSHELL',
                'SP_EXECUTESQL',
                'RECONFIGURE',
                'BACKUP',
                'RESTORE',
                'SHUTDOWN',
                'SETUSER',
                'DBCC',
                'MERGE',
                'OUTPUT',
            ],
            QbConsts::DRIVER_ORACLE => [
                'CONNECT',
                'RESOURCE',
                'DBA',
                'SYSDBA',
                'SYSOPER',
                'DBMS_PIPE',
                'DBMS_LOB',
                'UTL_FILE',
                'UTL_HTTP',
                'UTL_TCP',
                'DBMS_SCHEDULER',
                'DBMS_JOB',
                'MERGE',
                'DUAL',
                'ROWNUM',
            ],
            QbConsts::DRIVER_SQLITE => [
                'ATTACH',
                'DETACH',
                'PRAGMA',
                'VACUUM',
                'REINDEX',
                'ANALYZE',
                'EXPLAIN',
                'AUTOINCREMENT',
                'CONFLICT',
                'ABORT',
            ],
            default => [],
        };

        return array_merge($common, $specific, $includeFunctions ? static::getSqlFunctions($driver) : []);
    }

    /**
     * Get valid comparison operators for specific driver.
     *
     * @param string $driver Database driver
     *
     * @return array<string> List of valid operators
     */
    private static function getValidComparisonOperators(string $driver): array
    {
        $common = [
            '=',
            '!=',
            '<>',
            '>',
            '<',
            '>=',
            '<=',
            'LIKE',
            'NOT LIKE',
            'IS',
            'IS NOT',
            'IN',
            'NOT IN',
            'BETWEEN',
            'NOT BETWEEN',
        ];

        $specific = match ($driver) {
            QbConsts::DRIVER_MYSQL, QbConsts::DRIVER_PDO_MYSQL => [
                'REGEXP',
                'NOT REGEXP',
                'RLIKE',
                'NOT RLIKE',
                'SOUNDS LIKE',
                '&',
                '|',
                '^',
                '<<',
                '>>',
                'DIV',
                'MOD',
                '<=>',
            ],
            QbConsts::DRIVER_POSTGRESQL, QbConsts::DRIVER_PGSQL => [
                'ILIKE',
                'NOT ILIKE',
                'SIMILAR TO',
                'NOT SIMILAR TO',
                'IS DISTINCT FROM',
                'IS NOT DISTINCT FROM',
                '~',
                '~*',
                '!~',
                '!~*',
                '@>',
                '<@',
                '?',
                '?|',
                '?&',
                '->',
                '->>',
                '#>',
                '#>>',
                '&&',
                '||',
                '@?',
                '@@',
            ],
            QbConsts::DRIVER_CLICKHOUSE => [
                'GLOBAL IN',
                'GLOBAL NOT IN',
                'LIKE',
                'NOT LIKE',
                'ILIKE',
                'NOT ILIKE',
                '=~',
                '!~',
            ],
            QbConsts::DRIVER_MSSQL => [
                '!<',
                '!>',
            ],
            QbConsts::DRIVER_ORACLE => [
                'IS DISTINCT FROM',
                'IS NOT DISTINCT FROM',
            ],
            QbConsts::DRIVER_SQLITE => [
                'GLOB',
                'NOT GLOB',
                'MATCH',
                'NOT MATCH',
                'REGEXP',
                'NOT REGEXP',
            ],
            default => [],
        };

        return array_merge($common, $specific);
    }
}
