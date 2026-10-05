# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.0] - 2026-10-06

### Upgrading from 1.x

- Use only the `@api` classes: `QueryBuilder`, `QbConsts`, `Condition`, `ConditionBuilder`, `ConditionJoin`, `ConditionBy`, `Field`, `ConflictBuilderInterface`, `UnionBuilder`, `RecursiveCteBuilder` and the exceptions. Drivers, SQL builders, conflict builder implementations, `DriverFactory`, `DriverInterface`, `SqlSecurity` and `SqlCompactor` are internal; their concrete classes are `final` and cannot be extended
- Removed methods and their replacements: static `create()` of the conflict builders → `QueryBuilder::conflictBuilder()`; `DriverFactory::getSupportedDrivers()` → `QbConsts::getSupportedDrivers()`; `DriverFactory::isSupported()` → `in_array($driver, QbConsts::getSupportedDrivers(), true)`; drivers' `getName()` and `SqlBuilderInterface::getBuiltQuery()` have no replacement
- Input that is rejected now instead of producing SQL:
  - non-numeric `increment()` / `decrement()` values → `InvalidQueryException`
  - `expression()`, `caseExpression()`, `sqlFunction()` fragments that can leave their assignment or read other tables → `InvalidIdentifierException` (rules in `docs/en/11-security.md`)
  - `join()` with an unknown type → `InvalidQueryException`
  - INSERT without fields, including `insertRow([])` on MySQL, and UPDATE without assignments → `MissingRequirementException`
  - `from()` with a subquery and no alias → `InvalidIdentifierException`
  - MS SQL Server / Oracle upsert without `conflictTarget()` or with `insertConflictHandler()` before `insertRow()` → `MissingRequirementException`
  - strings with a NUL byte on PostgreSQL and SQLite → `InvalidQueryException`
  - `fullJoin()` / `fullJoinFromSelect()` on MySQL → `UnsupportedFeatureException`
- Generated SQL that changed: PostgreSQL `increment()` / `decrement()` reference `"table"."field"`, MERGE references `target.field`; UNION is no longer wrapped in parentheses; UNION LIMIT follows the dialect; ClickHouse UNION is `UNION DISTINCT` and is wrapped into `SELECT * FROM (...)` for ORDER BY / LIMIT; Oracle multi-row MERGE aliases the `USING` columns

### Security

- `increment()` / `decrement()` inserted a string value into SQL as is, so `increment('cnt', '1; DROP TABLE users; --')` appended an arbitrary statement on MySQL, PostgreSQL and MS SQL Server; the value must now be a finite number, otherwise `InvalidQueryException` is thrown
- `expression()`, `caseExpression()` and `sqlFunction()` let a fragment read other tables (`(SELECT password FROM users)`) or leave its assignment (`... END, role = ...`, `NOW(), role = 1`); every fragment is now validated to stay a single expression and `InvalidIdentifierException` is thrown otherwise (see `docs/en/11-security.md`)
- `expression()` and `caseExpression()` reject a fragment when the dangerous-pattern check itself fails

### Fixed

- PostgreSQL upsert: `increment()` / `decrement()` emitted `"n" = "n" + 1`, which PostgreSQL rejects as ambiguous with `EXCLUDED`; the current value is now referenced through the INSERT table (`"t"."n" + 1`), and MERGE on MS SQL Server and Oracle references it as `target.` for the same reason
- UNION with `orderBy()` / `limit()` wrapped the whole UNION in parentheses, which SQLite rejects; ORDER BY and LIMIT now follow the last query and apply to the whole result
- UNION `limit()` always emitted `LIMIT`/`OFFSET`, invalid on MS SQL Server and Oracle; it now uses the dialect form (`OFFSET ... FETCH`, with `ORDER BY (SELECT NULL)` on MS SQL Server when no order is set)
- ClickHouse UNION: a distinct UNION is emitted as `UNION DISTINCT`, and ORDER BY / LIMIT wrap the UNION into `SELECT * FROM (...)`, since ClickHouse applies them to the last query only
- Oracle MERGE with several rows had no column aliases in `USING`, so `source."COL"` referenced unknown columns
- MySQL `fullJoin()` / `fullJoinFromSelect()` produced SQL the server rejects; they now throw `UnsupportedFeatureException`
- PostgreSQL and SQLite strings with a NUL byte cut the statement off at it; they now throw `InvalidQueryException`
- `join()` with an unknown type silently fell back to `INNER JOIN`; it now throws `InvalidQueryException`
- INSERT without fields (`insert()` alone, `insertRow([])`) and UPDATE without assignments built an incomplete statement on every dialect; `build()` now throws `MissingRequirementException`. On MySQL `insertRow([])` used to produce `INSERT INTO t () VALUES ()` and is now rejected as well
- `from()` with a subquery and no alias produced a derived table without alias, rejected by MySQL and PostgreSQL; the alias is now required, as for joined subqueries
- MS SQL Server and Oracle `MERGE` silently dropped the upsert when `conflictTarget()` was missing or `insertConflictHandler()` was called before `insertRow()`; both now throw `MissingRequirementException`

