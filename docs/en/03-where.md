# WHERE Conditions

QueryBuilder supports two syntaxes for building WHERE conditions:

1. **Standard syntax** - the classic approach with an explicit `end()` call
2. **Alternative closure syntax** - a modern approach that doesn't require calling `end()`

Both syntaxes are fully equivalent and generate identical SQL.

## Basic Operators

### Equality (=)

**Standard syntax:**

```php
$qb->select('*')
    ->from('users')
    ->where()
        ->eq('status', 'active')
        ->end();

// WHERE (`status` = 'active')
```

**Alternative closure syntax:**

```php
$qb->select('*')
    ->from('users')
    ->where(static fn (ConditionBuilder $q): ConditionBuilder => $q->eq('status', 'active'));

// WHERE (`status` = 'active')
```

### Inequality (!=)

**Standard syntax:**

```php
$qb->where()
    ->neq('status', 'deleted')
    ->end();

// WHERE (`status` != 'deleted')
```

**Alternative syntax:**

```php
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder => $q->neq('status', 'deleted'));

// WHERE (`status` != 'deleted')
```

### Greater Than / Less Than

**Standard syntax:**

```php
$qb->where()
    ->gt('age', 18)
    ->and()->lt('age', 65)
    ->end();

// WHERE (`age` > 18) AND (`age` < 65)
```

**Alternative syntax:**

```php
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->gt('age', 18)->and()->lt('age', 65)
);

// WHERE (`age` > 18) AND (`age` < 65)
```

### Greater Than or Equal / Less Than or Equal

**Standard syntax:**

```php
$qb->where()
    ->gte('price', 100)
    ->and()->lte('price', 1000)
    ->end();

// WHERE (`price` >= 100) AND (`price` <= 1000)
```

**Alternative syntax:**

```php
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->gte('price', 100)->and()->lte('price', 1000)
);

// WHERE (`price` >= 100) AND (`price` <= 1000)
```

## Logical Operators

### AND

**Standard syntax:**

```php
$qb->where()
    ->eq('status', 'active')
    ->and()->eq('verified', 1)
    ->and()->gt('balance', 0)
    ->end();

// WHERE (`status` = 'active') AND (`verified` = 1) AND (`balance` > 0)
```

**Alternative syntax:**

```php
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->eq('status', 'active')
      ->and()->eq('verified', 1)
      ->and()->gt('balance', 0)
);

// WHERE (`status` = 'active') AND (`verified` = 1) AND (`balance` > 0)
```

### OR

**Standard syntax:**

```php
$qb->where()
    ->eq('status', 'active')
    ->or()->eq('status', 'pending')
    ->end();

// WHERE (`status` = 'active') OR (`status` = 'pending')
```

**Alternative syntax:**

```php
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->eq('status', 'active')->or()->eq('status', 'pending')
);

// WHERE (`status` = 'active') OR (`status` = 'pending')
```

### Combining AND and OR

**Standard syntax:**

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

**Standard syntax (using andGroup/orGroup):**

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

**Alternative closure syntax:**

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

## Special Operators

### IN

**Standard syntax:**

```php
$qb->where()
    ->in('status', ['active', 'pending', 'verified'])
    ->end();

// WHERE (`status` IN ('active', 'pending', 'verified'))
```

**Alternative syntax:**

```php
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->in('status', ['active', 'pending', 'verified'])
);

// WHERE (`status` IN ('active', 'pending', 'verified'))
```

### NOT IN

**Standard syntax:**

```php
$qb->where()
    ->notIn('status', ['deleted', 'banned'])
    ->end();

// WHERE (`status` NOT IN ('deleted', 'banned'))
```

**Alternative syntax:**

```php
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->notIn('status', ['deleted', 'banned'])
);

// WHERE (`status` NOT IN ('deleted', 'banned'))
```

### IN / NOT IN Value List

The rules are the same for WHERE, HAVING and JOIN:

