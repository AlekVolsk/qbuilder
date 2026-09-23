# Closure Syntax

## Advantages

QueryBuilder supports an alternative syntax using closures, which provides the following advantages:

1. **No need to call `end()`** - the closure automatically completes building the conditions
2. **More compact code** - fewer lines of code for the same conditions
3. **Improved readability** - nested groups become clearer
4. **Full compatibility** - both syntaxes generate identical SQL

## Comparing the Syntaxes

### Simple Condition

```php
// Standard syntax
$qb->where()->eq('status', 'active')->end();

// Alternative syntax
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder => $q->eq('status', 'active'));
```

### Multiple Conditions

```php
// Standard syntax
$qb->where()
    ->eq('status', 'active')
    ->and()->gt('age', 18)
    ->and()->like('name', 'John', QbConsts::LIKE_RIGHT)
    ->end();

// Alternative syntax
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->eq('status', 'active')
      ->and()->gt('age', 18)
      ->and()->like('name', 'John', QbConsts::LIKE_RIGHT)
);
```

### Condition Groups

```php
// Standard syntax
$qb->where()
    ->eq('country', 'US')
    ->andGroup()
        ->eq('status', 'active')
        ->or()->eq('status', 'pending')
    ->end()
    ->end();

// Alternative syntax
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->eq('country', 'US')
      ->andGroup(static fn (ConditionBuilder $q2): ConditionBuilder => $q2->eq('status', 'active')->or()->eq('status', 'pending'))
);
```

### Nested Groups

```php
// Standard syntax
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

// Alternative syntax
$qb->where(function($q) {
    $q->eq('status', 'active')
      ->andGroup(function($q2) {
          $q2->eq('country', 'US')
             ->orGroup(fn ($q3) => $q3->eq('state', 'CA')->or()->eq('state', 'NY'));
      });
});
```

## Using with HAVING

The alternative syntax also works with `having()`:

```php
// Standard syntax
$qb->having()
    ->gt('COUNT(*)', 5)
    ->and()->lt('SUM(amount)', 10000)
    ->end();

// Alternative syntax
$qb->having(static fn (ConditionBuilder $q): ConditionBuilder => $q->gt('COUNT(*)', 5)->and()->lt('SUM(amount)', 10000));
```

## Complex Example

```php
// Alternative syntax for a complex query
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

## When to Use Each Syntax

**The standard syntax is recommended:**

- When building conditions dynamically in loops
- When conditions are added conditionally (if/else)
- In legacy code for compatibility

**The alternative syntax is recommended:**

- For static queries with a known structure
- When maximum readability is needed
- For complex nested conditions
- In new code

---

[← WHERE Conditions](03-where.md) · [Contents](index.md) · [JOIN Operations →](05-join.md)
