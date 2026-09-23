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

## DISTINCT

```php
$qb->select('country')
    ->distinct()
    ->from('users');

// SELECT DISTINCT `country` FROM `users`
```

---

[← Introduction and Quick Start](01-getting-started.md) · [Contents](index.md) · [WHERE Conditions →](03-where.md)
