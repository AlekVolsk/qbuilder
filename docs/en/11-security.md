# Security

## Automatic Escaping

QueryBuilder **automatically escapes** all identifiers and values:

```php
$qb->select('*')
    ->from('users')
    ->where()
        ->eq('name', "O'Brien")
        ->end();

// WHERE (`name` = 'O\'Brien')  - automatically escaped
```

## Protection Against SQL Injection

**✅ CORRECT** - using QueryBuilder:

```php
$userInput = $_GET['status'];

$qb->where()
    ->eq('status', $userInput)
    ->end();

// The value will be escaped automatically
```

**❌ INCORRECT** - do NOT use string concatenation:

```php
$userInput = $_GET['status'];
$sql = "SELECT * FROM users WHERE status = '" . $userInput . "'";
```

## Identifier Validation

QueryBuilder validates table names, field names, and aliases:

```php
$qb->from('users');          // ✅ OK
$qb->from('users; DROP--');  // ❌ Exception: Invalid table name
```

## Reserved Words

SQL reserved words are automatically escaped:

```php
$qb->from('fields');  // ✅ OK - will be escaped as `fields`
$qb->from('order');   // ✅ OK - will be escaped as `order`
```

## Dangerous Functions

QueryBuilder **blocks** dangerous SQL functions:

```php
$qb->from('LOAD_FILE');        // ❌ Exception
$qb->from('INFORMATION_SCHEMA'); // ❌ Exception
```

---

[← Advanced Examples](10-examples.md) · [Contents](index.md) · [Driver Support →](12-drivers.md)
