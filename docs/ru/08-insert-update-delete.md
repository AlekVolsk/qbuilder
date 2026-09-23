# INSERT, UPDATE, DELETE

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

**Ссылка на вставляемое значение — `excluded()`.** Синтаксис зависит от сервера: в MySQL 8.0.20+ функция `VALUES(col)` объявлена устаревшей (предупреждение 1287), её замена — алиас строки `AS new` (MySQL 8.0.19+), которого нет в MariaDB. Билдер соединения не открывает, поэтому версию сервера передаёт вызывающий код:

```php
$qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
$qb->setServerVersion($pdo->getAttribute(PDO::ATTR_SERVER_VERSION));

$qb->insert('users')
    ->insertRow(['email' => 'john@example.com', 'name' => 'John'])
    ->insertConflictHandler($qb->conflictBuilder()->excluded('name'))
    ->build();

// MySQL 8.0.19+:
// INSERT INTO `users` (`email`, `name`)
// VALUES ('john@example.com', 'John') AS `new`
// ON DUPLICATE KEY UPDATE `name` = `new`.`name`

// MariaDB, MySQL до 8.0.19 или версия не задана:
// INSERT INTO `users` (`email`, `name`)
// VALUES ('john@example.com', 'John')
// ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)
```

Без версии используется `VALUES()` — он работает на всех версиях MySQL и MariaDB. Версию наследуют подзапросы (`subQuery()`).

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

[← Группировка, сортировка и LIMIT](07-grouping-ordering-limit.md) · [Содержание](index.md) · [Рекурсивные запросы (CTE) →](09-recursive-cte.md)
