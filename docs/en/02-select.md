# SELECT Queries

## Selecting Fields

### Simple Fields

```php
$qb->select('id', 'name', 'email')
    ->from('users');

// SELECT `id`, `name`, `email` FROM `users`
```

### All Fields

```php
$qb->select('*')
    ->from('users');

// SELECT * FROM `users`
```

### Fields with Tables

```php
use QBuilder\Condition\Field;

$qb->select(
    Field::set('id', 'users'),
    Field::set('name', 'users'),
    Field::set('email', 'users')
)->from('users');

// SELECT `users`.`id`, `users`.`name`, `users`.`email` FROM `users`
```

### Fields with Aliases

```php
$qb->select(
    Field::set('id', 'users'),
    Field::set('name', 'users', 'user_name'),
    Field::set('email', 'users', 'user_email')
)->from('users');

// SELECT `users`.`id`, `users`.`name` AS `user_name`, `users`.`email` AS `user_email` FROM `users`
```

### All Fields of a Table

```php
$qb->select(
    Field::set('id', 'users'),
    Field::set('*', 'orders')
)->from('users')
->leftJoin('orders', '', ConditionJoin::create($qb, 'user_id', 'id', 'users'));

// SELECT `users`.`id`, `orders`.* FROM `users` LEFT JOIN `orders` ON (...)
```

### SQL Functions

```php
$qb->select(
    'COUNT(*) AS total',
    'MAX(price) AS max_price',
    'MIN(created_at) AS first_date'
)->from('orders');

// SELECT COUNT(*) AS total, MAX(`price`) AS max_price, MIN(`created_at`) AS first_date FROM `orders`
```

**Important:** QueryBuilder automatically escapes identifiers inside SQL expressions:

- `SUM(amount)` → `SUM(`amount`)`
- `DATE_FORMAT(table.field, "%Y")` → `DATE_FORMAT(`table`.`field`, "%Y")`
- Already escaped identifiers are left untouched

### DATE_FORMAT and Other Functions

```php
$qb->select(
    Field::set('id', 'orders'),
    'DATE_FORMAT(`orders`.`created_at`, "%d-%m-%Y") AS created_date',
    'CONCAT(`users`.`first_name`, " ", `users`.`last_name`) AS full_name'
)->from('orders');
```

### Numeric Literal

An unsigned integer is selected as a literal, unquoted — e.g. for `EXISTS (SELECT 1 ...)`:

```php
$qb->select('1')->from('orders');
// SELECT 1 FROM `orders`

$qb->select(Field::set('0', '', 'zero'));
// SELECT 0 AS `zero`
```

### Index Hints

`useIndex()`, `forceIndex()` and `ignoreIndex()` go in the chain right after the table they apply to: after `from()` or after a join. Each takes an index name or an array of names and an optional scope — `QbConsts::INDEX_FOR_JOIN`, `INDEX_FOR_ORDER_BY`, `INDEX_FOR_GROUP_BY`.

```php
use QBuilder\QbConsts;

$qb->select('id')
    ->from('orders', 'o')->forceIndex('idx_created')
    ->ignoreIndex(['idx_a', 'idx_b'], QbConsts::INDEX_FOR_ORDER_BY)
    ->leftJoin('users', 'u', $condition)->useIndex('PRIMARY')
    ->build();

// MySQL:
// SELECT `o`.`id` FROM `orders` AS `o` FORCE INDEX (`idx_created`) IGNORE INDEX FOR ORDER BY (`idx_a`, `idx_b`)
// LEFT JOIN `users` AS `u` USE INDEX (`PRIMARY`) ON (...)
```

| Database | Support |
|---|---|
| MySQL / MariaDB | all three hints and scopes; `useIndex([])` → `USE INDEX ()` — use no indexes |
| MS SQL Server | only `forceIndex()` without scope → `WITH (INDEX([ix_a], [ix_b]))`; `useIndex()`, `ignoreIndex()` and scopes throw `UnsupportedFeatureException` — the MS SQL INDEX table hint always forces the index |
| PostgreSQL, SQLite, Oracle, ClickHouse | hints are ignored: they do not change the query result, so the same code runs on every database |

Restrictions: hints are allowed only in SELECT and only on a table (not on a subquery); `useIndex()` and `forceIndex()` cannot be combined for one table; calling a hint before `from()` throws `InvalidQueryException`.

## DISTINCT

```php
$qb->select('country')
    ->distinct()
    ->from('users');

// SELECT DISTINCT `country` FROM `users`
```

---

[← Introduction and Quick Start](01-getting-started.md) · [Contents](index.md) · [WHERE Conditions →](03-where.md)
