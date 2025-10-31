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
