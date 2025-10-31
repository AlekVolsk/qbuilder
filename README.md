# QBuilder - SQL Query Builder

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/php-8.4%2B-blue.svg)](https://www.php.net/)

**QBuilder** — это комплексная библиотека для построения SQL-запросов на PHP, включающая:

- Fluent API для построения SELECT, INSERT, UPDATE, DELETE запросов
- Систему условий (WHERE, HAVING) с поддержкой вложенных групп
- Поддержку подзапросов, JOIN'ов, агрегатных функций
- Два синтаксиса: стандартный (цепочка методов) и альтернативный (замыкания)
- Интеграцию с PDO и собственной ORM
- Полное покрытие тестами (PHPUnit)
- Статический анализ (PHPStan level 10 (max))
- Подробную документацию

## Содержание

1. [Введение](#введение)
2. [Быстрый старт](#быстрый-старт)
3. [SELECT запросы](#select-запросы)
4. [WHERE условия](#where-условия)
5. [JOIN операции](#join-операции)
6. [Подзапросы](#подзапросы)
7. [GROUP BY и HAVING](#group-by-и-having)
8. [ORDER BY и LIMIT](#order-by-и-limit)
9. [INSERT запросы](#insert-запросы)
10. [UPDATE запросы](#update-запросы)
11. [DELETE запросы](#delete-запросы)
12. [Рекурсивные запросы (CTE)](#рекурсивные-запросы-cte)
13. [Сложные примеры](#сложные-примеры)
14. [Безопасность](#безопасность)

---

## Введение

QueryBuilder - это мощный инструмент для построения SQL-запросов в PHP с поддержкой множества СУБД:

- MySQL / MariaDB
- PostgreSQL
- ClickHouse
- MS SQL Server
- Oracle
- SQLite

**Основные преимущества:**

- ✅ Безопасность: автоматическое экранирование и защита от SQL-инъекций
- ✅ Кроссплатформенность: один код работает на разных СУБД
- ✅ Читаемость: fluent interface для построения запросов
- ✅ Типобезопасность: PHPDoc аннотации и строгая типизация

---

## Быстрый старт

### Инициализация

```php
use Ep\App\QBuilder\QueryBuilder;
use Ep\App\QBuilder\QbConsts;

$qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
```

### Простой SELECT

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

## SELECT запросы

### Выбор полей

#### Простые поля

```php
$qb->select('id', 'name', 'email')
    ->from('users');

// SELECT `id`, `name`, `email` FROM `users`
```

#### Все поля

```php
$qb->select('*')
    ->from('users');

// SELECT * FROM `users`
```

#### Поля с таблицами

```php
use Ep\App\QBuilder\Condition\Field;

$qb->select(
    Field::set('id', 'users'),
    Field::set('name', 'users'),
    Field::set('email', 'users')
)->from('users');

// SELECT `users`.`id`, `users`.`name`, `users`.`email` FROM `users`
```

#### Поля с алиасами

```php
$qb->select(
    Field::set('id', 'users'),
    Field::set('name', 'users', 'user_name'),
    Field::set('email', 'users', 'user_email')
)->from('users');

// SELECT `users`.`id`, `users`.`name` AS `user_name`, `users`.`email` AS `user_email` FROM `users`
```

#### Все поля таблицы

```php
$qb->select(
    Field::set('id', 'users'),
    Field::set('*', 'orders')
)->from('users')
->leftJoin('orders', '', ConditionJoin::create($qb, 'user_id', 'id', 'users'));

// SELECT `users`.`id`, `orders`.* FROM `users` LEFT JOIN `orders` ON (...)
```

#### SQL функции

```php
$qb->select(
    'COUNT(*) AS total',
    'MAX(price) AS max_price',
    'MIN(created_at) AS first_date'
)->from('orders');

// SELECT COUNT(*) AS total, MAX(`price`) AS max_price, MIN(`created_at`) AS first_date FROM `orders`
```

**Важно:** QueryBuilder автоматически экранирует идентификаторы внутри SQL-выражений:

- `SUM(amount)` → `SUM(`amount`)`
- `DATE_FORMAT(table.field, "%Y")` → `DATE_FORMAT(`table`.`field`, "%Y")`
- Уже экранированные идентификаторы не трогаются

#### DATE_FORMAT и другие функции

```php
$qb->select(
    Field::set('id', 'orders'),
    'DATE_FORMAT(`orders`.`created_at`, "%d-%m-%Y") AS created_date',
    'CONCAT(`users`.`first_name`, " ", `users`.`last_name`) AS full_name'
)->from('orders');
```

### DISTINCT

```php
$qb->select('country')
    ->distinct()
    ->from('users');

// SELECT DISTINCT `country` FROM `users`
```

---

## WHERE условия

QueryBuilder поддерживает два синтаксиса для построения WHERE условий:

1. **Стандартный синтаксис** - классический подход с явным вызовом `end()`
2. **Альтернативный синтаксис с замыканиями** - современный подход без необходимости вызова `end()`

Оба синтаксиса полностью эквивалентны и генерируют одинаковый SQL.

### Базовые операторы

#### Равенство (=)

**Стандартный синтаксис:**

```php
$qb->select('*')
    ->from('users')
    ->where()
        ->eq('status', 'active')
        ->end();

// WHERE (`status` = 'active')
```

**Альтернативный синтаксис с замыканием:**

```php
$qb->select('*')
    ->from('users')
    ->where(static fn (ConditionBuilder $q): ConditionBuilder => $q->eq('status', 'active'));

// WHERE (`status` = 'active')
```

#### Неравенство (!=)

**Стандартный синтаксис:**

```php
$qb->where()
    ->neq('status', 'deleted')
    ->end();

// WHERE (`status` != 'deleted')
```

**Альтернативный синтаксис:**

```php
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder => $q->neq('status', 'deleted'));

// WHERE (`status` != 'deleted')
```

#### Больше / Меньше

**Стандартный синтаксис:**

```php
$qb->where()
    ->gt('age', 18)
    ->and()->lt('age', 65)
    ->end();

// WHERE (`age` > 18) AND (`age` < 65)
```

**Альтернативный синтаксис:**

```php
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->gt('age', 18)->and()->lt('age', 65)
);

// WHERE (`age` > 18) AND (`age` < 65)
```

#### Больше или равно / Меньше или равно

**Стандартный синтаксис:**

```php
$qb->where()
    ->gte('price', 100)
    ->and()->lte('price', 1000)
    ->end();

// WHERE (`price` >= 100) AND (`price` <= 1000)
```

**Альтернативный синтаксис:**

```php
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->gte('price', 100)->and()->lte('price', 1000)
);

// WHERE (`price` >= 100) AND (`price` <= 1000)
```

### Логические операторы

#### AND

**Стандартный синтаксис:**

```php
$qb->where()
    ->eq('status', 'active')
    ->and()->eq('verified', 1)
    ->and()->gt('balance', 0)
    ->end();

// WHERE (`status` = 'active') AND (`verified` = 1) AND (`balance` > 0)
```

**Альтернативный синтаксис:**

```php
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->eq('status', 'active')
      ->and()->eq('verified', 1)
      ->and()->gt('balance', 0)
);

// WHERE (`status` = 'active') AND (`verified` = 1) AND (`balance` > 0)
```

#### OR

**Стандартный синтаксис:**

```php
$qb->where()
    ->eq('status', 'active')
    ->or()->eq('status', 'pending')
    ->end();

// WHERE (`status` = 'active') OR (`status` = 'pending')
```

**Альтернативный синтаксис:**

```php
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->eq('status', 'active')->or()->eq('status', 'pending')
);

// WHERE (`status` = 'active') OR (`status` = 'pending')
```

#### Комбинация AND и OR

**Стандартный синтаксис:**

```php
$qb->where()
    ->eq('country', 'US')
    ->and()
    ->startGroup()
        ->eq('status', 'active')
        ->or()->eq('status', 'pending')
    ->endGroup()
    ->end();

// WHERE (`country` = 'US') AND ((`status` = 'active') OR (`status` = 'pending'))
```

**Стандартный синтаксис (используя andGroup/orGroup):**

```php
$qb->where()
    ->eq('country', 'US')
    ->andGroup()
        ->eq('status', 'active')
        ->or()->eq('status', 'pending')
    ->end()
    ->end();

// WHERE (`country` = 'US') AND ((`status` = 'active') OR (`status` = 'pending'))
```

**Альтернативный синтаксис с замыканиями:**

```php
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->eq('country', 'US')
      ->andGroup(static fn (ConditionBuilder $q2): ConditionBuilder => $q2
        ->eq('status', 'active')
        ->or()
        ->eq('status', 'pending'))
);

// WHERE (`country` = 'US') AND ((`status` = 'active') OR (`status` = 'pending'))
```

### Специальные операторы

#### IN

**Стандартный синтаксис:**

```php
$qb->where()
    ->in('status', ['active', 'pending', 'verified'])
    ->end();

// WHERE (`status` IN ('active', 'pending', 'verified'))
```

**Альтернативный синтаксис:**

```php
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->in('status', ['active', 'pending', 'verified'])
);

// WHERE (`status` IN ('active', 'pending', 'verified'))
```

#### NOT IN

**Стандартный синтаксис:**

```php
$qb->where()
    ->notIn('status', ['deleted', 'banned'])
    ->end();

// WHERE (`status` NOT IN ('deleted', 'banned'))
```

**Альтернативный синтаксис:**

```php
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->notIn('status', ['deleted', 'banned'])
);

// WHERE (`status` NOT IN ('deleted', 'banned'))
```

#### BETWEEN

**Стандартный синтаксис:**

```php
$qb->where()
    ->between('age', 18, 65)
    ->end();

// WHERE (`age` BETWEEN 18 AND 65)
```

**Альтернативный синтаксис:**

```php
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->between('age', 18, 65)
);

// WHERE (`age` BETWEEN 18 AND 65)
```

#### NOT BETWEEN

**Стандартный синтаксис:**

```php
$qb->where()
    ->notBetween('age', 18, 65)
    ->end();

// WHERE (`age` NOT BETWEEN 18 AND 65)
```

**Альтернативный синтаксис:**

```php
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->notBetween('age', 18, 65)
);

// WHERE (`age` NOT BETWEEN 18 AND 65)
```

#### LIKE

**Стандартный синтаксис:**

```php
$qb->where()
    ->like('name', '%John%')
    ->end();

// WHERE (`name` LIKE '%John%')
```

**Альтернативный синтаксис:**

```php
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->like('name', '%John%')
);

// WHERE (`name` LIKE '%John%')
```

**LIKE с типами границ:**

**Стандартный синтаксис:**

```php
use Ep\App\QBuilder\QbConsts;

// Полное совпадение (по умолчанию)
$qb->where()->like('name', 'John', QbConsts::LIKE_FULL)->end();
// WHERE (`name` LIKE '%John%')

// Начинается с
$qb->where()->like('name', 'John', QbConsts::LIKE_RIGHT)->end();
// WHERE (`name` LIKE 'John%')

// Заканчивается на
$qb->where()->like('name', 'John', QbConsts::LIKE_LEFT)->end();
// WHERE (`name` LIKE '%John')
```

**Альтернативный синтаксис:**

```php
use Ep\App\QBuilder\QbConsts;

// Полное совпадение (по умолчанию)
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->like('name', 'John', QbConsts::LIKE_FULL)
);
// WHERE (`name` LIKE '%John%')

// Начинается с
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->like('name', 'John', QbConsts::LIKE_RIGHT)
);
// WHERE (`name` LIKE 'John%')

// Заканчивается на
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->like('name', 'John', QbConsts::LIKE_LEFT)
);
// WHERE (`name` LIKE '%John')
```

#### NOT LIKE

**Стандартный синтаксис:**

```php
$qb->where()
    ->notLike('name', '%Admin%')
    ->end();

// WHERE (`name` NOT LIKE '%Admin%')
```

**Альтернативный синтаксис:**

```php
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->notLike('name', '%Admin%')
);

// WHERE (`name` NOT LIKE '%Admin%')
```

#### IS NULL / IS NOT NULL

**Стандартный синтаксис:**

```php
$qb->where()
    ->isNull('deleted_at')
    ->and()->isNotNull('email')
    ->end();

// WHERE (`deleted_at` IS NULL) AND (`email` IS NOT NULL)
```

**Альтернативный синтаксис:**

```php
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->isNull('deleted_at')->and()->isNotNull('email')
);

// WHERE (`deleted_at` IS NULL) AND (`email` IS NOT NULL)
```

### Сравнение полей

#### Сравнение полей из разных таблиц

**Стандартный синтаксис:**

```php
$qb->where()
    ->eqField('orders', 'user_id', 'users', 'id')
    ->end();

// WHERE (`orders`.`user_id` = `users`.`id`)
```

**Альтернативный синтаксис:**

```php
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->eqField('orders', 'user_id', 'users', 'id')
);

// WHERE (`orders`.`user_id` = `users`.`id`)
```

#### Другие операторы сравнения полей

**Стандартный синтаксис:**

```php
$qb->where()
    ->gtField('orders', 'total', 'users', 'credit_limit')
    ->and()->neqField('orders', 'status', 'users', 'default_status')
    ->end();

// WHERE (`orders`.`total` > `users`.`credit_limit`) AND (`orders`.`status` != `users`.`default_status`)
```

**Альтернативный синтаксис:**

```php
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->gtField('orders', 'total', 'users', 'credit_limit')
      ->and()
      ->neqField('orders', 'status', 'users', 'default_status')
);

// WHERE (`orders`.`total` > `users`.`credit_limit`) AND (`orders`.`status` != `users`.`default_status`)
```

### Битовые операции (MySQL)

#### Проверка битовой маски

**Стандартный синтаксис:**

```php
$qb->where()
    ->bitmask('permissions', [1 => 1, 2 => 1, 4 => 0])
    ->end();

// WHERE ((`permissions` & 1 = 1)) AND ((`permissions` & 2 = 2)) AND ((`permissions` & 4 = 0))
```

**Альтернативный синтаксис:**

```php
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder => $q->bitmask('permissions', [1 => 1, 2 => 1, 4 => 0]));

// WHERE ((`permissions` & 1 = 1)) AND ((`permissions` & 2 = 2)) AND ((`permissions` & 4 = 0))
```

#### Отрицание битовой маски

**Стандартный синтаксис:**

```php
$qb->where()
    ->notBitmask('permissions', [8 => 1])
    ->end();

// WHERE NOT ((`permissions` & 8 = 8))
```

**Альтернативный синтаксис:**

```php
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->notBitmask('permissions', [8 => 1])
);

// WHERE NOT ((`permissions` & 8 = 8))
```

### FIND_IN_SET (MySQL)

**Стандартный синтаксис:**

```php
$qb->where()
    ->findInSet('tags', 'admin')
    ->end();

// WHERE (FIND_IN_SET('admin', `tags`))

$qb->where()
    ->findInSet('tags', ['admin', 'moderator'])
    ->end();

// WHERE (FIND_IN_SET('admin', `tags`) OR FIND_IN_SET('moderator', `tags`))
```

**Альтернативный синтаксис:**

```php
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->findInSet('tags', 'admin')
);

// WHERE (FIND_IN_SET('admin', `tags`))

$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->findInSet('tags', ['admin', 'moderator'])
);

// WHERE (FIND_IN_SET('admin', `tags`) OR FIND_IN_SET('moderator', `tags`))
```

### Произвольные SQL условия (raw)

**⚠️ ВНИМАНИЕ:** Использование `raw()` обходит защиту от SQL-инъекций. Используйте только с проверенными данными!

**Стандартный синтаксис:**

```php
$qb->where()
    ->eq('status', 'active')
    ->and()
    ->raw('YEAR(created_at) = 2024')
    ->end();

// WHERE (`status` = 'active') AND (YEAR(created_at) = 2024)
```

**Альтернативный синтаксис:**

```php
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->eq('status', 'active')->and()->raw('YEAR(created_at) = 2024')
);

// WHERE (`status` = 'active') AND (YEAR(created_at) = 2024)
```

**Когда использовать `raw()`:**

- ✅ Сложные SQL-выражения, которые не поддерживаются стандартными методами
- ✅ Специфичные функции СУБД
- ❌ **НИКОГДА** не используйте с пользовательским вводом без валидации

---

## JOIN операции

### LEFT JOIN

#### Простой JOIN без алиаса

```php
use Ep\App\QBuilder\Condition\ConditionJoin;

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

#### JOIN с алиасом

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

#### JOIN с несколькими условиями

```php
$condition = ConditionJoin::create($qb, 'id', 'user_id', 'orders')
    ->and()->eq('status', 'active');

$qb->leftJoin('users', 'u', $condition);

// LEFT JOIN `users` AS `u` ON (`u`.`id` = `orders`.`user_id`) AND (`u`.`status` = 'active')
```

### INNER JOIN

```php
$qb->innerJoin(
    'orders',
    'o',
    ConditionJoin::create($qb, 'user_id', 'id', 'users')
);

// INNER JOIN `orders` AS `o` ON (`o`.`user_id` = `users`.`id`)
```

### RIGHT JOIN

```php
$qb->rightJoin(
    'orders',
    'o',
    ConditionJoin::create($qb, 'user_id', 'id', 'users')
);

// RIGHT JOIN `orders` AS `o` ON (`o`.`user_id` = `users`.`id`)
```

### Множественные JOIN

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

## Подзапросы

### Подзапрос в SELECT

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

### Подзапрос в WHERE (IN)

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

### Подзапрос в WHERE (NOT IN)

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

### Подзапрос в WHERE (EXISTS)

```php
$subquery = $qb->subQuery()
    ->select('1')
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
// WHERE (EXISTS (SELECT `1` FROM `orders` WHERE (`orders`.`user_id` = `users`.`id`) AND (`total` >= 1000)))
```

### Подзапрос в WHERE (NOT EXISTS)

```php
$subquery = $qb->subQuery()
    ->select('1')
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
// WHERE (NOT EXISTS (SELECT `1` FROM `orders` WHERE (`orders`.`user_id` = `users`.`id`)))
```

### Подзапрос в WHERE (сравнение)

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

### Подзапрос в FROM

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

### JOIN с подзапросом

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

### Вложенные подзапросы

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

## GROUP BY и HAVING

### Простой GROUP BY

```php
use Ep\App\QBuilder\Condition\ConditionBy;

$qb->select('status', 'COUNT(*) AS total')
    ->from('orders')
    ->groupBy(ConditionBy::groupBy()->add('status'));

// SELECT `status`, COUNT(*) AS total FROM `orders` GROUP BY `status`
```

### GROUP BY с несколькими полями

```php
$qb->select('country', 'city', 'COUNT(*) AS total')
    ->from('users')
    ->groupBy(
        ConditionBy::groupBy()
            ->add('country')
            ->add('city')
    );

// SELECT `country`, `city`, COUNT(*) AS total FROM `users` GROUP BY `country`, `city`
```

### GROUP BY с таблицей

```php
$qb->groupBy(
    ConditionBy::groupBy()
        ->add('status', 'orders')
        ->add('user_id', 'orders')
);

// GROUP BY `orders`.`status`, `orders`.`user_id`
```

### HAVING

```php
$qb->select('status', 'COUNT(*) AS total')
    ->from('orders')
    ->groupBy(ConditionBy::groupBy()->add('status'))
    ->having()
        ->gt('COUNT(*)', 5)
        ->end();

// SELECT `status`, COUNT(*) AS total
// FROM `orders`
// GROUP BY `status`
// HAVING (COUNT(*) > 5)
```

### HAVING с несколькими условиями

```php
$qb->having()
    ->gt('COUNT(*)', 5)
    ->and()->lt('SUM(total)', 10000)
    ->end();

// HAVING (COUNT(*) > 5) AND (SUM(total) < 10000)
```

---

## ORDER BY и LIMIT

### ORDER BY

#### По возрастанию (ASC)

```php
$qb->orderBy(ConditionBy::orderBy()->asc('name'));

// ORDER BY `name` ASC
```

#### По убыванию (DESC)

```php
$qb->orderBy(ConditionBy::orderBy()->desc('created_at'));

// ORDER BY `created_at` DESC
```

#### Несколько полей

```php
$qb->orderBy(
    ConditionBy::orderBy()
        ->asc('country')
        ->desc('created_at')
        ->asc('name')
);

// ORDER BY `country` ASC, `created_at` DESC, `name` ASC
```

#### С указанием таблицы

```php
$qb->orderBy(
    ConditionBy::orderBy()
        ->asc('status', 'orders')
        ->desc('created_at', 'orders')
);

// ORDER BY `orders`.`status` ASC, `orders`.`created_at` DESC
```

### LIMIT

#### Только количество

```php
$qb->limit(10);

// LIMIT 10
```

#### С offset (MySQL)

```php
$qb->limit(10, 20);

// LIMIT 20, 10  (пропустить 20, взять 10)
```

### LIMIT WITH TIES (ClickHouse, PostgreSQL, MS SQL, Oracle)

```php
$qb->select('*')
    ->from('users')
    ->orderBy(ConditionBy::orderBy()->desc('score'))
    ->limitWithTies(10);

// ClickHouse: SELECT * FROM `users` ORDER BY `score` DESC LIMIT 10 WITH TIES
// PostgreSQL: SELECT * FROM "users" ORDER BY "score" DESC FETCH FIRST 10 ROWS WITH TIES
// MS SQL: SELECT TOP 10 WITH TIES * FROM [users] ORDER BY [score] DESC
```

---

## INSERT запросы

### Вставка одной записи

```php
$qb->insert('users')
    ->insertRow([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'age' => 30,
        'status' => 'active'
    ])
    ->build();

// INSERT INTO `users` (`name`, `email`, `age`, `status`)
// VALUES ('John Doe', 'john@example.com', 30, 'active')
```

### Вставка нескольких записей

```php
$qb->insert('users')
    ->insertRow(['name' => 'John', 'age' => 30])
    ->insertRow(['name' => 'Jane', 'age' => 25])
    ->insertRow(['name' => 'Bob', 'age' => 35])
    ->build();

// INSERT INTO `users` (`name`, `age`)
// VALUES ('John', 30), ('Jane', 25), ('Bob', 35)
```

### INSERT с ON DUPLICATE KEY UPDATE (MySQL)

```php
$qb->insert('users')
    ->insertRow(['email' => 'john@example.com', 'name' => 'John', 'visits' => 1])
    ->insertConflictHandler(
        $qb->conflictBuilder()
            ->set('name', 'John Updated')
            ->increment('visits')
    )
    ->build();

// INSERT INTO `users` (`email`, `name`, `visits`)
// VALUES ('john@example.com', 'John', 1)
// ON DUPLICATE KEY UPDATE `name` = 'John Updated', `visits` = `visits` + 1
```

### INSERT из подзапроса

```php
$subQuery = $qb->subQuery()
    ->select('name', 'email', 'age')
    ->from('temp_users')
    ->where()
        ->eq('verified', 1)
        ->end();

$qb->insert('users')
    ->insertFrom($subQuery, ['name', 'email', 'age'])
    ->build();

// INSERT INTO `users` (`name`, `email`, `age`)
// SELECT `name`, `email`, `age` FROM `temp_users` WHERE (`verified` = 1)
```

---

## UPDATE запросы

### Простой UPDATE

```php
$qb->update('users')
    ->updateRow([
        'status' => 'inactive',
        'updated_at' => 'NOW()'
    ])
    ->where()
        ->eq('id', 123)
        ->end()
    ->build();

// UPDATE `users` SET `status` = 'inactive', `updated_at` = NOW() WHERE (`id` = 123)
```

### UPDATE с несколькими условиями

```php
$qb->update('users')
    ->updateRow(['status' => 'verified'])
    ->where()
        ->eq('email_verified', 1)
        ->and()->isNull('verified_at')
        ->end()
    ->build();

// UPDATE `users` SET `status` = 'verified'
// WHERE (`email_verified` = 1) AND (`verified_at` IS NULL)
```

### UPDATE с LIMIT (MySQL)

```php
$qb->update('users')
    ->updateRow(['status' => 'inactive'])
    ->where()
        ->lt('last_login', '2020-01-01')
        ->end()
    ->limit(100)
    ->build();

// UPDATE `users` SET `status` = 'inactive'
// WHERE (`last_login` < '2020-01-01')
// LIMIT 100
```

### UPDATE из подзапроса

```php
$subQuery = $qb->subQuery()
    ->select('user_id')
    ->from('temp_updates')
    ->where()
        ->eq('status', 1)
        ->end();

$qb->updateFromSelect('users', $subQuery, ['status' => 'verified'], 'id')
    ->build();

// UPDATE `users` SET `status` = 'verified'
// WHERE `id` IN (SELECT `user_id` FROM `temp_updates` WHERE (`status` = 1))
```

---

## DELETE запросы

### Простой DELETE

```php
$qb->delete('users')
    ->where()
        ->eq('id', 123)
        ->end()
    ->build();

// DELETE FROM `users` WHERE (`id` = 123)
```

### DELETE с несколькими условиями

```php
$qb->delete('users')
    ->where()
        ->eq('status', 'deleted')
        ->and()->lt('created_at', '2020-01-01')
        ->end()
    ->build();

// DELETE FROM `users` WHERE (`status` = 'deleted') AND (`created_at` < '2020-01-01')
```

### DELETE с LIMIT (MySQL)

```php
$qb->delete('users')
    ->where()
        ->eq('status', 'spam')
        ->end()
    ->limit(1000)
    ->build();

// DELETE FROM `users` WHERE (`status` = 'spam') LIMIT 1000
```

### DELETE из подзапроса

```php
$subQuery = $qb->subQuery()
    ->select('user_id')
    ->from('temp_deletions')
    ->where()
        ->eq('status', 'deleted')
        ->end();

$qb->deleteFromSelect('users', $subQuery, 'id')
    ->build();

// DELETE FROM `users`
// WHERE `id` IN (SELECT `user_id` FROM `temp_deletions` WHERE (`status` = 'deleted'))
```

---

## Рекурсивные запросы (CTE)

Рекурсивные CTE (Common Table Expressions) позволяют работать с иерархическими данными, такими как деревья категорий, организационные структуры, графы зависимостей и т.д.

### Поддержка СУБД

- ✅ MySQL 8.0+
- ✅ MariaDB 10.2+
- ✅ PostgreSQL
- ✅ MS SQL Server
- ✅ Oracle
- ❌ ClickHouse (не поддерживает рекурсивные CTE)
- ❌ SQLite 3.8.3+ (поддержка ограничена)

### Основы рекурсивных CTE

Рекурсивный CTE состоит из трёх частей:

1. **Base Query (якорный запрос)** - начальная точка рекурсии
2. **Recursive Query (рекурсивный запрос)** - запрос, ссылающийся на CTE
3. **Final Select** - финальный SELECT, использующий результат CTE

### Создание рекурсивного CTE

```php
use Ep\App\QBuilder\Builder\RecursiveCteBuilder;

// Создание через QueryBuilder
$cte = $qb->recursiveCte('TableName');

// Или напрямую
$cte = new RecursiveCteBuilder($qb, 'TableName');
```

### Пример 1: Дерево категорий с уровнями

Построение полного дерева категорий с подсчётом уровня вложенности:

```php
use Ep\App\QBuilder\Builder\RecursiveCteBuilder;
use Ep\App\QBuilder\Condition\ConditionBy;
use Ep\App\QBuilder\Condition\ConditionJoin;
use Ep\App\QBuilder\Condition\Field;

$qb = new QueryBuilder();

// Якорный запрос: корневые категории (без родителя)
$baseQuery = $qb->subQuery()
    ->select('id', 'name', 'parent_id', '1 AS level')
    ->from('categories')
    ->where()->isNull('parent_id')->end();

// Рекурсивный запрос: дочерние категории
$joinCondition = ConditionJoin::create($qb, 'id', 'parent_id', 'c');

$recursiveQuery = $qb->subQuery()
    ->select(
        Field::set('id', 'c'),
        Field::set('name', 'c'),
        Field::set('parent_id', 'c'),
        '`ct`.`level` + 1 AS level'
    )
    ->from('categories', 'c')
    ->innerJoin('category_tree', 'ct', $joinCondition);

// Финальный запрос: выборка всех категорий
$finalQuery = $qb->subQuery()
    ->select('*')
    ->from('category_tree')
    ->orderBy(ConditionBy::orderBy()->asc('level')->asc('name'));

// Построение CTE
$cte = $qb->recursiveCte('category_tree');
$sql = $cte->baseQuery($baseQuery)
    ->recursiveQuery($recursiveQuery)
    ->finalSelect($finalQuery)
    ->build();

// Результат:
// WITH RECURSIVE `category_tree` AS (
//     SELECT `id`, `name`, `parent_id`, 1 AS level
//     FROM `categories`
//     WHERE (`parent_id` IS NULL)
//     UNION ALL
//     SELECT `c`.`id`, `c`.`name`, `c`.`parent_id`, `ct`.`level` + 1 AS level
//     FROM `categories` AS `c`
//     INNER JOIN `category_tree` AS `ct` ON (`ct`.`id` = `c`.`parent_id`)
// )
// SELECT * FROM `category_tree`
// ORDER BY `level` ASC, `name` ASC
```

### Пример 2: Путь от корня до узла

Построение полного пути от корневой категории до каждого узла:

```php
$qb = new QueryBuilder();

// Якорный запрос: корневые категории
$baseQuery = $qb->subQuery()
    ->select('id', 'name', 'parent_id', Field::set('name', '', 'path'))
    ->from('categories')
    ->where()->isNull('parent_id')->end();

// Рекурсивный запрос: добавляем путь
$joinCondition = ConditionJoin::create($qb, 'id', 'parent_id', 'c');

$recursiveQuery = $qb->subQuery()
    ->select(
        Field::set('id', 'c'),
        Field::set('name', 'c'),
        Field::set('parent_id', 'c'),
        Field::set("CONCAT(ct.path, ' > ', c.name)", '', 'path')
    )
    ->from('categories', 'c')
    ->innerJoin('category_tree', 'ct', $joinCondition);

// Финальный запрос
$finalQuery = $qb->subQuery()
    ->select('id', 'name', 'path')
    ->from('category_tree')
    ->orderBy(ConditionBy::orderBy()->asc('path'));

$cte = $qb->recursiveCte('category_tree');
$sql = $cte->baseQuery($baseQuery)
    ->recursiveQuery($recursiveQuery)
    ->finalSelect($finalQuery)
    ->build();

// Результат:
// Electronics
// Electronics > Computers
// Electronics > Computers > Laptops
// Electronics > Phones
// Home & Garden
// Home & Garden > Furniture
```

### Пример 3: Все предки узла

Получение всех родительских категорий для заданной категории:

```php
$categoryId = 15;

$qb = new QueryBuilder();

// Якорный запрос: начальная категория
$baseQuery = $qb->subQuery()
    ->select('id', 'name', 'parent_id', '0 AS level')
    ->from('categories')
    ->where()->eq('id', $categoryId)->end();

// Рекурсивный запрос: родительские категории
$joinCondition = ConditionJoin::create($qb, 'id', 'parent_id', 'a');

$recursiveQuery = $qb->subQuery()
    ->select(
        Field::set('id', 'c'),
        Field::set('name', 'c'),
        Field::set('parent_id', 'c'),
        '`a`.`level` + 1 AS level'
    )
    ->from('categories', 'c')
    ->innerJoin('ancestors', 'a', $joinCondition);

// Финальный запрос: сортировка от корня к листу
$finalQuery = $qb->subQuery()
    ->select('id', 'name', 'level')
    ->from('ancestors')
    ->orderBy(ConditionBy::orderBy()->desc('level'));

$cte = $qb->recursiveCte('ancestors');
$sql = $cte->baseQuery($baseQuery)
    ->recursiveQuery($recursiveQuery)
    ->finalSelect($finalQuery)
    ->build();
```

### Пример 4: Все потомки узла

Получение всех дочерних категорий для заданной категории:

```php
$categoryId = 5;

$qb = new QueryBuilder();

// Якорный запрос: начальная категория
$baseQuery = $qb->subQuery()
    ->select('id', 'name', 'parent_id', '0 AS depth')
    ->from('categories')
    ->where()->eq('id', $categoryId)->end();

// Рекурсивный запрос: дочерние категории
$joinCondition = ConditionJoin::create($qb, 'parent_id', 'id', 'd');

$recursiveQuery = $qb->subQuery()
    ->select(
        Field::set('id', 'c'),
        Field::set('name', 'c'),
        Field::set('parent_id', 'c'),
        '`d`.`depth` + 1 AS depth'
    )
    ->from('categories', 'c')
    ->innerJoin('descendants', 'd', $joinCondition);

// Финальный запрос
$finalQuery = $qb->subQuery()
    ->select('id', 'name', 'depth')
    ->from('descendants')
    ->orderBy(ConditionBy::orderBy()->asc('depth')->asc('name'));

$cte = $qb->recursiveCte('descendants');
$sql = $cte->baseQuery($baseQuery)
    ->recursiveQuery($recursiveQuery)
    ->finalSelect($finalQuery)
    ->build();
```

### Пример 5: Граф зависимостей (обнаружение циклов)

Построение графа зависимостей с защитой от циклов:

```php
$qb = new QueryBuilder();

// Якорный запрос: начальные узлы без зависимостей
$baseQuery = $qb->subQuery()
    ->select('id', 'name', 'depends_on', Field::set('CAST(id AS CHAR(1000))', '', 'path'))
    ->from('tasks')
    ->where()->isNull('depends_on')->end();

// Рекурсивный запрос: зависимые задачи
$joinCondition = ConditionJoin::create($qb, 'id', 'depends_on', 't');

$recursiveQuery = $qb->subQuery()
    ->select(
        Field::set('id', 't'),
        Field::set('name', 't'),
        Field::set('depends_on', 't'),
        Field::set("CONCAT(dg.path, ',', t.id)", '', 'path')
    )
    ->from('tasks', 't')
    ->innerJoin('dependency_graph', 'dg', $joinCondition)
    ->where()->raw("FIND_IN_SET(t.id, dg.path) = 0")->end(); // Защита от циклов

// Финальный запрос
$finalQuery = $qb->subQuery()
    ->select('id', 'name', 'path')
    ->from('dependency_graph')
    ->orderBy(ConditionBy::orderBy()->asc('id'));

$cte = $qb->recursiveCte('dependency_graph');
$sql = $cte->baseQuery($baseQuery)
    ->recursiveQuery($recursiveQuery)
    ->finalSelect($finalQuery)
    ->build();
```

### Методы RecursiveCteBuilder

#### `baseQuery(QueryBuilder $query): self`

Устанавливает якорный запрос (начальную точку рекурсии).

**Требования:**

- Должен быть SELECT запрос
- Определяет структуру столбцов для всего CTE

#### `recursiveQuery(QueryBuilder $query): self`

Устанавливает рекурсивный запрос, который ссылается на имя CTE.

**Требования:**

- Должен быть SELECT запрос
- Должен содержать JOIN с именем CTE
- Структура столбцов должна совпадать с baseQuery

#### `finalSelect(QueryBuilder $query): self`

Устанавливает финальный SELECT, который использует результат CTE.

**Требования:**

- Должен быть SELECT запрос
- Может содержать ORDER BY, LIMIT, GROUP BY и т.д.

#### `build(bool $compact = false): string`

Строит финальный SQL запрос.

**Параметры:**

- `$compact` - удалить лишние пробелы и переносы строк

**Возвращает:** готовый SQL запрос

### Поддержка замыканий

Для рекурсивных CTE замыкания не применяются, так как требуется явное определение трёх отдельных запросов.

### Ограничения и best practices

#### Ограничения

1. **Максимальная глубина рекурсии**
   - MySQL/MariaDB: по умолчанию 1000 (настраивается через `cte_max_recursion_depth`)
   - PostgreSQL: по умолчанию нет ограничений
   - MS SQL Server: по умолчанию 100 (настраивается через `MAXRECURSION`)

2. **Структура столбцов**
   - Все запросы (base, recursive, final) должны иметь совместимые типы столбцов

3. **Производительность**
   - Рекурсивные запросы могут быть медленными на больших деревьях
   - Рекомендуется добавлять условия ограничения глубины

#### Best Practices

1. **Защита от бесконечной рекурсии**

```php
// Добавьте ограничение глубины
->where()->lt('level', 10)->end()

// Или используйте FIND_IN_SET для обнаружения циклов
->where()->raw("FIND_IN_SET(id, path) = 0")->end()
```

1. **Индексы**

```sql
-- Для иерархических структур
CREATE INDEX idx_parent_id ON categories(parent_id);
CREATE INDEX idx_id_parent ON categories(id, parent_id);
```

1. **Оптимизация**

```php
// Используйте WHERE в baseQuery для ограничения начальной выборки
$baseQuery->where()->eq('status', 'active')->end();

// Добавляйте условия в recursiveQuery
$recursiveQuery->where()->eq('status', 'active')->end();
```

1. **Тестирование**

```php
// Всегда тестируйте на небольших данных
$finalQuery->limit(100);

// Проверяйте глубину рекурсии
$finalQuery->select('*', 'MAX(level) AS max_depth')->from('category_tree');
```

---

## Сложные примеры

### Пример 1: Отчет по заказам с подзапросами

```php
use Ep\App\QBuilder\Condition\Field;
use Ep\App\QBuilder\Condition\ConditionBy;

$qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);

$sql = $qb->select(
    Field::set('id', 'users'),
    Field::set('name', 'users'),
    Field::set('email', 'users'),
    Field::subquery(
        $qb->subQuery()
            ->select(Field::set('COUNT(*)'))
            ->from('orders')
            ->where()
                ->eqField('orders', 'user_id', 'users', 'id')
                ->and()->eq('status', 'completed')
                ->end(),
        'completed_orders'
    ),
    Field::subquery(
        $qb->subQuery()
            ->select(Field::set('SUM(total)'))
            ->from('orders')
            ->where()
                ->eqField('orders', 'user_id', 'users', 'id')
                ->end(),
        'total_spent'
    )
)
->from('users')
->where()
    ->eq('status', 'active')
    ->end()
->orderBy(ConditionBy::orderBy()->desc('total_spent'))
->limit(10)
->build();
```

### Пример 2: Сложный JOIN с GROUP_CONCAT

```php
use Ep\App\QBuilder\Condition\Field;
use Ep\App\QBuilder\Condition\ConditionBy;
use Ep\App\QBuilder\Condition\ConditionJoin;

$layerSubquery = $qb->subQuery()
    ->select(
        Field::set('perforation_id', 'well_perforation_layer'),
        'GROUP_CONCAT(`layer`.`name`) AS layer_names',
        'GROUP_CONCAT(`well_perforation_layer`.`layer_id`) AS layer_ids'
    )
    ->from('well_perforation_layer')
    ->leftJoin(
        'layer',
        '',
        ConditionJoin::create($qb, 'id', 'layer_id', 'well_perforation_layer')
    )
    ->groupBy(ConditionBy::groupBy()->add('perforation_id', 'well_perforation_layer'));

$sql = $qb->select(
    Field::set('*', 'perforations'),
    Field::set('name', 'wells', 'well_name'),
    Field::set('layer_names', 'layers'),
    Field::set('layer_ids', 'layers')
)
->from('perforations')
->leftJoin('wells', '', ConditionJoin::create($qb, 'id', 'well_id', 'perforations'))
->leftJoinFromSelect(
    $layerSubquery,
    'layers',
    ConditionJoin::create($qb, 'perforation_id', 'id', 'perforations')
)
->build();
```

### Пример 3: Вложенные подзапросы с расчетами

```php
use Ep\App\QBuilder\Condition\Field;
use Ep\App\QBuilder\Condition\ConditionJoin;

$innerSubquery = $qb->subQuery()
    ->select(
        Field::subquery(
            $qb->subQuery()
                ->select(Field::set('MAX(md)'))
                ->from('inclinometry')
                ->where()
                    ->eqField('inclinometry', 'well_id', 'p', 'well_id')
                    ->and()->ltField('inclinometry', 'md', 'p', 'top_md')
                    ->end(),
            'top_md_min'
        ),
        Field::subquery(
            $qb->subQuery()
                ->select(Field::set('MIN(md)'))
                ->from('inclinometry')
                ->where()
                    ->eqField('inclinometry', 'well_id', 'p', 'well_id')
                    ->and()->gteField('inclinometry', 'md', 'p', 'top_md')
                    ->end(),
            'top_md_max'
        ),
        Field::set('*', 'p')
    )
    ->from('wells_perforations', 'p');

$outerSubquery = $qb->subQuery()
    ->select(
        Field::set('*', 't'),
        Field::set('IF(top_md_max, top_tvdss_min + (top_md - top_md_min) * (top_tvdss_max - top_tvdss_min) / (top_md_max - top_md_min), (top_md_min + top_tvdss_min - top_md))', '', 'top_tvd_calc')
    )
    ->from($innerSubquery, 't');

$sql = $qb->select(
    Field::set('*', 'perforations'),
    Field::set('name', 'wells', 'well_name')
)
->from($outerSubquery, 'perforations')
->leftJoin('wells', '', ConditionJoin::create($qb, 'id', 'well_id', 'perforations'))
->build();
```

### Пример 4: UNION запросы

```php
use Ep\App\QBuilder\Builder\UnionBuilder;

$query1 = $qb->select('id', 'name', '"customer" AS type')
    ->from('customers')
    ->where()
        ->eq('status', 'active')
        ->end();

$query2 = $qb->subQuery()
    ->select('id', 'name', '"supplier" AS type')
    ->from('suppliers')
    ->where()
        ->eq('status', 'active')
        ->end();

$union = new UnionBuilder($qb);
$union->add($query1);
$union->add($query2);

$sql = $union->build();

// (SELECT `id`, `name`, "customer" AS type FROM `customers` WHERE (`status` = 'active'))
// UNION
// (SELECT `id`, `name`, "supplier" AS type FROM `suppliers` WHERE (`status` = 'active'))
```

---

## Безопасность

### Автоматическое экранирование

QueryBuilder **автоматически экранирует** все идентификаторы и значения:

```php
$qb->select('*')
    ->from('users')
    ->where()
        ->eq('name', "O'Brien")
        ->end();

// WHERE (`name` = 'O\'Brien')  - автоматически экранировано
```

### Защита от SQL-инъекций

**✅ ПРАВИЛЬНО** - использование QueryBuilder:

```php
$userInput = $_GET['status'];

$qb->where()
    ->eq('status', $userInput)
    ->end();

// Значение будет экранировано автоматически
```

**❌ НЕПРАВИЛЬНО** - НЕ используйте конкатенацию строк:

```php
$userInput = $_GET['status'];
$sql = "SELECT * FROM users WHERE status = '" . $userInput . "'";
```

### Валидация идентификаторов

QueryBuilder проверяет имена таблиц, полей и алиасов:

```php
$qb->from('users');          // ✅ OK
$qb->from('users; DROP--');  // ❌ Exception: Invalid table name
```

### Зарезервированные слова

Зарезервированные слова SQL автоматически экранируются:

```php
$qb->from('fields');  // ✅ OK - будет экранировано как `fields`
$qb->from('order');   // ✅ OK - будет экранировано как `order`
```

### Опасные функции

QueryBuilder **блокирует** опасные SQL-функции:

```php
$qb->from('LOAD_FILE');        // ❌ Exception
$qb->from('INFORMATION_SCHEMA'); // ❌ Exception
```

---

## Лучшие практики

### 1. Всегда используйте Field для сложных запросов

**✅ ПРАВИЛЬНО:**

```php
$qb->select(
    Field::set('id', 'users'),
    Field::set('name', 'users', 'user_name')
);
```

**❌ ИЗБЕГАЙТЕ:**

```php
$qb->select('users.id', 'users.name AS user_name');
```

### 2. Используйте алиасы для читаемости

**✅ ПРАВИЛЬНО:**

```php
$qb->select(
    Field::set('name', 'u', 'user_name'),
    Field::set('name', 'c', 'company_name')
)
->from('users', 'u')
->leftJoin('companies', 'c', ConditionJoin::create($qb, 'id', 'company_id', 'u'));
```

### 3. Группируйте сложные условия

**✅ ПРАВИЛЬНО:**

```php
$qb->where()
    ->eq('country', 'US')
    ->and()
    ->startGroup()
        ->eq('status', 'active')
        ->or()->eq('status', 'pending')
    ->endGroup()
    ->end();
```

### 4. Используйте подзапросы для сложной логики

**✅ ПРАВИЛЬНО:**

```php
$activeUsersSubquery = $qb->subQuery()
    ->select('user_id')
    ->from('orders')
    ->where()
        ->gte('created_at', '2024-01-01')
        ->end();

$qb->where()
    ->inSubquery('id', $activeUsersSubquery)
    ->end();
```

### 5. Всегда вызывайте end() для условий

**✅ ПРАВИЛЬНО:**

```php
$qb->where()
    ->eq('status', 'active')
    ->end();  // ← обязательно!
```

**❌ НЕПРАВИЛЬНО:**

```php
$qb->where()
    ->eq('status', 'active');  // ← забыли end()
```

---

## Отладка

### Получение SQL без выполнения

```php
$sql = $qb->select('*')
    ->from('users')
    ->build();

echo $sql;  // Вывод SQL для проверки
```

### Компактный SQL

```php
$sql = $qb->build(true);  // Убирает лишние пробелы и переносы строк
```

### Проверка типа запроса

```php
$type = $qb->getType();  // 'SELECT', 'INSERT', 'UPDATE', 'DELETE'
```

---

## Поддержка драйверов

### Особенности MySQL/MariaDB

- `LIMIT offset, count` синтаксис
- `ON DUPLICATE KEY UPDATE`
- `FIND_IN_SET()`
- Битовые операции

### Особенности PostgreSQL

- `LIMIT count OFFSET offset` синтаксис
- `ON CONFLICT DO UPDATE`
- `FETCH FIRST n ROWS WITH TIES`

### Особенности ClickHouse

- `LIMIT n BY expressions`
- `FINAL` модификатор - принудительное слияние данных для получения актуальных данных
- `WITH TIES`

**Пример использования FINAL:**

```php
$qb->select('*')
    ->from('sensor_data')
    ->final()  // Добавляет FINAL для ClickHouse
    ->where()
        ->gte('timestamp', '2024-01-01')
        ->end()
    ->build();

// ClickHouse: SELECT * FROM `sensor_data` FINAL WHERE (`timestamp` >= '2024-01-01')
// Другие СУБД: SELECT * FROM `sensor_data` WHERE (`timestamp` >= '2024-01-01')
```

### Особенности MS SQL Server

- `TOP n WITH TIES`
- `MERGE` вместо `UPSERT`
- `FETCH NEXT n ROWS ONLY`

### Особенности Oracle

- `FETCH FIRST n ROWS WITH TIES`
- `MERGE` для upsert
- Специфичное экранирование

### Особенности SQLite

- Ограниченная поддержка `ALTER TABLE`
- `ON CONFLICT` для upsert
- Простой синтаксис `LIMIT`

---

## Альтернативный синтаксис с замыканиями

### Преимущества

QueryBuilder поддерживает альтернативный синтаксис с использованием замыканий (closures), который предоставляет следующие преимущества:

1. **Не нужно вызывать `end()`** - замыкание автоматически завершает построение условий
2. **Более компактный код** - меньше строк кода для тех же условий
3. **Улучшенная читаемость** - вложенные группы становятся более понятными
4. **Полная совместимость** - оба синтаксиса генерируют идентичный SQL

### Сравнение синтаксисов

#### Простое условие

```php
// Стандартный синтаксис
$qb->where()->eq('status', 'active')->end();

// Альтернативный синтаксис
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder => $q->eq('status', 'active'));
```

#### Несколько условий

```php
// Стандартный синтаксис
$qb->where()
    ->eq('status', 'active')
    ->and()->gt('age', 18)
    ->and()->like('name', 'John%')
    ->end();

// Альтернативный синтаксис
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->eq('status', 'active')
      ->and()->gt('age', 18)
      ->and()->like('name', 'John%')
);
```

#### Группы условий

```php
// Стандартный синтаксис
$qb->where()
    ->eq('country', 'US')
    ->andGroup()
        ->eq('status', 'active')
        ->or()->eq('status', 'pending')
    ->end()
    ->end();

// Альтернативный синтаксис
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->eq('country', 'US')
      ->andGroup(static fn (ConditionBuilder $q2): ConditionBuilder => $q2->eq('status', 'active')->or()->eq('status', 'pending'))
);
```

#### Вложенные группы

```php
// Стандартный синтаксис
$qb->where()
    ->eq('status', 'active')
    ->andGroup()
        ->eq('country', 'US')
        ->orGroup()
            ->eq('state', 'CA')
            ->or()->eq('state', 'NY')
        ->end()
    ->end()
    ->end();

// Альтернативный синтаксис
$qb->where(function($q) {
    $q->eq('status', 'active')
      ->andGroup(function($q2) {
          $q2->eq('country', 'US')
             ->orGroup(fn ($q3) => $q3->eq('state', 'CA')->or()->eq('state', 'NY'));
      });
});
```

### Использование с HAVING

Альтернативный синтаксис также работает с `having()`:

```php
// Стандартный синтаксис
$qb->having()
    ->gt('COUNT(*)', 5)
    ->and()->lt('SUM(amount)', 10000)
    ->end();

// Альтернативный синтаксис
$qb->having(static fn (ConditionBuilder $q): ConditionBuilder => $q->gt('COUNT(*)', 5)->and()->lt('SUM(amount)', 10000));
```

### Сложный пример

```php
// Альтернативный синтаксис для сложного запроса
$qb->select('*')
    ->from('users')
    ->where(function($q) {
        $q->eq('status', 'active')
          ->and()->bitmask('permissions', [1 => 1, 2 => 1])
          ->and()->in('role', ['admin', 'moderator'])
          ->andGroup(function($q2) {
              $q2->gte('created_at', '2024-01-01')
                 ->or()->isNull('deleted_at');
          });
    })
    ->orderBy(ConditionBy::orderBy()->desc('created_at'))
    ->limit(10);
```

### Когда использовать каждый синтаксис

**Стандартный синтаксис рекомендуется:**

- При динамическом построении условий в циклах
- Когда условия добавляются условно (if/else)
- В легаси-коде для совместимости

**Альтернативный синтаксис рекомендуется:**

- Для статических запросов с известной структурой
- Когда нужна максимальная читаемость
- Для сложных вложенных условий
- В новом коде

---

## Заключение

QueryBuilder - это мощный инструмент для безопасного и удобного построения SQL-запросов. Следуйте этому руководству, и вы сможете создавать даже самые сложные запросы без риска SQL-инъекций и с поддержкой множества СУБД.

**Помните:**

- ✅ Всегда используйте QueryBuilder вместо конкатенации строк
- ✅ Используйте `Field::set()` для полей с таблицами и алиасами
- ✅ Выбирайте синтаксис (стандартный или с замыканиями) в зависимости от задачи
- ✅ Используйте подзапросы для сложной логики
- ⚠️ Используйте `raw()` методы **только с проверенными данными** - они обходят защиту от SQL-инъекций!

Удачи в разработке! 🚀

---

## Лицензия

Этот проект распространяется под лицензией [MIT](LICENSE).

## Установка

```bash
composer require qbuilder/qbuilder
```

## Требования

- PHP >= 8.4
- PDO extension (для работы с базами данных)
