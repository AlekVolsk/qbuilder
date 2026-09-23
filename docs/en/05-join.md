# 05 JOIN Operations

## LEFT JOIN

### Simple JOIN without an Alias

```php
use QBuilder\Condition\ConditionJoin;

$qb->select(
    Field::set('*', 'orders'),
    Field::set('name', 'users', 'user_name')
)
->from('orders')
->leftJoin(
    'users',
    '',
    ConditionJoin::create($qb, 'id', 'user_id', 'orders')
);

// SELECT `orders`.*, `users`.`name` AS `user_name`
// FROM `orders`
// LEFT JOIN `users` ON (`users`.`id` = `orders`.`user_id`)
```

### JOIN with an Alias

```php
$qb->select(
    Field::set('*', 'orders'),
    Field::set('name', 'u', 'user_name')
)
->from('orders')
->leftJoin(
    'users',
    'u',
    ConditionJoin::create($qb, 'id', 'user_id', 'orders')
);

// SELECT `orders`.*, `u`.`name` AS `user_name`
// FROM `orders`
// LEFT JOIN `users` AS `u` ON (`u`.`id` = `orders`.`user_id`)
```

### JOIN with Multiple Conditions

```php
$condition = ConditionJoin::create($qb, 'id', 'user_id', 'orders')
    ->and()->eq('status', 'active');

$qb->leftJoin('users', 'u', $condition);

// LEFT JOIN `users` AS `u` ON (`u`.`id` = `orders`.`user_id`) AND (`u`.`status` = 'active')
```

### Fields in a JOIN Condition

A field without a table in a JOIN condition refers to the joined table and gets its alias (or the table name when there is no alias) — on every database and for any condition: `eq`, `in`, `like`, `isNull`, `between`, etc. A field with a table (`'o.user_id'`, `Field::set('user_id', 'o')`, the `targetTable` argument) stays as given; `raw()` is not changed.

```php
$condition = ConditionJoin::create($qb, 'id', 'user_id', 'o')
    ->and()->in('status', ['active', 'trial'])
    ->and()->isNull('deleted_at');

$qb->select('*')->from('orders', 'o')->innerJoin('users', 'u', $condition);

// PostgreSQL:
// INNER JOIN "users" AS "u" ON ("u"."id" = "o"."user_id") AND ("u"."status" IN ('active', 'trial'))
// AND ("u"."deleted_at" IS NULL)
```

## INNER JOIN

```php
$qb->innerJoin(
    'orders',
    'o',
    ConditionJoin::create($qb, 'user_id', 'id', 'users')
);

// INNER JOIN `orders` AS `o` ON (`o`.`user_id` = `users`.`id`)
```

## RIGHT JOIN

```php
$qb->rightJoin(
    'orders',
    'o',
    ConditionJoin::create($qb, 'user_id', 'id', 'users')
);

// RIGHT JOIN `orders` AS `o` ON (`o`.`user_id` = `users`.`id`)
```

## Multiple JOINs

```php
$qb->select(
    Field::set('*', 'orders'),
    Field::set('name', 'users', 'user_name'),
    Field::set('name', 'products', 'product_name')
)
->from('orders')
->leftJoin('users', '', ConditionJoin::create($qb, 'id', 'user_id', 'orders'))
->leftJoin('products', '', ConditionJoin::create($qb, 'id', 'product_id', 'orders'));

// SELECT `orders`.*, `users`.`name` AS `user_name`, `products`.`name` AS `product_name`
// FROM `orders`
// LEFT JOIN `users` ON (`users`.`id` = `orders`.`user_id`)
// LEFT JOIN `products` ON (`products`.`id` = `orders`.`product_id`)
```

---

[← 04 Closure Syntax](04-closure-syntax.md) · [Contents](index.md) · [06 Subqueries →](06-subqueries.md)
