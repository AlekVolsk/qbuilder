# Поддержка драйверов

## Особенности MySQL/MariaDB

- `LIMIT offset, count` синтаксис
- `ON DUPLICATE KEY UPDATE`
- `FIND_IN_SET()`
- Битовые операции

## Особенности PostgreSQL

- `LIMIT count OFFSET offset` синтаксис
- `ON CONFLICT DO UPDATE`
- `FETCH FIRST n ROWS WITH TIES`

## Особенности ClickHouse

- `LIMIT n BY expressions`
- `FINAL` модификатор - принудительное слияние данных для получения актуальных данных
- `WITH TIES`

**Пример использования FINAL:**

```php
$qb->select('*')
    ->from('sensor_data')
    ->final()  // Добавляет FINAL для ClickHouse
    ->where()
        ->gte('timestamp', '2024-01-01')
        ->end()
    ->build();

// ClickHouse: SELECT * FROM `sensor_data` FINAL WHERE (`timestamp` >= '2024-01-01')
// Другие СУБД: SELECT * FROM `sensor_data` WHERE (`timestamp` >= '2024-01-01')
```

## Особенности MS SQL Server

- `TOP n WITH TIES`
- `MERGE` вместо `UPSERT`
- `FETCH NEXT n ROWS ONLY`

## Особенности Oracle

- `FETCH FIRST n ROWS WITH TIES`
- `MERGE` для upsert
- Специфичное экранирование

## Особенности SQLite

- Ограниченная поддержка `ALTER TABLE`
- `ON CONFLICT` для upsert
- Простой синтаксис `LIMIT`

---

[← Безопасность](11-security.md) · [Содержание](index.md) · [Лучшие практики и отладка →](13-best-practices-debugging.md)
