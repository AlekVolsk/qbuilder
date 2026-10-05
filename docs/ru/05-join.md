# JOIN операции

## LEFT JOIN

### Простой JOIN без алиаса

```php
use QBuilder\Condition\ConditionJoin;
use QBuilder\Condition\Field;

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
use QBuilder\Condition\ConditionJoin;
use QBuilder\Condition\Field;

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
use QBuilder\Condition\ConditionJoin;

$condition = ConditionJoin::create($qb, 'id', 'user_id', 'orders')
    ->and()->eq('status', 'active');

$qb->leftJoin('users', 'u', $condition);

// LEFT JOIN `users` AS `u` ON (`u`.`id` = `orders`.`user_id`) AND (`u`.`status` = 'active')
```

### Поля в условии JOIN

Поле без таблицы в условии JOIN относится к присоединяемой таблице и получает её алиас (или имя таблицы, если алиаса нет) — во всех СУБД и для любых условий: `eq`, `in`, `like`, `isNull`, `between` и т.д. Поле с таблицей (`'o.user_id'`, `Field::set('user_id', 'o')`, аргумент `targetTable`) остаётся как задано; `raw()` не изменяется.

```php
use QBuilder\Condition\ConditionJoin;

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
use QBuilder\Condition\ConditionJoin;

$qb->innerJoin(
    'orders',
    'o',
    ConditionJoin::create($qb, 'user_id', 'id', 'users')
);

// INNER JOIN `orders` AS `o` ON (`o`.`user_id` = `users`.`id`)
```

## RIGHT JOIN

```php
use QBuilder\Condition\ConditionJoin;

$qb->rightJoin(
    'orders',
    'o',
    ConditionJoin::create($qb, 'user_id', 'id', 'users')
);

// RIGHT JOIN `orders` AS `o` ON (`o`.`user_id` = `users`.`id`)
```

## Множественные JOIN

```php
use QBuilder\Condition\ConditionJoin;
use QBuilder\Condition\Field;

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