### Changed

- Public API is marked with `@api`: `QueryBuilder`, `QbConsts`, `Condition`, `ConditionBuilder`, `ConditionJoin`, `ConditionBy`, `Field`, `ConflictBuilderInterface`, `UnionBuilder`, `RecursiveCteBuilder` and all exceptions; every other class, interface and trait (drivers, SQL builders, conflict builder implementations, `DriverFactory`, `DriverInterface`, `SqlSecurity`, `SqlCompactor`) is `@internal` and may change without a major release; concrete internal classes are `final`
- Development: the test suite runs on Testo instead of PHPUnit (`composer test`, `composer test:unit`, `composer test:integration`, `composer test:coverage-clover`)
- Development: PHPStan runs at max level with `phpstan-strict-rules`, `phpstan-disallowed-calls` and `dead-code-detector`; PHP_CodeSniffer checks PSR-12, Slevomat and PHPCompatibility rules (`composer phpcs`); `composer lint` runs all checks

### Removed

- Public methods of the classes that are now internal: `getName()` of the drivers, `SqlBuilderInterface::getBuiltQuery()`, static `create()` of the conflict builders (use `QueryBuilder::conflictBuilder()`), `DriverFactory::isSupported()` and `DriverFactory::getSupportedDrivers()` (use `QbConsts::getSupportedDrivers()`)

## [1.0.0] - 2026-09-23

First stable release. Requires PHP 8.4+.

### Added

- Drivers: MySQL/MariaDB, PostgreSQL, SQLite, MS SQL Server, Oracle, ClickHouse
- SELECT with `DISTINCT`, field aliases, subqueries in the field list, `FROM` and `JOIN`
- WHERE / HAVING conditions: comparisons, `in()` / `notIn()`, `like()` / `notLike()` with boundary modes, `between()`, `isNull()`, field-to-field comparisons (`eqField()`, `gtField()`, ...), nested groups, closure syntax, `raw()` conditions; MySQL `bitmask()` and `findInSet()`
- Subqueries in WHERE: `IN`, `NOT IN`, `EXISTS`, `NOT EXISTS`, comparisons
- JOIN: `INNER`, `LEFT`, `RIGHT`, `FULL`, `CROSS`, including joins with a subquery; unqualified fields in JOIN conditions are qualified with the joined table alias
- Index hints: `useIndex()`, `forceIndex()`, `ignoreIndex()` after `from()` or a join, with optional `FOR JOIN` / `FOR ORDER BY` / `FOR GROUP BY` scope; MySQL/MariaDB emit `USE` / `FORCE` / `IGNORE INDEX`, MS SQL Server emits `WITH (INDEX(...))` for `forceIndex()`, other drivers ignore hints
- `GROUP BY`, `ORDER BY`, `LIMIT` with offset, `limitWithTies()` (PostgreSQL, MS SQL Server, Oracle, ClickHouse)
- ClickHouse `FINAL` modifier
- INSERT (single and multiple rows, `insertFrom()` from a SELECT), UPDATE (`updateFromSelect()`), DELETE (`deleteFromSelect()`)
- Upsert via `conflictBuilder()`: `ON DUPLICATE KEY UPDATE` (MySQL), `ON CONFLICT ... DO UPDATE` (PostgreSQL, SQLite), `MERGE` (MS SQL Server, Oracle)
- `UNION` / `UNION ALL` with ORDER BY and LIMIT, recursive CTE
- Stored procedure calls with dialect-specific syntax
- Compact output mode (`build(true)`) that preserves string literals, quoted identifiers and comments
- `QueryBuilder::setServerVersion()` / `DriverInterface::setServerVersion()` for version-dependent syntax; MySQL 8.0.19+ uses the `INSERT ... AS new` row alias instead of deprecated `VALUES()` in `ON DUPLICATE KEY UPDATE`
- `DriverInterface::formatValue()`: SQL literals are formatted by PHP type, numeric-looking strings stay quoted, `false` is emitted as `0`, PostgreSQL booleans as `TRUE` / `FALSE`, `INF` / `NAN` throw `InvalidQueryException`
- Security: identifier validation, dialect-safe string escaping (MySQL doubles quotes and stays safe under `NO_BACKSLASH_ESCAPES`, PostgreSQL emits `E'...'` for values with backslashes), LIKE escaping with an explicit `ESCAPE` clause independent of server settings

[Unreleased]: https://github.com/alekvolsk/qbuilder/compare/v2.0.0...HEAD
[2.0.0]: https://github.com/alekvolsk/qbuilder/compare/v1.0.0...v2.0.0
[1.0.0]: https://github.com/alekvolsk/qbuilder/releases/tag/v1.0.0
