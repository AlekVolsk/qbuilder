# QBuilder - SQL Query Builder

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/php-8.4%2B-blue.svg)](https://www.php.net/)

**QBuilder** is a PHP library that builds SQL query strings through a fluent API for MySQL/MariaDB, PostgreSQL, ClickHouse, MS SQL Server, Oracle and SQLite. It generates SQL only and does not open connections or execute queries.

- SELECT, INSERT, UPDATE, DELETE queries
- WHERE and HAVING conditions with nested groups
- JOINs, subqueries, aggregates, UNION, recursive CTEs
- Two syntaxes: method chaining and closures
- PHPUnit test suite, PHPStan level 10

## Installation

```bash
composer require alekvolsk/qbuilder
```

## Requirements

- PHP >= 8.4

## Quick Start

```php
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;

$sql = (new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL))
    ->select('id', 'name', 'email')
    ->from('users')
    ->where()
        ->eq('status', 'active')
        ->end()
    ->build(true);

// SELECT `id`, `name`, `email` FROM `users` WHERE (`status` = 'active')
```

## Documentation

- [English](docs/en/index.md)
- [Русский](docs/ru/index.md)

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

[MIT](LICENSE)