- array elements are taken as is — an empty string `''` and `null` are kept in the list (`IN ('', NULL)`); each element is formatted by its type as described in [Security](11-security.md#value-formatting-by-type);
- a string is split by commas and empty elements are dropped: `in('status', 'active,,pending')` → `IN ('active', 'pending')`;
- an empty list gives `1 = 0` for `in()` and `1 = 1` for `notIn()`.

Per the SQL standard, `NOT IN` with `NULL` in the list returns no rows, but databases differ — the builder passes `NULL` through unchanged and leaves the decision to the calling code.

```php
$qb->where()
    ->in('code', ['0012', 7, '', null])
    ->end();

// WHERE (`code` IN ('0012', 7, '', NULL))
```

### BETWEEN

**Standard syntax:**

```php
$qb->where()
    ->between('age', 18, 65)
    ->end();

// WHERE (`age` BETWEEN 18 AND 65)
```

**Alternative syntax:**

```php
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->between('age', 18, 65)
);

// WHERE (`age` BETWEEN 18 AND 65)
```

### NOT BETWEEN

**Standard syntax:**

```php
$qb->where()
    ->notBetween('age', 18, 65)
    ->end();

// WHERE (`age` NOT BETWEEN 18 AND 65)
```

**Alternative syntax:**

```php
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->notBetween('age', 18, 65)
);

// WHERE (`age` NOT BETWEEN 18 AND 65)
```

### LIKE

**Standard syntax:**

```php
$qb->where()
    ->like('name', 'John')
    ->end();

// WHERE (`name` LIKE '%John%')
```

**Alternative syntax:**

```php
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->like('name', 'John')
);

// WHERE (`name` LIKE '%John%')
```

**LIKE with boundary types:**

**Standard syntax:**

```php
use QBuilder\QbConsts;

// Contains (default)
$qb->where()->like('name', 'John', QbConsts::LIKE_FULL)->end();
// WHERE (`name` LIKE '%John%')

// Starts with
$qb->where()->like('name', 'John', QbConsts::LIKE_RIGHT)->end();
// WHERE (`name` LIKE 'John%')

// Ends with
$qb->where()->like('name', 'John', QbConsts::LIKE_LEFT)->end();
// WHERE (`name` LIKE '%John')
```

**Alternative syntax:**

```php
use QBuilder\QbConsts;

// Contains (default)
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->like('name', 'John', QbConsts::LIKE_FULL)
);
// WHERE (`name` LIKE '%John%')

// Starts with
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->like('name', 'John', QbConsts::LIKE_RIGHT)
);
// WHERE (`name` LIKE 'John%')

// Ends with
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->like('name', 'John', QbConsts::LIKE_LEFT)
);
// WHERE (`name` LIKE '%John')
```

**The value is a literal search string.** `%` and `_` in it are escaped; wildcards are added only by the boundary type. MySQL, PostgreSQL, SQLite and Oracle escape with `!` and an explicit `ESCAPE '!'`, so the result does not depend on `sql_mode` or `standard_conforming_strings`; MS SQL uses `[...]`, ClickHouse uses `\`. `ESCAPE` is appended only when the value contained something to escape.

```php
$qb->where()->like('discount', '50%', QbConsts::LIKE_RIGHT)->end();
// WHERE (`discount` LIKE '50!%%' ESCAPE '!')   — starts with "50%"

$qb->where()->like('code', 'a_b')->end();
// WHERE (`code` LIKE '%a!_b%' ESCAPE '!')       — contains "a_b" but not "axb"
```

### NOT LIKE

**Standard syntax:**

```php
$qb->where()
    ->notLike('name', 'Admin')
    ->end();

// WHERE (`name` NOT LIKE '%Admin%')
```

**Alternative syntax:**

```php
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->notLike('name', 'Admin')
);

// WHERE (`name` NOT LIKE '%Admin%')
```

### IS NULL / IS NOT NULL

**Standard syntax:**

```php
$qb->where()
    ->isNull('deleted_at')
    ->and()->isNotNull('email')
    ->end();

// WHERE (`deleted_at` IS NULL) AND (`email` IS NOT NULL)
```

**Alternative syntax:**

```php
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->isNull('deleted_at')->and()->isNotNull('email')
);

// WHERE (`deleted_at` IS NULL) AND (`email` IS NOT NULL)
```

## Comparing Fields

### Comparing Fields from Different Tables

**Standard syntax:**

```php
$qb->where()
    ->eqField('orders', 'user_id', 'users', 'id')
    ->end();

// WHERE (`orders`.`user_id` = `users`.`id`)
```

**Alternative syntax:**

```php
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->eqField('orders', 'user_id', 'users', 'id')
);

// WHERE (`orders`.`user_id` = `users`.`id`)
```

### Other Field Comparison Operators

**Standard syntax:**

```php
$qb->where()
    ->gtField('orders', 'total', 'users', 'credit_limit')
    ->and()->neqField('orders', 'status', 'users', 'default_status')
    ->end();

// WHERE (`orders`.`total` > `users`.`credit_limit`) AND (`orders`.`status` != `users`.`default_status`)
```

**Alternative syntax:**

```php
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->gtField('orders', 'total', 'users', 'credit_limit')
      ->and()
      ->neqField('orders', 'status', 'users', 'default_status')
);

// WHERE (`orders`.`total` > `users`.`credit_limit`) AND (`orders`.`status` != `users`.`default_status`)
```

## Bitwise Operations (MySQL)

### Checking a Bitmask

**Standard syntax:**

```php
$qb->where()
    ->bitmask('permissions', [1 => 1, 2 => 1, 4 => 0])
    ->end();

// WHERE ((`permissions` & 1 = 1)) AND ((`permissions` & 2 = 2)) AND ((`permissions` & 4 = 0))
```

**Alternative syntax:**

```php
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder => $q->bitmask('permissions', [1 => 1, 2 => 1, 4 => 0]));

// WHERE ((`permissions` & 1 = 1)) AND ((`permissions` & 2 = 2)) AND ((`permissions` & 4 = 0))
```

### Negating a Bitmask

**Standard syntax:**

```php
$qb->where()
    ->notBitmask('permissions', [8 => 1])
    ->end();

// WHERE NOT ((`permissions` & 8 = 8))
```

**Alternative syntax:**

```php
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->notBitmask('permissions', [8 => 1])
);

// WHERE NOT ((`permissions` & 8 = 8))
```

## FIND_IN_SET (MySQL)

**Standard syntax:**

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

**Alternative syntax:**

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

## Raw SQL Conditions (raw)

**⚠️ WARNING:** Using `raw()` bypasses SQL injection protection. Use only with trusted data!

**Standard syntax:**

```php
$qb->where()
    ->eq('status', 'active')
    ->and()
    ->raw('YEAR(created_at) = 2024')
    ->end();

// WHERE (`status` = 'active') AND (YEAR(created_at) = 2024)
```

**Alternative syntax:**

```php
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->eq('status', 'active')->and()->raw('YEAR(created_at) = 2024')
);

// WHERE (`status` = 'active') AND (YEAR(created_at) = 2024)
```

**When to use `raw()`:**

- ✅ Complex SQL expressions not supported by the standard methods
- ✅ DBMS-specific functions
- ❌ **NEVER** use it with user input without validation

---

[← SELECT Queries](02-select.md) · [Contents](index.md) · [Closure Syntax →](04-closure-syntax.md)
