# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Initial release of QBuilder - SQL Query Builder library
- Support for multiple database drivers (MySQL, PostgreSQL, SQLite, MSSQL, Oracle, ClickHouse)
- Fluent interface for building SELECT, INSERT, UPDATE, DELETE queries
- WHERE conditions with support for nested groups
- JOIN operations (LEFT, RIGHT, INNER, FULL, CROSS)
- Subqueries support (in SELECT, WHERE, FROM, JOIN)
- GROUP BY and HAVING clauses
- ORDER BY and LIMIT with offset
- Recursive CTE support
- UNION queries support
- Field-to-field comparisons
- Security features (SQL injection protection, identifier validation)
- Comprehensive test coverage
- `DriverInterface::formatValue()` — single type-driven formatter for SQL literals
- `QueryBuilder::setServerVersion()` / `DriverInterface::setServerVersion()` — server version for version-dependent syntax; MySQL 8.0.19+ uses the `INSERT ... AS `new`` row alias instead of deprecated `VALUES()` in `ON DUPLICATE KEY UPDATE`
- Index hints: `useIndex()`, `forceIndex()`, `ignoreIndex()` after `from()` or a join, with optional `FOR JOIN` / `FOR ORDER BY` / `FOR GROUP BY` scope; MySQL/MariaDB emit `USE` / `FORCE` / `IGNORE INDEX`, MS SQL Server emits `WITH (INDEX(...))` for `forceIndex()`, other drivers ignore hints

### Fixed

- Values are formatted by PHP type everywhere (conditions, INSERT/UPDATE, upsert/merge, procedure parameters): numeric-looking strings such as `'0123456789'` or `'1e3'` stay quoted instead of being emitted as numbers; `false` is emitted as `0` instead of `''`; PostgreSQL booleans are emitted as `TRUE`/`FALSE`; `INF`/`NAN` throw `InvalidQueryException`
- `in()` / `notIn()` keep empty-string array elements instead of silently dropping them (`notIn('code', [''])` no longer turns into `1 = 1`); empty elements are dropped only when splitting a comma-separated string
- `in()` / `notIn()` have a single implementation, so WHERE, HAVING and JOIN produce identical SQL and all accept `null` elements
- LIKE boundary wildcards are always added: `like('name', '50%', LIKE_RIGHT)` produced an exact match (`'50\%'`) because the boundary check looked at the already escaped value
- LIKE escaping works on every dialect: MySQL, PostgreSQL, SQLite and Oracle use `!` with an explicit `ESCAPE '!'` (independent of `sql_mode` / `standard_conforming_strings`); SQLite and Oracle previously had no working escape at all, so searches containing `%` or `_` found nothing
- MySQL string escaping doubles the quote (`''`) instead of `\'`: with `NO_BACKSLASH_ESCAPES` the old escaping let a value such as `a\') OR 1=1 -- ` break out of the literal (reproduced on MySQL 8.0); control characters are no longer escaped, as they are valid inside a literal
- PostgreSQL values containing a backslash are emitted as `E'...'`, so backslashes are stored intact regardless of `standard_conforming_strings`
- Compact mode (`build(true)`) no longer alters data: `SqlCompactor` understands backslash escapes in MySQL/ClickHouse literals (`'O\'Neil  x'` kept its inner spaces collapsed before), keeps quoted identifiers and `/* */` comments intact and keeps the line break after a `--` comment so it does not comment out the rest of the query
- MySQL `excluded()` generated invalid SQL (`` `email` = VALUES.`email` ``, error 1064 on MySQL 8.0); it now emits `VALUES(`email`)`, or the `AS `new`` row alias on MySQL 8.0.19+ when the server version is set
- An unsigned integer in `select()` / `Field::set()` is emitted as a literal: `select('1')` threw `InvalidQueryException` and `Field::set('1')` produced the column reference `` `1` ``, so `EXISTS (SELECT 1 ...)` could not be built; `Field::set('0')` no longer throws "Field name cannot be empty"
- JOIN conditions qualify unqualified fields with the joined table alias on every database and for every condition type: PostgreSQL, SQLite, MS SQL Server, Oracle and ClickHouse emitted `ON ("id" = "o"."user_id")`, which fails with "ambiguous column" when both tables have the column; MySQL did it with a regex over the built SQL that covered only comparison operators
- JOIN conditions no longer turn a string value of the form `'__RAW__name'` into the identifier `name`
