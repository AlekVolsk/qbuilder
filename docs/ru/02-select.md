# SELECT запросы

## Выбор полей

### Простые поля

```php
$qb->select('id', 'name', 'email')
    ->from('users');

// SELECT `id`, `name`, `email` FROM `users`
```

### Все поля

```php
$qb->select('*')
    ->from('users');

// SELECT * FROM `users`
```

### Поля с таблицами

```php
use QBuilder\Condition\Field;

$qb->select(
    Field::set('id', 'users'),
    Field::set('name', 'users'),
    Field::set('email', 'users')
)->from('users');

// SELECT `users`.`id`, `users`.`name`, `users`.`email` FROM `users`
```

### Поля с алиасами

```php
$qb->select(
    Field::set('id', 'users'),
    Field::set('name', 'users', 'user_name'),
    Field::set('email', 'users', 'user_email')
)->from('users');

// SELECT `users`.`id`, `users`.`name` AS `user_name`, `users`.`email` AS `user_email` FROM `users`
```

### Все поля таблицы

```php
$qb->select(
    Field::set('id', 'users'),
    Field::set('*', 'orders')
)->from('users')
->leftJoin('orders', '', ConditionJoin::create($qb, 'user_id', 'id', 'users'));

// SELECT `users`.`id`, `orders`.* FROM `users` LEFT JOIN `orders` ON (...)
```

### SQL функции

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

### DATE_FORMAT и другие функции

```php
$qb->select(
    Field::set('id', 'orders'),
    'DATE_FORMAT(`orders`.`created_at`, "%d-%m-%Y") AS created_date',
    'CONCAT(`users`.`first_name`, " ", `users`.`last_name`) AS full_name'
)->from('orders');
```

### Числовой литерал

Целое число без знака выбирается как литерал, без кавычек — например, для `EXISTS (SELECT 1 ...)`:

```php
$qb->select('1')->from('orders');
// SELECT 1 FROM `orders`

$qb->select(Field::set('0', '', 'zero'));
// SELECT 0 AS `zero`
```

### Подсказки индексов

`useIndex()`, `forceIndex()`, `ignoreIndex()` ставятся в цепочке сразу после таблицы, к которой относятся: после `from()` или после join. Каждый метод принимает имя индекса или массив имён и необязательную область действия — `QbConsts::INDEX_FOR_JOIN`, `INDEX_FOR_ORDER_BY`, `INDEX_FOR_GROUP_BY`.

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

| СУБД | Поддержка |
|---|---|
| MySQL / MariaDB | все три хинта и области действия; `useIndex([])` → `USE INDEX ()` — не использовать индексы |
| MS SQL Server | только `forceIndex()` без области → `WITH (INDEX([ix_a], [ix_b]))`; `useIndex()`, `ignoreIndex()` и области бросают `UnsupportedFeatureException` — табличный хинт INDEX в MS SQL всегда принудительный |
| PostgreSQL, SQLite, Oracle, ClickHouse | хинты игнорируются: они не меняют результат запроса, поэтому один и тот же код работает на всех СУБД |

Ограничения: хинты допустимы только в SELECT и только для таблицы (не для подзапроса); `useIndex()` и `forceIndex()` нельзя сочетать для одной таблицы; вызов до `from()` бросает `InvalidQueryException`.

## DISTINCT

```php
$qb->select('country')
    ->distinct()
    ->from('users');

// SELECT DISTINCT `country` FROM `users`
```

---

[← Введение и быстрый старт](01-getting-started.md) · [Содержание](index.md) · [WHERE условия →](03-where.md)
