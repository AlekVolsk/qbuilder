# Введение и быстрый старт

## Введение

QueryBuilder - это мощный инструмент для построения SQL-запросов в PHP с поддержкой множества СУБД:

- MySQL / MariaDB
- PostgreSQL
- ClickHouse
- MS SQL Server
- Oracle
- SQLite

**Основные преимущества:**

- ✅ Безопасность: автоматическое экранирование и защита от SQL-инъекций
- ✅ Кроссплатформенность: один код работает на разных СУБД
- ✅ Читаемость: fluent interface для построения запросов
- ✅ Типобезопасность: PHPDoc аннотации и строгая типизация

---

## Быстрый старт

### Инициализация

```php
use QBuilder\QueryBuilder;
use QBuilder\QbConsts;

$qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
```

### Простой SELECT

```php
$sql = $qb->select('id', 'name', 'email')
    ->from('users')
    ->where()
        ->eq('status', 'active')
        ->end()
    ->build();

// SELECT `id`, `name`, `email` FROM `users` WHERE (`status` = 'active')
```

---

[Содержание](index.md) · [SELECT запросы →](02-select.md)
