# Поддержка драйверов

## Особенности MySQL/MariaDB

- `LIMIT offset, count` синтаксис
- `ON DUPLICATE KEY UPDATE`
- `FIND_IN_SET()`
- Битовые операции
- Подсказки индексов `USE` / `FORCE` / `IGNORE INDEX`, см. [SELECT](02-select.md#подсказки-индексов)
- Экранирование строк удвоением кавычки (`''`), см. [Безопасность](11-security.md)
- `excluded()` в upsert: `VALUES(col)` или алиас `AS new` для MySQL 8.0.19+ при заданной версии сервера, см. [INSERT, UPDATE, DELETE](08-insert-update-delete.md)
- Нет `FULL JOIN`: `fullJoin()` и `fullJoinFromSelect()` бросают `UnsupportedFeatureException`

## Особенности PostgreSQL

- `LIMIT count OFFSET offset` синтаксис
- `ON CONFLICT DO UPDATE`
- `FETCH FIRST n ROWS WITH TIES`
- bool как `TRUE` / `FALSE` — подходит только колонкам `boolean`, см. [Безопасность](11-security.md#форматирование-значений-по-типу)
- `increment()` / `decrement()` в upsert ссылаются на текущее значение через таблицу INSERT (`"t"."n" + 1`): неуточнённая колонка неоднозначна с `EXCLUDED`, поэтому обработчик конфликта требует сначала `insert()`
- Строки с NUL-байтом отклоняются
- Значения с обратным слэшем как `E'...'` — корректно при любом `standard_conforming_strings`

## Особенности ClickHouse

- `LIMIT n BY expressions`
- `FINAL` модификатор - принудительное слияние данных для получения актуальных данных
- `WITH TIES`
- LIKE экранируется обратным слэшем, без `ESCAPE`
- UNION без ALL выводится как `UNION DISTINCT`; `ORDER BY` / `LIMIT` для UNION оборачивают его в `SELECT * FROM (...)`

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
- `forceIndex()` → `WITH (INDEX(...))`, см. [SELECT](02-select.md#подсказки-индексов)
- `FETCH NEXT n ROWS ONLY`; UNION с лимитом без `orderBy()` получает `ORDER BY (SELECT NULL)`

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
