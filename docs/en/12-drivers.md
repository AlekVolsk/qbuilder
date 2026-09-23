# Driver Support

## MySQL/MariaDB Specifics

- `LIMIT offset, count` syntax
- `ON DUPLICATE KEY UPDATE`
- `FIND_IN_SET()`
- Bitwise operations

## PostgreSQL Specifics

- `LIMIT count OFFSET offset` syntax
- `ON CONFLICT DO UPDATE`
- `FETCH FIRST n ROWS WITH TIES`

## ClickHouse Specifics

- `LIMIT n BY expressions`
- `FINAL` modifier - forces a data merge to get up-to-date data
- `WITH TIES`

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
- `FETCH NEXT n ROWS ONLY`

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
