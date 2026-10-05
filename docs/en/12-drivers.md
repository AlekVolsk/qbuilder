# Driver Support

## MySQL/MariaDB Specifics

- `LIMIT offset, count` syntax
- `ON DUPLICATE KEY UPDATE`
- `FIND_IN_SET()`
- Bitwise operations
- Index hints `USE` / `FORCE` / `IGNORE INDEX`, see [SELECT](02-select.md#index-hints)
- String escaping by doubling the quote (`''`), see [Security](11-security.md)
- `excluded()` in upsert: `VALUES(col)`, or the `AS new` row alias for MySQL 8.0.19+ when the server version is set, see [INSERT, UPDATE, DELETE](08-insert-update-delete.md)
- No `FULL JOIN`: `fullJoin()` and `fullJoinFromSelect()` throw `UnsupportedFeatureException`

## PostgreSQL Specifics

- `LIMIT count OFFSET offset` syntax
- `ON CONFLICT DO UPDATE`
- `FETCH FIRST n ROWS WITH TIES`
- bool as `TRUE` / `FALSE` — fits only `boolean` columns, see [Security](11-security.md#value-formatting-by-type)
- `increment()` / `decrement()` in upsert reference the current value through the INSERT table (`"t"."n" + 1`): an unqualified column is ambiguous with `EXCLUDED`, so the conflict handler needs `insert()` first
- Strings with a NUL byte are rejected
- Values with a backslash as `E'...'` — correct with any `standard_conforming_strings`

## ClickHouse Specifics

- `LIMIT n BY expressions`
- `FINAL` modifier - forces a data merge to get up-to-date data
- `WITH TIES`
- LIKE is escaped with backslash, without `ESCAPE`
- UNION without ALL is emitted as `UNION DISTINCT`; `ORDER BY` / `LIMIT` of a UNION wrap it into `SELECT * FROM (...)`

**Example of using FINAL:**

```php
$qb->select('*')
    ->from('sensor_data')
    ->final()  // Adds FINAL for ClickHouse
    ->where()
        ->gte('timestamp', '2024-01-01')
        ->end()
    ->build();

// ClickHouse: SELECT * FROM `sensor_data` FINAL WHERE (`timestamp` >= '2024-01-01')
// Other DBMS: SELECT * FROM `sensor_data` WHERE (`timestamp` >= '2024-01-01')
```

## MS SQL Server Specifics

- `TOP n WITH TIES`
- `MERGE` instead of `UPSERT`
- `forceIndex()` → `WITH (INDEX(...))`, see [SELECT](02-select.md#index-hints)
- `FETCH NEXT n ROWS ONLY`; a UNION limited without `orderBy()` gets `ORDER BY (SELECT NULL)`

## Oracle Specifics

- `FETCH FIRST n ROWS WITH TIES`
- `MERGE` for upsert
- Specific escaping

## SQLite Specifics

- Limited `ALTER TABLE` support
- `ON CONFLICT` for upsert
- Simple `LIMIT` syntax

---

[← Security](11-security.md) · [Contents](index.md) · [Best Practices and Debugging →](13-best-practices-debugging.md)
