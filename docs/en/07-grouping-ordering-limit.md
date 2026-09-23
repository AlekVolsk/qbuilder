# 07 Grouping, Ordering and LIMIT

## GROUP BY and HAVING

### Simple GROUP BY

```php
use QBuilder\Condition\ConditionBy;

$qb->select('status', 'COUNT(*) AS total')
    ->from('orders')
    ->groupBy(ConditionBy::groupBy()->add('status'));

// SELECT `status`, COUNT(*) AS total FROM `orders` GROUP BY `status`
```

### GROUP BY with Multiple Fields

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

### GROUP BY with a Table

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

### HAVING with Multiple Conditions

```php
$qb->having()
    ->gt('COUNT(*)', 5)
    ->and()->lt('SUM(total)', 10000)
    ->end();

// HAVING (COUNT(*) > 5) AND (SUM(total) < 10000)
```

## ORDER BY and LIMIT

### ORDER BY

#### Ascending (ASC)

```php
$qb->orderBy(ConditionBy::orderBy()->asc('name'));

// ORDER BY `name` ASC
```

#### Descending (DESC)

```php
$qb->orderBy(ConditionBy::orderBy()->desc('created_at'));

// ORDER BY `created_at` DESC
```

#### Multiple Fields

```php
$qb->orderBy(
    ConditionBy::orderBy()
        ->asc('country')
        ->desc('created_at')
        ->asc('name')
);

// ORDER BY `country` ASC, `created_at` DESC, `name` ASC
```

#### With a Table Specified

```php
$qb->orderBy(
    ConditionBy::orderBy()
        ->asc('status', 'orders')
        ->desc('created_at', 'orders')
);

// ORDER BY `orders`.`status` ASC, `orders`.`created_at` DESC
```

### LIMIT

#### Count Only

```php
$qb->limit(10);

// LIMIT 10
```

#### With Offset (MySQL)

```php
$qb->limit(10, 20);

// LIMIT 20, 10  (skip 20, take 10)
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

[← 06 Subqueries](06-subqueries.md) · [Contents](index.md) · [08 INSERT, UPDATE, DELETE →](08-insert-update-delete.md)
