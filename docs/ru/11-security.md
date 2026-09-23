# Безопасность

## Автоматическое экранирование

QueryBuilder **автоматически экранирует** все идентификаторы и значения:

```php
$qb->select('*')
    ->from('users')
    ->where()
        ->eq('name', "O'Brien")
        ->end();

// WHERE (`name` = 'O\'Brien')  - автоматически экранировано
```

## Защита от SQL-инъекций

**✅ ПРАВИЛЬНО** - использование QueryBuilder:

```php
$userInput = $_GET['status'];

$qb->where()
    ->eq('status', $userInput)
    ->end();

// Значение будет экранировано автоматически
```

**❌ НЕПРАВИЛЬНО** - НЕ используйте конкатенацию строк:

```php
$userInput = $_GET['status'];
$sql = "SELECT * FROM users WHERE status = '" . $userInput . "'";
```

## Валидация идентификаторов

QueryBuilder проверяет имена таблиц, полей и алиасов:

```php
$qb->from('users');          // ✅ OK
$qb->from('users; DROP--');  // ❌ Exception: Invalid table name
```

## Зарезервированные слова

Зарезервированные слова SQL автоматически экранируются:

```php
$qb->from('fields');  // ✅ OK - будет экранировано как `fields`
$qb->from('order');   // ✅ OK - будет экранировано как `order`
```

## Опасные функции

QueryBuilder **блокирует** опасные SQL-функции:

```php
$qb->from('LOAD_FILE');        // ❌ Exception
$qb->from('INFORMATION_SCHEMA'); // ❌ Exception
```

---

[← Сложные примеры](10-examples.md) · [Содержание](index.md) · [Поддержка драйверов →](12-drivers.md)
