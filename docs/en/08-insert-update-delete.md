# 08 INSERT, UPDATE, DELETE

## INSERT Queries

An INSERT needs at least one field (`insertRow()` with data or `insertFrom()`), an UPDATE needs at least one assignment (`updateRow()`); otherwise `build()` throws `MissingRequirementException` instead of producing an incomplete statement.

### Inserting a Single Row

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

### Inserting Multiple Rows

```php
$qb->insert('users')
    ->insertRow(['name' => 'John', 'age' => 30])
    ->insertRow(['name' => 'Jane', 'age' => 25])
    ->insertRow(['name' => 'Bob', 'age' => 35])
    ->build();

// INSERT INTO `users` (`name`, `age`)
// VALUES ('John', 30), ('Jane', 25), ('Bob', 35)
```

### INSERT with ON DUPLICATE KEY UPDATE (MySQL)

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

The conflict handler is built when it is passed to `insertConflictHandler()`, so call `insertRow()` first: MS SQL Server and Oracle build `MERGE` from the rows added so far and throw `MissingRequirementException` without rows or without `conflictTarget()`; PostgreSQL references the INSERT table in `increment()` / `decrement()`.

**Referencing the inserted value — `excluded()`.** The syntax depends on the server: MySQL 8.0.20+ deprecates the `VALUES(col)` function (warning 1287) in favor of the `AS new` row alias (MySQL 8.0.19+), which MariaDB does not have. The builder opens no connection, so the calling code passes the server version:

```php
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;

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

// MariaDB, MySQL before 8.0.19, or version not set:
// INSERT INTO `users` (`email`, `name`)
// VALUES ('john@example.com', 'John')
// ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)
```

Without a version `VALUES()` is used — it works on every MySQL and MariaDB version. Subqueries (`subQuery()`) inherit the version.

### INSERT from a Subquery

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

## UPDATE Queries

### Simple UPDATE

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

### UPDATE with Multiple Conditions

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

### UPDATE with LIMIT (MySQL)

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

### UPDATE from a Subquery

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

## DELETE Queries

### Simple DELETE

```php
$qb->delete('users')
    ->where()
        ->eq('id', 123)
        ->end()
    ->build();

// DELETE FROM `users` WHERE (`id` = 123)
```

### DELETE with Multiple Conditions

```php
$qb->delete('users')
    ->where()
        ->eq('status', 'deleted')
        ->and()->lt('created_at', '2020-01-01')
        ->end()
    ->build();

// DELETE FROM `users` WHERE (`status` = 'deleted') AND (`created_at` < '2020-01-01')
```

### DELETE with LIMIT (MySQL)

```php
$qb->delete('users')
    ->where()
        ->eq('status', 'spam')
        ->end()
    ->limit(1000)
    ->build();

// DELETE FROM `users` WHERE (`status` = 'spam') LIMIT 1000
```

### DELETE from a Subquery

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

[← 07 Grouping, Ordering and LIMIT](07-grouping-ordering-limit.md) · [Contents](index.md) · [09 Recursive Queries (CTE) →](09-recursive-cte.md)
