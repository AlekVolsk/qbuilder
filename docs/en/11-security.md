# Security

## Automatic Escaping

QueryBuilder **automatically escapes** all identifiers and values:

```php
$qb->select('*')
    ->from('users')
    ->where()
        ->eq('name', "O'Brien")
        ->end();

// WHERE (`name` = 'O''Brien')  - automatically escaped
```

The quote is escaped by doubling (`''`), so the literal cannot be closed early in any server mode. The backslash is doubled (`\\`) because it is an escape character in the default MySQL mode. Control characters (`\n`, `\r`, `\0`) are passed as is — they are valid inside a literal.

**MySQL and `NO_BACKSLASH_ESCAPES`.** The builder does not know the server `sql_mode`. In this mode the backslash is an ordinary character, so a doubled `\\` is stored as two backslashes. There is no injection (verified on MySQL 8.0 in both modes), but values containing backslashes get altered — do not enable this mode for connections that run builder SQL.

**PostgreSQL.** A value with a backslash is emitted as `E'...'`: in such a string the backslash is an escape character regardless of `standard_conforming_strings`, so `C:\dir` is stored intact both with `on` (the default) and with the legacy `off`. Values without a backslash stay plain `'...'`.

## Value Formatting by Type

The literal form is decided by the PHP type of the value, not by its content. The same rule applies to conditions, INSERT/UPDATE, upsert/merge and procedure parameters:

| PHP type | Literal | Example |
|---|---|---|
| `int`, `float` | unquoted | `42`, `1.5` |
| `string` | always quoted, even if it looks like a number | `'0123456789'`, `'1e3'` |
| `bool` | driver literal | `1` / `0`; PostgreSQL — `TRUE` / `FALSE` |
| `null` | `NULL` | `NULL` |

A string with leading zeros (tax IDs, codes, SKUs) is kept intact. A number that arrives as a string (e.g. from `$_GET`) is quoted — databases compare an `int` column with `'5'` correctly. To pass a number, cast it: `(int) $_GET['id']`.

`INF` and `NAN` have no SQL literal — `formatValue()` throws `InvalidQueryException`.

```php
$qb->insert('org')
    ->insertRow(['inn' => '0123456789', 'qty' => 5, 'active' => false])
    ->build(true);

// INSERT INTO `org` (`inn`, `qty`, `active`) VALUES ('0123456789', 5, 0)
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
