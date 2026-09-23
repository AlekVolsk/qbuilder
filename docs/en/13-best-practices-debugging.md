# Best Practices and Debugging

## Best Practices

### 1. Always Use Field for Complex Queries

**✅ CORRECT:**

```php
$qb->select(
    Field::set('id', 'users'),
    Field::set('name', 'users', 'user_name')
);
```

**❌ AVOID:**

```php
$qb->select('users.id', 'users.name AS user_name');
```

### 2. Use Aliases for Readability

**✅ CORRECT:**

```php
$qb->select(
    Field::set('name', 'u', 'user_name'),
    Field::set('name', 'c', 'company_name')
)
->from('users', 'u')
->leftJoin('companies', 'c', ConditionJoin::create($qb, 'id', 'company_id', 'u'));
```

### 3. Group Complex Conditions

**✅ CORRECT:**

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

### 4. Use Subqueries for Complex Logic

**✅ CORRECT:**

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

### 5. Always Call end() for Conditions

**✅ CORRECT:**

```php
$qb->where()
    ->eq('status', 'active')
    ->end();  // ← required!
```

**❌ INCORRECT:**

```php
$qb->where()
    ->eq('status', 'active');  // ← forgot end()
```

## Debugging

### Getting SQL Without Executing It

```php
$sql = $qb->select('*')
    ->from('users')
    ->build();

echo $sql;  // Output SQL for inspection
```

### Compact SQL

```php
$sql = $qb->build(true);  // Removes extra spaces and line breaks
```

### Checking the Query Type

```php
$type = $qb->getType();  // 'SELECT', 'INSERT', 'UPDATE', 'DELETE'
```

## Cheat Sheet

- ✅ Always use QueryBuilder instead of string concatenation
- ✅ Use `Field::set()` for fields with tables and aliases
- ✅ Choose the syntax (standard or closures) depending on the task
- ✅ Use subqueries for complex logic
- ⚠️ Use `raw()` methods **only with trusted data** - they bypass SQL injection protection!

---

[← Driver Support](12-drivers.md) · [Contents](index.md)
