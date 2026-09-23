# Introduction and Quick Start

## Introduction

QueryBuilder is a powerful tool for building SQL queries in PHP with support for multiple DBMSs:

- MySQL / MariaDB
- PostgreSQL
- ClickHouse
- MS SQL Server
- Oracle
- SQLite

**Key advantages:**

- ✅ Security: automatic escaping and protection against SQL injection
- ✅ Cross-platform: the same code works across different DBMSs
- ✅ Readability: fluent interface for building queries
- ✅ Type safety: PHPDoc annotations and strict typing

---

## Quick Start

### Initialization

```php
use QBuilder\QueryBuilder;
use QBuilder\QbConsts;

$qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
```

### Simple SELECT

```php
$sql = $qb->select('id', 'name', 'email')
    ->from('users')
    ->where()
        ->eq('status', 'active')
        ->end()
    ->build();

// SELECT `id`, `name`, `email` FROM `users` WHERE (`status` = 'active')
```

---

[Contents](index.md) · [SELECT Queries →](02-select.md)
