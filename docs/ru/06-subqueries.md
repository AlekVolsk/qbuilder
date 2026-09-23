# Подзапросы

## Подзапрос в SELECT

```php
$subquery = $qb->subQuery()
    ->select(Field::set('COUNT(*)'))
    ->from('orders')
    ->where()
        ->eqField('orders', 'user_id', 'users', 'id')
        ->end();

$sql = $qb->select(
    Field::set('id', 'users'),
    Field::set('name', 'users'),
    Field::subquery($subquery, 'order_count')
)
->from('users')
->build();

// SELECT `users`.`id`, `users`.`name`,
// (SELECT COUNT(*) FROM `orders` WHERE (`orders`.`user_id` = `users`.`id`)) AS `order_count`
// FROM `users`
```

## Подзапрос в WHERE (IN)

```php
$subquery = $qb->subQuery()
    ->select('user_id')
    ->from('orders')
    ->where()
        ->gte('total', 1000)
        ->end();

$sql = $qb->select('*')
    ->from('users')
    ->where()
        ->inSubquery('id', $subquery)
        ->end()
    ->build();

// SELECT * FROM `users`
// WHERE (`id` IN (SELECT `user_id` FROM `orders` WHERE (`total` >= 1000)))
```

## Подзапрос в WHERE (NOT IN)

```php
$subquery = $qb->subQuery()
    ->select('user_id')
    ->from('orders')
    ->where()
        ->eq('status', 'cancelled')
        ->end();

$sql = $qb->select('*')
    ->from('users')
    ->where()
        ->notInSubquery('id', $subquery)
        ->end()
    ->build();

// SELECT * FROM `users`
// WHERE (`id` NOT IN (SELECT `user_id` FROM `orders` WHERE (`status` = 'cancelled')))
```

## Подзапрос в WHERE (EXISTS)

```php
$subquery = $qb->subQuery()
    ->select('*')
    ->from('orders')
    ->where()
        ->eqField('orders', 'user_id', 'users', 'id')
        ->and()->gte('total', 1000)
        ->end();

$sql = $qb->select('*')
    ->from('users')
    ->where()
        ->exists($subquery)
        ->end()
    ->build();

// SELECT * FROM `users`
// WHERE (EXISTS (SELECT * FROM `orders` WHERE (`orders`.`user_id` = `users`.`id`) AND (`total` >= 1000)))
```

## Подзапрос в WHERE (NOT EXISTS)

```php
$subquery = $qb->subQuery()
    ->select('*')
    ->from('orders')
    ->where()
        ->eqField('orders', 'user_id', 'users', 'id')
        ->end();

$sql = $qb->select('*')
    ->from('users')
    ->where()
        ->notExists($subquery)
        ->end()
    ->build();

// SELECT * FROM `users`
// WHERE (NOT EXISTS (SELECT * FROM `orders` WHERE (`orders`.`user_id` = `users`.`id`)))
```

## Подзапрос в WHERE (сравнение)

```php
$subquery = $qb->subQuery()
    ->select(Field::set('AVG(price)'))
    ->from('products')
    ->where()
        ->eq('category_id', 1)
        ->end();

$sql = $qb->select('*')
    ->from('products')
    ->where()
        ->compareSubquery('price', '>', $subquery)
        ->end()
    ->build();

// SELECT * FROM `products`
// WHERE (`price` > (SELECT AVG(price) FROM `products` WHERE (`category_id` = 1)))
```

## Подзапрос в FROM

```php
$subquery = $qb->subQuery()
    ->select('user_id', 'SUM(total) AS total_spent')
    ->from('orders')
    ->groupBy(ConditionBy::groupBy()->add('user_id'));

$sql = $qb->select(
    Field::set('id', 'users'),
    Field::set('name', 'users'),
    Field::set('total_spent', 'order_stats')
)
->from($subquery, 'order_stats')
->leftJoin('users', '', ConditionJoin::create($qb, 'id', 'user_id', 'order_stats'))
->build();

// SELECT `users`.`id`, `users`.`name`, `order_stats`.`total_spent`
// FROM (SELECT `user_id`, SUM(total) AS total_spent FROM `orders` GROUP BY `user_id`) AS `order_stats`
// LEFT JOIN `users` ON (`users`.`id` = `order_stats`.`user_id`)
```

## JOIN с подзапросом

```php
$subquery = $qb->subQuery()
    ->select(
        Field::set('user_id', 'orders'),
        'GROUP_CONCAT(`products`.`name`) AS product_names'
    )
    ->from('orders')
    ->leftJoin('products', '', ConditionJoin::create($qb, 'id', 'product_id', 'orders'))
    ->groupBy(ConditionBy::groupBy()->add('user_id', 'orders'));

$sql = $qb->select(
    Field::set('*', 'users'),
    Field::set('product_names', 'order_products')
)
->from('users')
->leftJoinFromSelect(
    $subquery,
    'order_products',
    ConditionJoin::create($qb, 'user_id', 'id', 'users')
)
->build();

// SELECT `users`.*, `order_products`.`product_names`
// FROM `users`
// LEFT JOIN (SELECT `orders`.`user_id`, GROUP_CONCAT(`products`.`name`) AS product_names
//            FROM `orders`
//            LEFT JOIN `products` ON (`products`.`id` = `orders`.`product_id`)
//            GROUP BY `orders`.`user_id`) AS `order_products`
// ON (`order_products`.`user_id` = `users`.`id`)
```

## Вложенные подзапросы

```php
$innerSubquery = $qb->subQuery()
    ->select(Field::set('MAX(created_at)'))
    ->from('orders')
    ->where()
        ->eqField('orders', 'user_id', 'users', 'id')
        ->end();

$outerSubquery = $qb->subQuery()
    ->select('user_id')
    ->from('orders')
    ->where()
        ->compareSubquery('created_at', '=', $innerSubquery)
        ->end();

$sql = $qb->select('*')
    ->from('users')
    ->where()
        ->inSubquery('id', $outerSubquery)
        ->end()
    ->build();

// SELECT * FROM `users`
// WHERE (`id` IN (
//     SELECT `user_id` FROM `orders`
//     WHERE (`created_at` = (
//         SELECT MAX(created_at) FROM `orders` WHERE (`orders`.`user_id` = `users`.`id`)
//     ))
// ))
```

---

[← JOIN операции](05-join.md) · [Содержание](index.md) · [Группировка, сортировка и LIMIT →](07-grouping-ordering-limit.md)
