# JOIN операции

## LEFT JOIN

### Простой JOIN без алиаса

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

### JOIN с алиасом

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

### JOIN с несколькими условиями

```php
$condition = ConditionJoin::create($qb, 'id', 'user_id', 'orders')
    ->and()->eq('status', 'active');

$qb->leftJoin('users', 'u', $condition);

// LEFT JOIN `users` AS `u` ON (`u`.`id` = `orders`.`user_id`) AND (`u`.`status` = 'active')
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

## Множественные JOIN

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

[← Альтернативный синтаксис с замыканиями](04-closure-syntax.md) · [Содержание](index.md) · [Подзапросы →](06-subqueries.md)
